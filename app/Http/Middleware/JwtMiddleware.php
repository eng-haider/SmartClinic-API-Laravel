<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JwtAuth\Exceptions\JwtException;
use Tymon\JwtAuth\Facades\JwtAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Check if token exists in Authorization header
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization header missing',
                ], 401);
            }

            // Parse and validate token
            JwtAuth::parseToken()->authenticate();
        } catch (JwtException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid or expired',
                'error' => $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 401);
        }

        return $next($request);
    }
}
