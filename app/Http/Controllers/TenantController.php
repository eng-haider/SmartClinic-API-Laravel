<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
     * Store a newly created tenant (clinic).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string|unique:tenants,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'rx_img' => 'nullable|string',
            'whatsapp_template_sid' => 'nullable|string',
            'whatsapp_phone' => 'nullable|string|max:20',
            'logo' => 'nullable|string',
        ]);

        // Generate ID if not provided
        if (empty($validated['id'])) {
            $validated['id'] = 'clinic_' . Str::slug($validated['name']) . '_' . Str::random(6);
        }

        try {
            $tenant = Tenant::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully. Database has been provisioned.',
                'message_ar' => 'تم إنشاء العيادة بنجاح. تم إعداد قاعدة البيانات.',
                'data' => $tenant,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
                'message_ar' => 'فشل في إنشاء العيادة: ' . $e->getMessage(),
            ], 500);
        }
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
