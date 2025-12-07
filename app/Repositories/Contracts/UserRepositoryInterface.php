<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * Get all users with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get user by ID
     */
    public function getById(int $id): ?User;

    /**
     * Get user by email
     */
    public function getByEmail(string $email): ?User;

    /**
     * Get user by phone
     */
    public function getByPhone(string $phone): ?User;

    /**
     * Create a new user
     */
    public function create(array $data): User;

    /**
     * Update user
     */
    public function update(int $id, array $data): User;

    /**
     * Delete user
     */
    public function delete(int $id): bool;

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $exceptId = null): bool;

    /**
     * Check if phone exists
     */
    public function phoneExists(string $phone, ?int $exceptId = null): bool;
}
