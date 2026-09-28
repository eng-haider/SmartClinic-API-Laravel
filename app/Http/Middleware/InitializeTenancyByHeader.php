<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class InitializeTenancyByHeader
{
    /**
     * The tenancy instance.
     */
    protected Tenancy $tenancy;

    /**
     * How many times to retry on a transient connection failure.
     */
    protected int $maxConnectionAttempts = 3;

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
        // Get tenant ID from header or query parameter (for public links)
        // Query parameter 'clinic' is used for QR codes and public access
        $tenantId = $request->header('X-Tenant-ID')
                    ?? $request->header('X-Clinic-ID')
                    ?? $request->query('clinic')
                    ?? $this->tenantIdFromToken($request);

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required. Please provide X-Tenant-ID header or clinic parameter.',
                'message_ar' => 'معرف العيادة مطلوب. يرجى توفير رأس X-Tenant-ID أو معامل clinic.',
            ], 400);
        }

        // Find and initialize tenant.
        //
        // The lookup hits the central connection, and initialize() then opens a
        // connection to the tenant's own database. On Hostinger shared hosting
        // either can intermittently fail with a transient connection error
        // (e.g. "[2002] Operation not permitted" when the connection limit is
        // momentarily hit) — which is why this "sometimes works, sometimes
        // doesn't". We retry a few times on those transient errors before
        // giving up. A non-transient failure (or running out of retries)
        // returns a clean 503 instead of a raw 500 that leaks SQL and file
        // paths, and is logged for debugging.
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $tenant = Tenant::find($tenantId);

                if (!$tenant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tenant not found.',
                        'message_ar' => 'العيادة غير موجودة.',
                    ], 404);
                }

                $this->tenancy->initialize($tenant);

                break;
            } catch (QueryException $e) {
                if ($this->isTransientConnectionError($e) && $attempt < $this->maxConnectionAttempts) {
                    // Drop the dead/blocked connection so the next attempt
                    // opens a fresh one, then back off briefly before retrying.
                    $this->purgeConnections();
                    usleep(150_000 * $attempt); // 150ms, 300ms, ...

                    continue;
                }

                Log::error('Tenancy initialization failed', [
                    'tenant_id' => $tenantId,
                    'attempts' => $attempt,
                    'sql_state' => $e->getCode(),
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to connect to the clinic database. Please try again shortly.',
                    'message_ar' => 'تعذر الاتصال بقاعدة بيانات العيادة. يرجى المحاولة مرة أخرى بعد قليل.',
                ], 503);
            }
        }

        return $next($request);
    }

    /**
     * Determine whether a QueryException is a transient connection-level error
     * that is worth retrying (vs. a real query/credentials error that won't
     * resolve by trying again).
     *
     * MySQL/PDO connection failures surface as SQLSTATE "HY000" with driver
     * codes like 2002 (can't connect / "Operation not permitted"), 2003, 2006
     * ("server has gone away"), 2013 ("lost connection"), or 1040/1203
     * (too many connections).
     */
    protected function isTransientConnectionError(QueryException $e): bool
    {
        $message = $e->getMessage();

        foreach (['2002', '2003', '2006', '2013', '1040', '1203', 'Operation not permitted', 'server has gone away', 'Lost connection', 'too many connections'] as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Close any open DB connections so the next attempt reconnects cleanly.
     */
    protected function purgeConnections(): void
    {
        try {
            DB::disconnect();
        } catch (\Throwable) {
            // Nothing to disconnect / already closed — ignore.
        }
    }

    /**
     * Resolve the tenant id from the JWT's custom `tenant_id` claim.
     *
     * This lets authenticated clients hit /api/tenant/* routes using only the
     * Authorization bearer token (no X-Tenant-ID header needed). We decode the
     * token's payload to read the claim — this verifies the signature but does
     * NOT authenticate the user, so it does not require the tenant DB (which is
     * exactly what we're trying to initialize here). Any failure is swallowed so
     * a missing/invalid token simply falls through to the "tenant required" error.
     */
    protected function tenantIdFromToken(Request $request): ?string
    {
        if (!$request->bearerToken()) {
            return null;
        }

        try {
            $tenantId = JWTAuth::parseToken()->getPayload()->get('tenant_id');

            return $tenantId !== null ? (string) $tenantId : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
