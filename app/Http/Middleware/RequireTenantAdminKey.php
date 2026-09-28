<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Guards the central tenant-management endpoints (list / update / delete /
 * migrate / seed clinics). There is no admin user in the central DB, so these
 * are protected by a static secret set in .env as TENANT_ADMIN_KEY and sent
 * in the X-Admin-Key header.
 *
 * Fails closed: if the key is not configured, every request is rejected.
 */
class RequireTenantAdminKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('tenancy.admin_key', '');
        $provided = (string) $request->header('X-Admin-Key', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            Log::warning('Rejected tenant admin request', [
                'ip'     => $request->ip(),
                'method' => $request->method(),
                'path'   => $request->path(),
                'reason' => $expected === '' ? 'TENANT_ADMIN_KEY not configured' : 'invalid or missing X-Admin-Key',
            ]);

            return response()->json([
                'success'    => false,
                'message'    => 'Unauthorized',
                'message_ar' => 'غير مصرح',
            ], 401);
        }

        return $next($request);
    }
}
