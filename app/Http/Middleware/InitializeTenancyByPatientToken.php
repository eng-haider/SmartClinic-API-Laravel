<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByPatientToken
{
    protected Tenancy $tenancy;

    public function __construct(Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Handle an incoming request.
     *
     * Resolution order:
     *  1. X-Tenant-ID / X-Clinic-ID header
     *  2. ?clinic= query parameter
     *  3. Auto-detect from the {token} route parameter by scanning all tenant DBs
     */
    public function handle(Request $request, Closure $next): Response
    {
        // --- Fast path: explicit tenant identifier provided ---
        $tenantId = $request->header('X-Tenant-ID')
                    ?? $request->header('X-Clinic-ID')
                    ?? $request->query('clinic');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found.',
                    'message_ar' => 'العيادة غير موجودة.',
                ], 404);
            }

            $this->tenancy->initialize($tenant);
            return $next($request);
        }

        // --- Slow path: resolve tenant from the patient public_token ---
        $token = $request->route('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve clinic for this request.',
                'message_ar' => 'تعذّر تحديد العيادة لهذا الطلب.',
            ], 400);
        }

        $tenant = $this->resolveTenantFromToken($token);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found or not publicly accessible.',
                'message_ar' => 'لم يُعثر على الملف الشخصي للمريض أو أنه غير متاح للعرض العام.',
            ], 404);
        }

        $this->tenancy->initialize($tenant);
        return $next($request);
    }

    /**
     * Iterate all tenants to find the one that owns this patient token.
     */
    private function resolveTenantFromToken(string $token): ?Tenant
    {
        foreach (Tenant::all() as $tenant) {
            try {
                // Temporarily switch to tenant DB to query
                $this->tenancy->initialize($tenant);

                $exists = DB::table('patients')
                    ->where('public_token', $token)
                    ->where('is_public_profile_enabled', true)
                    ->exists();

                if ($exists) {
                    return $tenant; // tenancy already initialized to this tenant
                }

                // End tenancy so we can try the next one cleanly
                $this->tenancy->end();
            } catch (\Throwable $e) {
                // Skip tenants with connection issues
                $this->tenancy->end();
                continue;
            }
        }

        return null;
    }
}
