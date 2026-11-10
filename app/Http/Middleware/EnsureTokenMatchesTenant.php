<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Refuses a request whose token was issued for another clinic.
 *
 * InitializeTenancyByHeader prefers the X-Tenant-ID header over the token's
 * tenant_id claim, so a client could point a valid token at another clinic's
 * database (where the same user id is a different person). The agent acts on
 * data, so its routes require the two to agree. Tokens issued before the claim
 * existed carry no tenant_id and are let through.
 */
class EnsureTokenMatchesTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $claim = $this->tokenTenant($request);
        $current = tenant('id');

        if ($claim !== null && $current !== null && (string) $claim !== (string) $current) {
            return response()->json([
                'success' => false,
                'message' => 'This token belongs to another clinic.',
                'message_ar' => 'هذا الرمز يخص عيادة أخرى.',
            ], 403);
        }

        return $next($request);
    }

    protected function tokenTenant(Request $request): ?string
    {
        if (!$request->bearerToken()) {
            return null;
        }

        try {
            $claim = JWTAuth::parseToken()->getPayload()->get('tenant_id');

            return $claim !== null ? (string) $claim : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
