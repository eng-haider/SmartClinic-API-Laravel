<?php

namespace App\Services;

use App\Models\User;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Models\DatabasePool;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Create a new service instance
     */
    public function __construct(
        private UserRepository $userRepository,
        private ClinicSettingService $clinicSettingService
    ) {
    }

    /**
     * Register a new clinic with full database pool setup (migrate + seed).
     */
    public function register(array $data): array
    {
        $centralConnection = config('tenancy.database.central_connection');

        // Check if phone exists
        if (!empty($data['phone']) && $this->userRepository->phoneExists($data['phone'])) {
            throw new \Exception('Phone already registered');
        }

        if (!empty($data['email']) && $this->userRepository->emailExists($data['email'])) {
            throw new \Exception('Email already registered');
        }

        // Check pool availability
        if (DatabasePool::availableCount() === 0) {
            throw new \Exception('Service temporarily unavailable: no database slots available. Contact administrator.');
        }

        // Generate unique tenant ID from clinic name
        $baseId   = '_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($data['clinic_name']));
        $tenantId = $baseId;
        $counter  = 1;
        while (Tenant::where('id', $tenantId)->exists()) {
            $tenantId = $baseId . '_' . $counter++;
        }

        // Claim a database from the pool (atomic)
        $poolSlot         = DatabasePool::claim($tenantId);
        $databaseName     = $poolSlot->db_name;
        $databaseUsername = $poolSlot->db_username;
        $databasePassword = $poolSlot->db_password;

        // ── Step 1: Create central records ────────────────────────────────
        $tenant      = null;
        $centralUser = null;

        DB::connection($centralConnection)->beginTransaction();
        try {
            $tenant = Tenant::create([
                'id'          => $tenantId,
                'name'        => $data['clinic_name'],
                'specialty'   => $data['specialty'],
                'address'     => $data['clinic_address'] ?? null,
                'has_ai_bot'  => false,
                'db_name'     => $databaseName,
                'db_username' => $databaseUsername,
                'db_password' => $databasePassword,
            ]);

            DB::connection($centralConnection)->table('clinics')->insert([
                'id'         => $tenantId,
                'name'       => $data['clinic_name'],
                'specialty'  => $data['specialty'],
                'address'    => $data['clinic_address'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $clinic = Clinic::on($centralConnection)->findOrFail($tenantId);

            $centralUser = User::on($centralConnection)->create([
                'name'      => $data['name'],
                'phone'     => $data['phone'],
                'email'     => $data['email'] ?? null,
                'password'  => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $centralUser->clinic_id = $tenantId;
            $centralUser->save();

            DB::connection($centralConnection)->commit();
        } catch (\Exception $e) {
            DB::connection($centralConnection)->rollBack();
            $poolSlot->update(['status' => 'available', 'tenant_id' => null, 'claimed_at' => null]);
            throw $e;
        }

        // ── Step 2: Setup tenant database (migrate + seed + user) ─────────
        try {
            $centralConfig = config('database.connections.' . $centralConnection);

            config([
                'database.connections.tenant.host'     => $centralConfig['host'],
                'database.connections.tenant.port'     => $centralConfig['port'],
                'database.connections.tenant.database' => $databaseName,
                'database.connections.tenant.username' => $databaseUsername,
                'database.connections.tenant.password' => $databasePassword,
            ]);
            DB::purge('tenant');
            DB::connection('tenant')->getPdo();

            $originalDefault = config('database.default');
            config(['database.default' => 'tenant']);

            try {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path'     => 'database/migrations/tenant',
                    '--force'    => true,
                ]);

                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                Artisan::call('db:seed', [
                    '--class' => 'TenantDatabaseSeeder',
                    '--force' => true,
                ]);
            } finally {
                config(['database.default' => $originalDefault]);
                DB::purge('tenant');
            }

            // Create user in tenant DB
            $tenantUser = User::on('tenant')->create([
                'name'      => $data['name'],
                'phone'     => $data['phone'],
                'email'     => $data['email'] ?? null,
                'password'  => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $roleId = DB::connection('tenant')->table('roles')
                ->where('name', 'clinic_super_doctor')
                ->value('id') ?? 1;

            DB::connection('tenant')->table('model_has_roles')->insert([
                'role_id'    => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id'   => $tenantUser->id,
            ]);
        } catch (\Exception $e) {
            // Rollback central records and release pool slot
            try {
                $tenant->forceDelete();
                DB::connection($centralConnection)->table('clinics')->where('id', $tenantId)->delete();
                $centralUser->forceDelete();
                $poolSlot->update(['status' => 'available', 'tenant_id' => null, 'claimed_at' => null]);
            } catch (\Exception $cleanupError) {
                // Log but don't rethrow cleanup errors
            }
            throw $e;
        }

        // Generate JWT for the central user
        $centralUser->load('roles', 'clinic');
        $token = JWTAuth::fromUser($centralUser);

        return [
            'user'    => $centralUser,
            'clinic'  => $clinic,
            'token'   => $token,
            'message' => 'Clinic registered successfully. You can now login.',
        ];
    }

    /**
     * Smart Login: One-step authentication (discovers tenant and logs in)
     * This combines checkCredentials and login into a single call
     * AUTO-CREATES tenant database and user if they don't exist
     */
    public function smartLogin(string $phone, string $password): array
    {
        // Step 1: Authenticate in CENTRAL database and get tenant_id
        $centralConnection = config('tenancy.database.central_connection');
        $centralUser = User::on($centralConnection)->where('phone', $phone)->first();

        if (!$centralUser || !Hash::check($password, $centralUser->password)) {
            throw new \Exception('Invalid phone number or password');
        }

        if (!$centralUser->is_active) {
            throw new \Exception('User account is inactive');
        }

        // Get user's clinic
        $clinic = $centralUser->clinic;

        if (!$clinic) {
            throw new \Exception('User is not associated with any clinic');
        }

        // Step 2: Ensure tenant exists, create if not
        $tenant = \App\Models\Tenant::find($clinic->id);

        if (!$tenant) {
            // Auto-create tenant record
            $tenant = $this->createTenantForClinic($clinic);
        }

        // Step 3: Ensure tenant database is setup
        $this->ensureTenantDatabaseExists($tenant, $centralUser, $password);

        // Step 4: Initialize tenant context
        tenancy()->initialize($tenant);

        // Step 5: Get user from tenant database with roles and permissions
        $tenantUser = User::where('phone', $phone)->first();

        if (!$tenantUser) {
            throw new \Exception('User not found in tenant database');
        }

        if (!$tenantUser->is_active) {
            throw new \Exception('User account is inactive in tenant database');
        }

        // Load roles and permissions explicitly
        $tenantUser->load(['roles.permissions', 'permissions']);

        // Debug: Log what we got
        \Illuminate\Support\Facades\Log::info('Tenant user loaded', [
            'user_id' => $tenantUser->id,
            'roles_count' => $tenantUser->roles->count(),
            'permissions_count' => $tenantUser->permissions->count(),
            'all_permissions_count' => $tenantUser->getAllPermissions()->count(),
        ]);

        // Generate token for tenant user
        $token = JWTAuth::fromUser($tenantUser);

        return [
            'user' => $tenantUser,
            'token' => $token,
            'tenant_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'has_ai_bot' => $clinic->has_ai_bot,
            'specialty' => $clinic->specialty ?? 'dental',
            'message' => 'Login successful',
        ];
    }

    /**
     * Create tenant record for existing clinic
     */
    private function createTenantForClinic(Clinic $clinic): \App\Models\Tenant
    {
        $tenant = new \App\Models\Tenant();
        $tenant->setAttribute('id', $clinic->id);
        $tenant->exists = false;

        // Copy clinic data to tenant
        $tenant->setAttribute('name', $clinic->name);
        $tenant->setAttribute('address', $clinic->address);
        $tenant->setAttribute('logo', $clinic->logo);

        // Store database credentials for Hostinger
        $databaseName = config('tenancy.database.prefix') . $clinic->id;
        $tenant->setAttribute('db_name', $databaseName);
        $tenant->setAttribute('db_username', $databaseName);
        $tenant->setAttribute('db_password', env('TENANT_DB_PASSWORD'));

        $tenant->saveQuietly();
        $tenant->refresh();

        \Illuminate\Support\Facades\Log::info('Auto-created tenant record', [
            'tenant_id' => $tenant->id,
            'db_name' => $databaseName
        ]);

        return $tenant;
    }

    /**
     * Ensure tenant database exists and is properly setup
     */
    private function ensureTenantDatabaseExists(\App\Models\Tenant $tenant, User $centralUser, string $password): void
    {
        $databaseName = $tenant->db_name ?? (config('tenancy.database.prefix') . $tenant->id);
        $tenantUsername = $tenant->db_username ?? $databaseName;
        $tenantPassword = $tenant->db_password ?? env('TENANT_DB_PASSWORD');

        $centralConfig = config('database.connections.' . config('tenancy.database.central_connection'));

        // Configure the tenant connection
        config([
            'database.connections.tenant.database' => $databaseName,
            'database.connections.tenant.username' => $tenantUsername,
            'database.connections.tenant.password' => $tenantPassword,
            'database.connections.tenant.host' => $centralConfig['host'],
            'database.connections.tenant.port' => $centralConfig['port'],
        ]);

        // Purge and reconnect
        DB::purge('tenant');

        // Test connection
        try {
            DB::connection('tenant')->getPdo();

            // Check if database is already setup (has users table with data)
            try {
                $userCount = DB::connection('tenant')->table('users')->count();
                if ($userCount > 0) {
                    \Illuminate\Support\Facades\Log::info('Tenant database already setup', [
                        'database' => $databaseName,
                        'user_count' => $userCount
                    ]);
                    return; // Database already setup
                }
            } catch (\Exception $e) {
                // Table doesn't exist, need to run migrations
            }

            // Database exists but not setup - run migrations and seeders
            \Illuminate\Support\Facades\Log::info('Setting up tenant database', [
                'database' => $databaseName
            ]);

            // Run migrations
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // Run seeders
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'RoleAndPermissionSeeder',
                '--force' => true,
            ]);

            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);

            // Create user in tenant database
            $createdUser = User::on('tenant')->create([
                'name' => $centralUser->name,
                'phone' => $centralUser->phone,
                'email' => $centralUser->email ?? null,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            // Assign role - need to refresh connection context first
            DB::purge('tenant');
            $tenantUser = User::on('tenant')->where('phone', $centralUser->phone)->first();
            if ($tenantUser) {
                try {
                    $tenantUser->assignRole('clinic_super_doctor');
                    \Illuminate\Support\Facades\Log::info('Role assigned to tenant user', [
                        'user_id' => $tenantUser->id,
                        'role' => 'clinic_super_doctor'
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to assign role', [
                        'user_id' => $tenantUser->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            \Illuminate\Support\Facades\Log::info('Tenant database setup completed', [
                'database' => $databaseName
            ]);

        } catch (\Exception $e) {
            throw new \Exception(
                "Database '{$databaseName}' does not exist. " .
                "Please create it in your hosting panel (e.g., hPanel on Hostinger): " .
                "(1) Create database: {$databaseName}. " .
                "(2) Create user: {$tenantUsername}. " .
                "(3) Set password to match TENANT_DB_PASSWORD in .env. " .
                "Original error: " . $e->getMessage()
            );
        }
    }

    /**
     * Step 1: Check credentials and return tenant_id (no token yet)
     * Used to discover which clinic the user belongs to
     */
    public function checkCredentials(string $phone, string $password): array
    {
        // Authenticate in CENTRAL database (get connection from config)
        $centralConnection = config('tenancy.database.central_connection');
        $user = User::on($centralConnection)->where('phone', $phone)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid phone number or password');
        }

        if (!$user->is_active) {
            throw new \Exception('User account is inactive');
        }

        // Get user's clinic
        $clinic = $user->clinic;

        if (!$clinic) {
            throw new \Exception('User is not associated with any clinic');
        }

        // Return tenant_id for the frontend to use in step 2
        return [
            'tenant_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'has_ai_bot' => $clinic->has_ai_bot,
            'specialty' => $clinic->specialty ?? 'dental',
            'user_name' => $user->name,
            'message' => 'Credentials verified. Please proceed with tenant login.',
        ];
    }

    /**
     * Step 2: Login user with tenant context (requires X-Tenant-ID)
     * This is called after checkCredentials with the tenant_id
     */
    public function login(string $phone, string $password): array
    {
        $user = $this->userRepository->getByPhone($phone);

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid phone number or password');
        }

        if (!$user->is_active) {
            throw new \Exception('User account is inactive');
        }

        // Load roles and permissions explicitly
        $user->load(['roles.permissions', 'permissions']);

        // Generate token
        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful',
        ];
    }

    /**
     * Logout user (invalidate token)
     */
    public function logout(): array
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ['message' => 'Logout successful'];
        } catch (\Exception $e) {
            throw new \Exception('Logout failed: ' . $e->getMessage());
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(): array
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return [
                'token' => $token,
                'message' => 'Token refreshed successfully',
            ];
        } catch (\Exception $e) {
            throw new \Exception('Token refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Get authenticated user
     */
    public function me(): ?User
    {
        return JWTAuth::user();
    }

    /**
     * Get all users
     */
    public function getAllUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get user by ID
     */
    public function getUser(int $id): ?User
    {
        return $this->userRepository->getById($id);
    }

    /**
     * Update user
     */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->getUser($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        // Check if email is being changed
        if (!empty($data['email']) && $data['email'] !== $user->email) {
            if ($this->userRepository->emailExists($data['email'], $id)) {
                throw new \Exception('Email already in use');
            }
        }

        // Check if phone is being changed
        if (!empty($data['phone']) && $data['phone'] !== $user->phone) {
            if ($this->userRepository->phoneExists($data['phone'], $id)) {
                throw new \Exception('Phone already in use');
            }
        }

        // Hash password if provided
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $this->userRepository->update($id, $data);
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->getUser($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $this->userRepository->delete($id);
    }

    /**
     * Change password
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        $user = $this->getUser($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if (!Hash::check($oldPassword, $user->password)) {
            throw new \Exception('Current password is incorrect');
        }

        $this->userRepository->update($userId, [
            'password' => Hash::make($newPassword),
        ]);

        return ['message' => 'Password changed successfully'];
    }
}
