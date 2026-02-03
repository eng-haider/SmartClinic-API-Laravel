<?php

namespace App\Services;

use App\Models\User;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Create a new service instance
     */
    public function __construct(
        private UserRepository $userRepository,
        private ClinicSettingService $clinicSettingService
    ) {
    }

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        // Check if email exists
        if (!empty($data['email']) && $this->userRepository->emailExists($data['email'])) {
            throw new \Exception('Email already registered');
        }

        // Check if phone exists
        if (!empty($data['phone']) && $this->userRepository->phoneExists($data['phone'])) {
            throw new \Exception('Phone already registered');
        }

        DB::beginTransaction();
        
        try {
            // Create clinic first
            $clinic = Clinic::create([
                'name' => $data['clinic_name'],
                'address' => $data['clinic_address'],
                'phone' => $data['clinic_phone'] ?? null,
                'email' => $data['clinic_email'] ?? null,
            ]);

            // Create default settings for the clinic from setting definitions
            $this->clinicSettingService->createDefaultSettingsForClinic($clinic);

            // Hash password
            $userData = [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'clinic_id' => $clinic->id,
            ];

            // Always set role to clinic_super_doctor for registration
            $roleName = 'clinic_super_doctor';

            $user = $this->userRepository->create($userData);

            // Assign role using Spatie
            $user->assignRole($roleName);

            // Refresh user to load relationships
            $user->load('roles', 'clinic');

            // Generate token
            $token = JWTAuth::fromUser($user);

            DB::commit();

            return [
                'user' => $user,
                'clinic' => $clinic,
                'token' => $token,
                'message' => 'User and clinic registered successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Smart Login: One-step authentication (discovers tenant and logs in)
     * This combines checkCredentials and login into a single call
     */
    public function smartLogin(string $phone, string $password): array
    {
        // Step 1: Authenticate in CENTRAL database and get tenant_id
        $centralConnection = config('tenancy.database.central_connection');
        $centralUser = User::on($centralConnection)->where('phone', $phone)->first();

        if (!$centralUser || !Hash::check($password, $centralUser->password)) {
            throw new \Exception('Invalid phone number or password');
        }

        if (!$centralUser->is_active) {
            throw new \Exception('User account is inactive');
        }

        // Get user's clinic
        $clinic = $centralUser->clinic;
        
        if (!$clinic) {
            throw new \Exception('User is not associated with any clinic');
        }

        // Step 2: Switch to tenant context and authenticate
        $tenant = \App\Models\Tenant::find($clinic->id);
        
        if (!$tenant) {
            throw new \Exception('Tenant/clinic not found');
        }

        // Initialize tenant context
        tenancy()->initialize($tenant);

        // Get user from tenant database
        $tenantUser = User::where('phone', $phone)->first();

        if (!$tenantUser) {
            throw new \Exception('User not found in tenant database');
        }

        if (!$tenantUser->is_active) {
            throw new \Exception('User account is inactive in tenant database');
        }

        // Generate token for tenant user
        $token = JWTAuth::fromUser($tenantUser);

        return [
            'user' => $tenantUser,
            'token' => $token,
            'tenant_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'message' => 'Login successful',
        ];
    }

    /**
     * Step 1: Check credentials and return tenant_id (no token yet)
     * Used to discover which clinic the user belongs to
     */
    public function checkCredentials(string $phone, string $password): array
    {
        // Authenticate in CENTRAL database (get connection from config)
        $centralConnection = config('tenancy.database.central_connection');
        $user = User::on($centralConnection)->where('phone', $phone)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid phone number or password');
        }

        if (!$user->is_active) {
            throw new \Exception('User account is inactive');
        }

        // Get user's clinic
        $clinic = $user->clinic;
        
        if (!$clinic) {
            throw new \Exception('User is not associated with any clinic');
        }

        // Return tenant_id for the frontend to use in step 2
        return [
            'tenant_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'user_name' => $user->name,
            'message' => 'Credentials verified. Please proceed with tenant login.',
        ];
    }

    /**
     * Step 2: Login user with tenant context (requires X-Tenant-ID)
     * This is called after checkCredentials with the tenant_id
     */
    public function login(string $phone, string $password): array
    {
        $user = $this->userRepository->getByPhone($phone);

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid phone number or password');
        }

        if (!$user->is_active) {
            throw new \Exception('User account is inactive');
        }

        // Generate token
        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful',
        ];
    }

    /**
     * Logout user (invalidate token)
     */
    public function logout(): array
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ['message' => 'Logout successful'];
        } catch (\Exception $e) {
            throw new \Exception('Logout failed: ' . $e->getMessage());
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(): array
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return [
                'token' => $token,
                'message' => 'Token refreshed successfully',
            ];
        } catch (\Exception $e) {
            throw new \Exception('Token refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Get authenticated user
     */
    public function me(): ?User
    {
        return JWTAuth::user();
    }

    /**
     * Get all users
     */
    public function getAllUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get user by ID
     */
    public function getUser(int $id): ?User
    {
        return $this->userRepository->getById($id);
    }

    /**
     * Update user
     */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->getUser($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        // Check if email is being changed
        if (!empty($data['email']) && $data['email'] !== $user->email) {
            if ($this->userRepository->emailExists($data['email'], $id)) {
                throw new \Exception('Email already in use');
            }
        }

        // Check if phone is being changed
        if (!empty($data['phone']) && $data['phone'] !== $user->phone) {
            if ($this->userRepository->phoneExists($data['phone'], $id)) {
                throw new \Exception('Phone already in use');
            }
        }

        // Hash password if provided
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $this->userRepository->update($id, $data);
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->getUser($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $this->userRepository->delete($id);
    }

    /**
     * Change password
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        $user = $this->getUser($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if (!Hash::check($oldPassword, $user->password)) {
            throw new \Exception('Current password is incorrect');
        }

        $this->userRepository->update($userId, [
            'password' => Hash::make($newPassword),
        ]);

        return ['message' => 'Password changed successfully'];
    }
}
