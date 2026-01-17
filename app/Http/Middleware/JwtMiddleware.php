<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization header missing',
                ], 401);
            }

            JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {

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
