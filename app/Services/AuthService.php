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
    public function __construct(private UserRepository $userRepository)
    {
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

            // Create clinic settings
            ClinicSetting::create([
                'clinic_id' => $clinic->id,
                'currency' => 'USD',
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
            ]);

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
     * Login user
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
