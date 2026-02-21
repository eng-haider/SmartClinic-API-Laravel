<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class DemoRegisterController extends Controller
{
    /**
     * The fixed tenant ID used for demo accounts.
     */
    private const DEMO_TENANT_ID = 'tenant_test';

    /**
     * Register a new demo user into the pre-created tenant_test database.
     *
     * POST /api/auth/demo-register
     */
    public function register(Request $request): JsonResponse
    {
        // --- Validation ---
        $validated = $request->validate([
            'user_name'                  => ['required', 'string', 'max:255'],
            'user_phone'                 => ['required', 'string', 'max:20'],
            'user_password'              => ['required', 'string', 'min:6'],
        ]);

        try {
            // --- 1. Find or bootstrap the tenant_test tenant record ---
            $tenant = Tenant::find(self::DEMO_TENANT_ID);

            if (!$tenant) {
                $tenant = $this->createDemoTenantRecord();
            }

            // --- 2. Point the tenant connection to the tenant_test DB ---
            $this->configureTenantConnection($tenant);

            // --- 3. Check phone uniqueness inside tenant DB ---
            $phoneExists = DB::connection('tenant')
                ->table('users')
                ->where('phone', $validated['user_phone'])
                ->exists();

            if ($phoneExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number already registered in the demo clinic.',
                ], 422);
            }

            // --- 4. Seed roles/permissions if the tenant DB is fresh ---
            $this->seedIfNeeded();

            // --- 5. Initialize tenancy (switches the default DB connection) ---
            tenancy()->initialize($tenant);

            // --- 6. Create the user inside tenant DB ---
            $user = User::create([
                'name'      => $validated['user_name'],
                'phone'     => $validated['user_phone'],
                'password'  => Hash::make($validated['user_password']),
                'is_active' => true,
            ]);

            // --- 7. Assign super_admin role ---
            $user->assignRole('super_admin');

            // --- 8. Reload with roles for the response ---
            $user->load(['roles.permissions', 'permissions']);

            // --- 9. Generate JWT token ---
            $token = JWTAuth::fromUser($user);

            Log::info('Demo user registered', [
                'user_id' => $user->id,
                'phone'   => $user->phone,
                'tenant'  => self::DEMO_TENANT_ID,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demo account created successfully.',
                'data'    => [
                    'user'      => [
                        'id'    => $user->id,
                        'name'  => $user->name,
                        'phone' => $user->phone,
                        'roles' => $user->getRoleNames(),
                    ],
                    'tenant_id' => self::DEMO_TENANT_ID,
                    'token'     => $token,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Demo registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create the tenant_test record in the central DB.
     * The actual MySQL database must already exist on the server.
     */
    private function createDemoTenantRecord(): Tenant
    {
        $dbName   = self::DEMO_TENANT_ID; // DB name = 'tenant_test'
        $dbUser   = config('database.connections.mysql.username');
        $dbPass   = config('database.connections.mysql.password');

        $tenant = new Tenant();
        $tenant->id          = self::DEMO_TENANT_ID;
        $tenant->name        = 'Demo Clinic';
        $tenant->address     = 'Demo Address';
        $tenant->db_name     = $dbName;
        $tenant->db_username = $dbUser;
        $tenant->db_password = $dbPass;
        $tenant->saveQuietly();

        Log::info('Created tenant_test tenant record', ['db_name' => $dbName]);

        return $tenant->fresh();
    }

    /**
     * Configure the 'tenant' DB connection to point at tenant_test.
     */
    private function configureTenantConnection(Tenant $tenant): void
    {
        $centralConfig = config('database.connections.' . config('tenancy.database.central_connection'));

        $dbName   = $tenant->db_name   ?? self::DEMO_TENANT_ID;
        $dbUser   = $tenant->db_username ?? $centralConfig['username'];
        $dbPass   = $tenant->db_password ?? $centralConfig['password'];

        config([
            'database.connections.tenant.host'     => $centralConfig['host'],
            'database.connections.tenant.port'     => $centralConfig['port'],
            'database.connections.tenant.database' => $dbName,
            'database.connections.tenant.username' => $dbUser,
            'database.connections.tenant.password' => $dbPass,
        ]);

        DB::purge('tenant');
    }

    /**
     * Seed roles/permissions into the tenant DB if it is empty (first run).
     */
    private function seedIfNeeded(): void
    {
        try {
            $roleCount = DB::connection('tenant')->table('roles')->count();

            if ($roleCount === 0) {
                Log::info('Seeding roles & permissions into tenant_test');

                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'TenantDatabaseSeeder',
                    '--force' => true,
                ]);

                Log::info('tenant_test seeded successfully');
            }
        } catch (\Exception $e) {
            // If the roles table doesn't exist yet, run migrations first
            Log::warning('Roles table missing in tenant_test, running migrations', [
                'error' => $e->getMessage(),
            ]);

            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path'     => 'database/migrations/tenant',
                '--force'    => true,
            ]);

            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);
        }
    }
}
