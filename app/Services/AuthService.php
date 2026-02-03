<?php

namespace App\Services;

use App\Models\User;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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
     * Register a new user
     */
    public function register(array $data): array
    {
        // Check if email exists
        if (!empty($data['email']) && $this->userRepository->emailExists($data['email'])) {
            throw new \Exception('Email already registered');
        }

        // Check if phone exists
        if (!empty($data['phone']) && $this->userRepository->phoneExists($data['phone'])) {
            throw new \Exception('Phone already registered');
        }

        DB::beginTransaction();
        
        try {
            // Create clinic first
            $clinic = Clinic::create([
                'name' => $data['clinic_name'],
                'address' => $data['clinic_address'],
                'phone' => $data['clinic_phone'] ?? null,
                'email' => $data['clinic_email'] ?? null,
            ]);

            // Create default settings for the clinic from setting definitions
            $this->clinicSettingService->createDefaultSettingsForClinic($clinic);

            // Hash password
            $userData = [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'clinic_id' => $clinic->id,
            ];

            // Always set role to clinic_super_doctor for registration
            $roleName = 'clinic_super_doctor';

            $user = $this->userRepository->create($userData);

            // Assign role using Spatie
            $user->assignRole($roleName);

            // Refresh user to load relationships
            $user->load('roles', 'clinic');

            // Generate token
            $token = JWTAuth::fromUser($user);

            DB::commit();

            return [
                'user' => $user,
                'clinic' => $clinic,
                'token' => $token,
                'message' => 'User and clinic registered successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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

        // Ensure user has the clinic_super_doctor role (auto-assign if missing)
        if (!$tenantUser->hasRole('clinic_super_doctor', 'web')) {
            try {
                $tenantUser->assignRole('clinic_super_doctor');
                \Illuminate\Support\Facades\Log::info('Auto-assigned clinic_super_doctor role to user', [
                    'user_id' => $tenantUser->id,
                    'phone' => $phone,
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to auto-assign role', [
                    'user_id' => $tenantUser->id,
                    'error' => $e->getMessage(),
                ]);
            }
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
