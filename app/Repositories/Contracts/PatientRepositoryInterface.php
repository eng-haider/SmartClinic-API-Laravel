<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Patient;

interface PatientRepositoryInterface
{
    /**
     * Get all patients with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get patient by ID
     */
    public function getById(int $id): ?Patient;

    /**
     * Create a new patient
     */
    public function create(array $data): Patient;

    /**
     * Update patient
     */
    public function update(int $id, array $data): Patient;

    /**
     * Delete patient
     */
    public function delete(int $id): bool;

    /**
     * Get patient by phone
     */
    public function getByPhone(string $phone): ?Patient;

    /**
     * Get patient by email
     */
    public function getByEmail(string $email): ?Patient;

    /**
     * Check if patient exists by phone
     */
    public function existsByPhone(string $phone, ?int $exceptId = null): bool;

    /**
     * Check if patient exists by email
     */
    public function existsByEmail(string $email, ?int $exceptId = null): bool;
}
