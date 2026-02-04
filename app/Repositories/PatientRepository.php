<?php

namespace App\Repositories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Filters\Filter;
use Spatie\QueryBuilder\Sorts\Sort;

class PatientRepository
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
            
                  'name',
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
            'cases',
            ]);
    }

    /**
     * Get all patients with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        return $query->paginate($perPage);
    }

    /**
     * Get patient by ID
     */
    public function getById(int $id): ?Patient
    {
        $query = $this->query();
        
        return $query->find($id);
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
        $query = $this->query()->where('phone', $phone);
        
        return $query->first();
    }

    /**
     * Get patient by email
     */
    public function getByEmail(string $email): ?Patient
    {
        $query = $this->query()->where('email', $email);
        
        return $query->first();
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
