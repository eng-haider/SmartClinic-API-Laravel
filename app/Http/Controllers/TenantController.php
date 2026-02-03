<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    /**
     * Display a listing of all tenants (clinics).
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Tenants retrieved successfully',
            'message_ar' => 'تم جلب العيادات بنجاح',
            'data' => $tenants,
        ]);
    }

    /**
     * Store a newly created tenant (clinic) and admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'rx_img' => 'nullable|string',
            'whatsapp_template_sid' => 'nullable|string',
            'whatsapp_phone' => 'nullable|string|max:20',
            'logo' => 'nullable|string',
            // User data
            'user_name' => 'required|string|max:255',
            'user_phone' => 'required|string|max:20',
            'user_email' => 'nullable|email|max:255',
            'user_password' => 'required|string|min:6',
        ]);

        // Generate unique tenant ID if not provided
        if (empty($validated['id'])) {
            $validated['id'] = $this->generateUniqueTenantId($validated['name']);
        }

        $centralConnection = config('tenancy.database.central_connection');
        
        // Step 0: Check if tenant/clinic already exists
        $existingTenant = Tenant::find($validated['id']);
        if ($existingTenant) {
            return response()->json([
                'success' => false,
                'message' => "Tenant with ID '{$validated['id']}' already exists. Use a different clinic name or delete the existing tenant first.",
                'message_ar' => "العيادة بمعرف '{$validated['id']}' موجودة بالفعل. استخدم اسماً مختلفاً أو احذف العيادة الموجودة أولاً.",
            ], 422);
        }
        
        $existingClinic = Clinic::on($centralConnection)->find($validated['id']);
        if ($existingClinic) {
            return response()->json([
                'success' => false,
                'message' => "Clinic with ID '{$validated['id']}' already exists. Use a different clinic name or delete the existing clinic first.",
                'message_ar' => "العيادة بمعرف '{$validated['id']}' موجودة بالفعل. استخدم اسماً مختلفاً أو احذف العيادة الموجودة أولاً.",
            ], 422);
        }
        
        DB::connection($centralConnection)->beginTransaction();
        
        try {
            // Step 1: Check if user already exists in central database
            $existingUser = User::on($centralConnection)
                ->where('phone', $validated['user_phone'])
                ->first();
                
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number already registered',
                    'message_ar' => 'رقم الهاتف مسجل مسبقاً',
                ], 422);
            }
            
            if (!empty($validated['user_email'])) {
                $existingEmail = User::on($centralConnection)
                    ->where('email', $validated['user_email'])
                    ->first();
                    
                if ($existingEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already registered',
                        'message_ar' => 'البريد الإلكتروني مسجل مسبقاً',
                    ], 422);
                }
            }
            
            // Step 2: Create tenant record (this IS the clinic)
            $tenant = new Tenant();
            $tenant->setAttribute('id', $validated['id']);
            $tenant->exists = false;
            
            foreach ($validated as $key => $value) {
                if ($key !== 'id' && !str_starts_with($key, 'user_')) {
                    $tenant->setAttribute($key, $value);
                }
            }
            
            $tenant->saveQuietly();
            $tenant->refresh();
            
            Log::info('Tenant created with ID:', ['id' => $tenant->id]);
            
            // Step 2.5: Create clinic record in central database (mirror of tenant)
            $clinic = Clinic::on($centralConnection)->create([
                'id' => $tenant->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'rx_img' => $validated['rx_img'] ?? null,
                'whatsapp_template_sid' => $validated['whatsapp_template_sid'] ?? null,
                'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
                'logo' => $validated['logo'] ?? null,
            ]);
            
            Log::info('Clinic record created in central DB:', ['clinic_id' => $clinic->id]);
            
            // Step 3: Create user in central database
            $centralUser = User::on($centralConnection)->create([
                'name' => $validated['user_name'],
                'phone' => $validated['user_phone'],
                'email' => $validated['user_email'] ?? null,
                'password' => Hash::make($validated['user_password']),
                'clinic_id' => $tenant->id, // tenant ID is the clinic ID
                'is_active' => true,
            ]);
            
            Log::info('User created in central DB:', ['user_id' => $centralUser->id]);
            
            DB::connection($centralConnection)->commit();
            
        } catch (\Exception $e) {
            DB::connection($centralConnection)->rollBack();
            Log::error('Failed to create tenant/user in central database:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant/user: ' . $e->getMessage(),
                'message_ar' => 'فشل في إنشاء العيادة والمستخدم: ' . $e->getMessage(),
            ], 500);
        }
        
        // Step 4: Setup tenant database (assumes database already exists on shared hosting)
        try {
            $databaseName = config('tenancy.database.prefix') . $tenant->id;
            
            // Check if we should create database (only on local/VPS environments)
            $autoCreateDatabase = config('tenancy.auto_create_database', false);
            
            if ($autoCreateDatabase) {
                try {
                    // Create the database (only works on VPS/local with proper permissions)
                    DB::statement("CREATE DATABASE `{$databaseName}`");
                    Log::info('Database created:', ['database' => $databaseName]);
                } catch (\Exception $e) {
                    Log::warning('Could not auto-create database (expected on shared hosting):', [
                        'database' => $databaseName,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::info('Skipping auto database creation (using pre-created database):', ['database' => $databaseName]);
            }
            
            
            // Verify database exists by attempting to connect
            try {
                $tenant->run(function() {
                    DB::connection()->getPdo();
                });
                Log::info('Database connection verified:', ['database' => $databaseName]);
            } catch (\Exception $e) {
                // Provide helpful error message for shared hosting
                $fullDatabaseName = $databaseName;
                if (env('DB_USERNAME')) {
                    // On Hostinger/shared hosting, database names are prefixed with username
                    $userPrefix = explode('_', env('DB_USERNAME'))[0] . '_';
                    $fullDatabaseName = $userPrefix . $databaseName;
                }
                
                throw new \Exception(
                    "Database '{$databaseName}' does not exist. " .
                    "On shared hosting (Hostinger/cPanel), you must create it manually:\n" .
                    "1. Go to your hosting panel → Databases → MySQL Databases\n" .
                    "2. Create database with name: {$fullDatabaseName}\n" .
                    "3. Ensure your DB user has access to this database\n" .
                    "4. Try creating the tenant again\n" .
                    "Original error: " . $e->getMessage()
                );
            }
            
            // Step 5: Run migrations and create user in tenant database
            $userPassword = $validated['user_password']; // Store password before closure
            
            $tenant->run(function() use ($validated, $userPassword) {
                // Run tenant migrations
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
                
                // Clear cache
                try {
                    Artisan::call('cache:clear');
                } catch (\Exception $e) {
                    Log::warning('Cache clear failed:', ['error' => $e->getMessage()]);
                }
                
                // Run seeder to create roles and permissions
                Artisan::call('db:seed', [
                    '--class' => 'RoleAndPermissionSeeder',
                    '--force' => true,
                ]);
                
                // Run additional tenant seeders if needed
                Artisan::call('db:seed', [
                    '--class' => 'TenantDatabaseSeeder',
                    '--force' => true,
                ]);
                
                // Create user in tenant database with same data
                $tenantUser = User::create([
                    'name' => $validated['user_name'],
                    'phone' => $validated['user_phone'],
                    'email' => $validated['user_email'] ?? null,
                    'password' => Hash::make($userPassword),
                    'is_active' => true,
                ]);
                
                // Assign clinic_super_doctor role
                $tenantUser->assignRole('clinic_super_doctor');
                
                Log::info('User created in tenant DB:', ['user_id' => $tenantUser->id]);
            });
            
            Log::info('Tenant setup completed:', ['id' => $tenant->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant and user created successfully. You can now login.',
                'message_ar' => 'تم إنشاء العيادة والمستخدم بنجاح. يمكنك الآن تسجيل الدخول.',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'user' => [
                        'name' => $centralUser->name,
                        'phone' => $centralUser->phone,
                        'email' => $centralUser->email,
                    ],
                ],
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Database creation/setup failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Rollback: Delete tenant and user
            try {
                if (isset($tenant)) {
                    $tenant->delete();
                }
                if (isset($clinic)) {
                    $clinic->forceDelete();
                }
                if (isset($centralUser)) {
                    $centralUser->forceDelete();
                }
            } catch (\Exception $cleanupError) {
                Log::error('Cleanup failed:', ['error' => $cleanupError->getMessage()]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup tenant database: ' . $e->getMessage(),
                'message_ar' => 'فشل في إعداد قاعدة البيانات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a unique tenant ID based on clinic name
     */
    private function generateUniqueTenantId(string $clinicName): string
    {
        $baseId = Str::slug($clinicName, '_');
        
        // If slug is empty or invalid, use fallback
        if (empty($baseId) || is_numeric($baseId) || strlen($baseId) < 2) {
            $baseId = preg_replace('/[^a-z0-9]+/i', '_', strtolower($clinicName));
            if (empty($baseId) || strlen($baseId) < 2) {
                $baseId = 'clinic_' . Str::lower(Str::random(6));
            }
        }
        
        $prefix = config('tenancy.database.prefix', 'tenant');
        $counter = 1;
        $attemptId = '_' . $baseId;
        
        // Keep trying until we find a unique ID
        while (true) {
            // Check if ID exists in tenants table
            if (Tenant::where('id', $attemptId)->exists()) {
                $attemptId = '_' . $baseId . '_' . $counter++;
                continue;
            }
            
            // Check if database exists
            $dbName = $prefix . $attemptId;
            $dbExists = DB::select("SHOW DATABASES LIKE '{$dbName}'");
            
            if (!empty($dbExists)) {
                $attemptId = '_' . $baseId . '_' . $counter++;
                continue;
            }
            
            // Found unique ID
            break;
        }
        
        return $attemptId;
    }

    /**
     * Display the specified tenant (clinic).
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant retrieved successfully',
            'message_ar' => 'تم جلب العيادة بنجاح',
            'data' => $tenant,
        ]);
    }

    /**
     * Update the specified tenant (clinic).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:500',
            'rx_img' => 'nullable|string',
            'whatsapp_template_sid' => 'nullable|string',
            'whatsapp_message_count' => 'nullable|integer',
            'whatsapp_phone' => 'nullable|string|max:20',
            'show_image_case' => 'nullable|boolean',
            'doctor_mony' => 'nullable|integer',
            'teeth_v2' => 'nullable|boolean',
            'send_msg' => 'nullable|boolean',
            'show_rx_id' => 'nullable|boolean',
            'logo' => 'nullable|string',
            'api_whatsapp' => 'nullable|boolean',
        ]);

        $tenant->update($validated);
        
        // Also update the clinic record in central database
        $clinic = Clinic::find($id);
        if ($clinic) {
            $clinic->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant updated successfully',
            'message_ar' => 'تم تحديث العيادة بنجاح',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Remove the specified tenant (clinic).
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        try {
            // Delete clinic record first
            $clinic = Clinic::find($id);
            if ($clinic) {
                $clinic->forceDelete();
            }
            
            $tenant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully. Database has been removed.',
                'message_ar' => 'تم حذف العيادة بنجاح. تم إزالة قاعدة البيانات.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant: ' . $e->getMessage(),
                'message_ar' => 'فشل في حذف العيادة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get domains for a specific tenant.
     */
    public function domains(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Domains retrieved successfully',
            'message_ar' => 'تم جلب النطاقات بنجاح',
            'data' => $tenant->domains,
        ]);
    }

    /**
     * Add a domain to a tenant.
     */
    public function addDomain(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        $validated = $request->validate([
            'domain' => 'required|string|unique:domains,domain',
        ]);

        $domain = $tenant->domains()->create(['domain' => $validated['domain']]);

        return response()->json([
            'success' => true,
            'message' => 'Domain added successfully',
            'message_ar' => 'تم إضافة النطاق بنجاح',
            'data' => $domain,
        ], 201);
    }

    /**
     * Run migrations for a specific tenant.
     */
    public function migrate(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        try {
            $tenant->run(function () {
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Migrations run successfully for tenant',
                'message_ar' => 'تم تشغيل الترحيلات بنجاح للعيادة',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to run migrations: ' . $e->getMessage(),
                'message_ar' => 'فشل في تشغيل الترحيلات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Seed a specific tenant's database.
     */
    public function seed(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'message_ar' => 'العيادة غير موجودة',
            ], 404);
        }

        $seederClass = $request->input('seeder', 'DatabaseSeeder');

        try {
            $tenant->run(function () use ($seederClass) {
                Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Seeder run successfully for tenant',
                'message_ar' => 'تم تشغيل البذور بنجاح للعيادة',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to run seeder: ' . $e->getMessage(),
                'message_ar' => 'فشل في تشغيل البذور: ' . $e->getMessage(),
            ], 500);
        }
    }
}
