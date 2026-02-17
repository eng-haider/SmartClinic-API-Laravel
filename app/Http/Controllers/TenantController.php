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
     * Preview what tenant ID will be generated for a given name.
     * Useful for testing before actually creating a tenant.
     * 
     * GET /api/tenants/preview?name=haider
     */
    public function previewId(Request $request): JsonResponse
    {
        $name = $request->input('name');
        
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'message' => 'Name is required',
                'message_ar' => 'الاسم مطلوب',
            ], 422);
        }

        $generatedId = $this->generateUniqueTenantId($name);
        $prefix = config('tenancy.database.prefix', 'tenant');
        $cleanName = ltrim($generatedId, '_'); // Remove leading underscore
        $databaseName = $prefix . '_' . $cleanName; // u876784197_tenant_haider

        // Check availability
        $tenantExists = Tenant::where('id', $generatedId)->exists();
        $clinicExists = DB::table('clinics')->where('id', $generatedId)->exists();
        $dbExists = !empty(DB::select("SHOW DATABASES LIKE '{$databaseName}'"));

        return response()->json([
            'success' => true,
            'message' => 'Tenant ID preview generated',
            'message_ar' => 'تم إنشاء معاينة معرف العيادة',
            'data' => [
                'name' => $name,
                'generated_id' => $generatedId,
                'database_name' => $databaseName,
                'is_available' => !$tenantExists && !$clinicExists && !$dbExists,
                'checks' => [
                    'tenant_exists' => $tenantExists,
                    'clinic_exists' => $clinicExists,
                    'database_exists' => $dbExists,
                ],
            ],
        ]);
    }

    /**
     * Store a newly created tenant (clinic) and admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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

        // Generate unique tenant ID from name
        $tenantId = $this->generateUniqueTenantId($validated['name']);
        // Database name: u876784197_tenant_haider (manually created on Hostinger)
        $cleanName = ltrim($tenantId, '_'); // Remove leading underscore from _haider -> haider
        $databaseName = config('tenancy.database.prefix') . '_' . $cleanName;
        $databaseUsername = $databaseName; // Username = database name on Hostinger
        $databasePassword = '9!iSeEys:6sO'; // Hostinger database password
        $centralConnection = config('tenancy.database.central_connection');
        
        Log::info('=== CREATING NEW TENANT ===', [
            'name' => $validated['name'],
            'generated_id' => $tenantId,
            'database' => $databaseName,
            'note' => 'Database must be manually created on Hostinger first',
        ]);

        // Check if tenant already exists
        if (Tenant::find($tenantId)) {
            return response()->json([
                'success' => false,
                'message' => "Tenant '{$tenantId}' already exists.",
                'message_ar' => "العيادة '{$tenantId}' موجودة بالفعل.",
            ], 422);
        }
        
        if (Clinic::on($centralConnection)->find($tenantId)) {
            return response()->json([
                'success' => false,
                'message' => "Clinic '{$tenantId}' already exists.",
                'message_ar' => "العيادة '{$tenantId}' موجودة بالفعل.",
            ], 422);
        }
        
        // Check if user phone exists
        if (User::on($centralConnection)->where('phone', $validated['user_phone'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already registered',
                'message_ar' => 'رقم الهاتف مسجل مسبقاً',
            ], 422);
        }
        
        if (!empty($validated['user_email']) && User::on($centralConnection)->where('email', $validated['user_email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already registered',
                'message_ar' => 'البريد الإلكتروني مسجل مسبقاً',
            ], 422);
        }
        
        DB::connection($centralConnection)->beginTransaction();
        
        try {
            // Create tenant
            $tenant = Tenant::create([
                'id' => $tenantId,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'rx_img' => $validated['rx_img'] ?? null,
                'whatsapp_template_sid' => $validated['whatsapp_template_sid'] ?? null,
                'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
                'logo' => $validated['logo'] ?? null,
                'db_name' => $databaseName,
                'db_username' => $databaseUsername,
                'db_password' => $databasePassword,
            ]);
            
            Log::info('✓ Tenant created', ['id' => $tenant->id]);
            
            // Create clinic in central database
            $clinic = Clinic::on($centralConnection)->create([
                'id' => $tenantId,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'rx_img' => $validated['rx_img'] ?? null,
                'whatsapp_template_sid' => $validated['whatsapp_template_sid'] ?? null,
                'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
                'logo' => $validated['logo'] ?? null,
            ]);
            
            Log::info('✓ Clinic created in central DB', ['id' => $clinic->id]);
            
            // Create user in central database
            $centralUser = User::on($centralConnection)->create([
                'name' => $validated['user_name'],
                'phone' => $validated['user_phone'],
                'email' => $validated['user_email'] ?? null,
                'password' => Hash::make($validated['user_password']),
                'is_active' => true,
            ]);
            
            // Set clinic_id separately (not in fillable to avoid issues with tenant databases)
            $centralUser->clinic_id = $tenantId;
            $centralUser->save();
            
            Log::info('✓ User created in central DB', ['id' => $centralUser->id]);
            
            DB::connection($centralConnection)->commit();
            
        } catch (\Exception $e) {
            DB::connection($centralConnection)->rollBack();
            Log::error('Failed to create tenant/clinic/user:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
                'message_ar' => 'فشل في إنشاء العيادة: ' . $e->getMessage(),
            ], 500);
        }
        
        // Setup tenant database
        try {
            $centralConfig = config('database.connections.' . $centralConnection);
            
            config([
                'database.connections.tenant.database' => $databaseName,
                'database.connections.tenant.username' => $databaseUsername,
                'database.connections.tenant.password' => $databasePassword,
                'database.connections.tenant.host' => $centralConfig['host'],
                'database.connections.tenant.port' => $centralConfig['port'],
            ]);
            
            DB::purge('tenant');
            
            // Test the connection to manually created database
            try {
                DB::connection('tenant')->getPdo();
                Log::info('✓ Connected to manually created database', ['database' => $databaseName]);
            } catch (\Exception $e) {
                throw new \Exception(
                    "Cannot connect to database '{$databaseName}'. " .
                    "Please manually create it on Hostinger with:\n" .
                    "- Database name: {$databaseName}\n" .
                    "- Username: {$databaseUsername}\n" .
                    "- Password: {$databasePassword}\n" .
                    "Error: " . $e->getMessage()
                );
            }
            
            Log::info('✓ Tenant database connected', ['database' => $databaseName]);
            
            // Run migrations
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            Log::info('✓ Migrations completed');
            
            // Seed roles and permissions
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'RoleAndPermissionSeeder',
                '--force' => true,
            ]);
            Log::info('✓ Roles seeded');
            
            // Seed tenant data
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);
            Log::info('✓ Tenant data seeded');
            
            // Create user in tenant database
            $tenantUser = User::on('tenant')->create([
                'name' => $validated['user_name'],
                'phone' => $validated['user_phone'],
                'email' => $validated['user_email'] ?? null,
                'password' => Hash::make($validated['user_password']),
                'is_active' => true,
            ]);
            
            // Assign super doctor role
            $roleId = DB::connection('tenant')->table('roles')
                ->where('name', 'clinic_super_doctor')
                ->value('id') ?? 1;
                
            DB::connection('tenant')->table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $tenantUser->id,
            ]);
            
            Log::info('✓ User created in tenant DB with role', ['id' => $tenantUser->id]);
            Log::info('=== TENANT SETUP COMPLETE ===', ['tenant_id' => $tenantId]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully. You can now login.',
                'message_ar' => 'تم إنشاء العيادة بنجاح. يمكنك الآن تسجيل الدخول.',
                'data' => [
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenant->name,
                    'database' => $databaseName,
                    'user' => [
                        'name' => $centralUser->name,
                        'phone' => $centralUser->phone,
                        'email' => $centralUser->email,
                    ],
                ],
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Tenant database setup failed:', ['error' => $e->getMessage()]);
            
            // Cleanup on failure
            try {
                $tenant->delete();
                $clinic->forceDelete();
                $centralUser->forceDelete();
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
     * Note: Database must be manually created on Hostinger
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
        
        $counter = 1;
        $attemptId = '_' . $baseId;
        
        // Keep trying until we find a unique tenant ID (only check tenant table, not database)
        while (Tenant::where('id', $attemptId)->exists()) {
            $attemptId = '_' . $baseId . '_' . $counter++;
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
