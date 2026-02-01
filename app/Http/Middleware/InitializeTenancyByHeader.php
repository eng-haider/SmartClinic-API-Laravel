<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHeader
{
    /**
     * The tenancy instance.
     */
    protected Tenancy $tenancy;

    public function __construct(Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get tenant ID from header
        $tenantId = $request->header('X-Tenant-ID') ?? $request->header('X-Clinic-ID');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required. Please provide X-Tenant-ID or X-Clinic-ID header.',
                'message_ar' => 'معرف العيادة مطلوب. يرجى توفير رأس X-Tenant-ID أو X-Clinic-ID.',
            ], 400);
        }

        // Find and initialize tenant
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
}
