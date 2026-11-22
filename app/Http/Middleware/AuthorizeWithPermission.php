<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeWithPermission
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @param string $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // "a|b" passes with any one of them. A name this clinic's database has
        // never seeded counts as not held, instead of a 500.
        $allowed = collect(explode('|', $permission))->contains(function (string $name) {
            try {
                return Auth::user()->hasPermissionTo($name);
            } catch (PermissionDoesNotExist) {
                return false;
            }
        });

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
            ], 403);
        }

        return $next($request);
    }
}
