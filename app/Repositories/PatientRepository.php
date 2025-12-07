<?php

namespace App\Repositories;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Filters\Filter;
use Spatie\QueryBuilder\Sorts\Sort;

class PatientRepository implements PatientRepositoryInterface
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Patient::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters([
                'first_name',
                'last_name',
                'email',
                'phone',
                'gender',
                'blood_type',
                'city',
                'state',
                'country',
                'is_active',
            ])
            ->allowedSorts([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                // Add relations here if needed
            ]);
    }

    /**
     * Get all patients with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $builder = $this->queryBuilder();

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply date range filters
        if (!empty($filters['from_date'])) {
            $builder->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $builder->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $builder->paginate($perPage);
    }

    /**
     * Get patient by ID
     */
    public function getById(int $id): ?Patient
    {
        return $this->query()->find($id);
    }

    /**
     * Create a new patient
     */
    public function create(array $data): Patient
    {
        return $this->query()->create($data);
    }

    /**
     * Update patient
     */
    public function update(int $id, array $data): Patient
    {
        $patient = $this->getById($id);

        if (!$patient) {
            throw new \Exception("Patient with ID {$id} not found");
        }

        $patient->update($data);

        return $patient->fresh();
    }

    /**
     * Delete patient
     */
    public function delete(int $id): bool
    {
        $patient = $this->getById($id);

        if (!$patient) {
            throw new \Exception("Patient with ID {$id} not found");
        }

        return $patient->delete();
    }

    /**
     * Get patient by phone
     */
    public function getByPhone(string $phone): ?Patient
    {
        return $this->query()->where('phone', $phone)->first();
    }

    /**
     * Get patient by email
     */
    public function getByEmail(string $email): ?Patient
    {
        return $this->query()->where('email', $email)->first();
    }

    /**
     * Check if patient exists by phone
     */
    public function existsByPhone(string $phone, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('phone', $phone);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Check if patient exists by email
     */
    public function existsByEmail(string $email, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('email', $email);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
