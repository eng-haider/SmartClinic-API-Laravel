<?php

namespace App\Http\Middleware;

use App\Database\RetryingMySqlConnector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one line per request to storage/logs/requests-YYYY-MM-DD.log: who sent it
 * (clinic, user, IP, app or browser), what it was, how it ended, and how many MySQL
 * connections it opened. `php artisan requests:report` sums a day up, to see whether
 * the 20-a-second connection limit is reached by normal use, by one client sending
 * too much, or by one slow endpoint.
 *
 * Written to a file, not the database, so logging never needs a connection itself.
 * Turn it off with REQUEST_LOG=false.
 */
class LogRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('logging.request_log') || $request->isMethod('OPTIONS')) {
            return;
        }

        try {
            $started = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT', microtime(true));
            $auth = auth()->guard();

            Log::channel('requests')->info(json_encode([
                't'  => date('Y-m-d H:i:s', (int) $started).sprintf('.%03d', ($started - floor($started)) * 1000),
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'st' => $response->getStatusCode(),
                'm'  => $request->method(),
                // The route pattern (patients/{id}) so the same endpoint adds up.
                'p'  => $request->route()?->uri() ?? $request->path(),
                'tn' => function_exists('tenant') && tenant() ? tenant('id') : $request->header('X-Tenant-ID'),
                // hasUser() so logging never looks the user up (that would need a connection).
                'u'  => $auth->hasUser() ? $auth->id() : null,
                'ip' => $request->ip(),
                'ua' => mb_substr((string) $request->userAgent(), 0, 80),
                'c'  => RetryingMySqlConnector::$opened,
                'cp' => RetryingMySqlConnector::$persistent,
                'r'  => RetryingMySqlConnector::$retries,
                'x'  => RetryingMySqlConnector::$refused,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            // Never let the request log break a response.
        }
    }
}
