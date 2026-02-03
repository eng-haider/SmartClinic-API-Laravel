<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private AuthService $authService)
    {
    }

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => new UserResource($result['user']),
                    'clinic' => [
                        'id' => $result['clinic']->id,
                        'name' => $result['clinic']->name,
                        'address' => $result['clinic']->address,
                        'phone' => $result['clinic']->phone,
                        'email' => $result['clinic']->email,
                    ],
                    'token' => $result['token'],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Step 1: Check credentials and return tenant_id
     * POST /api/auth/check-credentials
     */
    public function checkCredentials(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->checkCredentials(
                $request->validated('phone'),
                $request->validated('password')
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'message_ar' => 'تم التحقق من بيانات الدخول. يرجى المتابعة.',
                'data' => [
                    'tenant_id' => $result['tenant_id'],
                    'clinic_name' => $result['clinic_name'],
                    'user_name' => $result['user_name'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'message_ar' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }
    }

    /**
     * Step 2: Login user with tenant context
     * POST /api/tenant/auth/login (requires X-Tenant-ID header)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('phone'),
                $request->validated('password')
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => new UserResource($result['user']),
                    'token' => $result['token'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Smart Login: One-step authentication
     * Automatically discovers tenant and logs in
     * POST /api/auth/smart-login
     */
    public function smartLogin(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->smartLogin(
                $request->validated('phone'),
                $request->validated('password')
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'message_ar' => 'تم تسجيل الدخول بنجاح',
                'data' => [
                    'user' => new UserResource($result['user']),
                    'token' => $result['token'],
                    'tenant_id' => $result['tenant_id'],
                    'clinic_name' => $result['clinic_name'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'message_ar' => 'فشل تسجيل الدخول: بيانات الدخول غير صحيحة',
            ], 401);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(): JsonResponse
    {
        try {
            $user = $this->authService->me();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Logout user
     */
    public function logout(): JsonResponse
    {
        try {
            $result = $this->authService->logout();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Refresh JWT token
     */
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'token' => $result['token'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string|min:8',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = $this->authService->me();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $result = $this->authService->changePassword(
                $user->id,
                $validated['current_password'],
                $validated['new_password']
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
