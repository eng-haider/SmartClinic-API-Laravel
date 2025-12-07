<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tymon\JwtAuth\Facades\JwtAuth;

class AuthService
{
    /**
     * Create a new service instance
     */
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        // Check if email exists
        if ($this->userRepository->emailExists($data['email'])) {
            throw new \Exception('Email already registered');
        }

        // Check if phone exists
        if (!empty($data['phone']) && $this->userRepository->phoneExists($data['phone'])) {
            throw new \Exception('Phone already registered');
        }

        // Hash password
        $data['password'] = Hash::make($data['password']);

        // Set default role if not provided
        if (empty($data['role'])) {
            $data['role'] = 'user';
        }

        $user = $this->userRepository->create($data);

        // Generate token
        $token = JwtAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'message' => 'User registered successfully',
        ];
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
        $token = JwtAuth::fromUser($user);

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
            JwtAuth::invalidate(JwtAuth::getToken());
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
            $token = JwtAuth::refresh(JwtAuth::getToken());
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
        return JwtAuth::user();
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
