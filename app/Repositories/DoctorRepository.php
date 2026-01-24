<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Hash;

class DoctorRepository
{
    /**
     * Get the query builder instance for doctors only
     */
    protected function query(): Builder
    {
        return User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['doctor', 'clinic_super_doctor']);
            });
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for($this->query())
            ->allowedFilters([
                'name',
                'email',
                'phone',
                'is_active',
            ])
            ->allowedSorts([
                'id',
                'name',
                'email',
                'phone',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'clinic',
                'roles',
                'permissions',
            ]);
    }

    /**
     * Get all doctors with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15, ?int $clinicId = null): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Get doctor by ID
     */
    public function getById(int $id, ?int $clinicId = null): ?User
    {
        $query = $this->query();
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->find($id);
    }

    /**
     * Create a new doctor
     */
    public function create(array $data): User
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $doctor = User::create($data);

        // Assign doctor role if role is specified
        if (isset($data['role'])) {
            $doctor->assignRole($data['role']);
        } else {
            // Default to 'doctor' role
            $doctor->assignRole('doctor');
        }

        return $doctor->fresh();
    }

    /**
     * Update doctor
     */
    public function update(int $id, array $data): User
    {
        $doctor = $this->getById($id);

        if (!$doctor) {
            throw new \Exception("Doctor with ID {$id} not found");
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Remove password from data if not provided (to avoid updating with null/empty)
            unset($data['password']);
        }

        // Update role if provided
        if (isset($data['role'])) {
            $doctor->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $doctor->update($data);

        return $doctor->fresh();
    }

    /**
     * Delete doctor
     */
    public function delete(int $id): bool
    {
        $doctor = $this->getById($id);

        if (!$doctor) {
            throw new \Exception("Doctor with ID {$id} not found");
        }

        return $doctor->delete();
    }

    /**
     * Get doctor by email
     */
    public function getByEmail(string $email, ?int $clinicId = null): ?User
    {
        $query = $this->query()->where('email', $email);
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->first();
    }

    /**
     * Get doctor by phone
     */
    public function getByPhone(string $phone, ?int $clinicId = null): ?User
    {
        $query = $this->query()->where('phone', $phone);
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->first();
    }

    /**
     * Get doctors by clinic
     */
    public function getByClinic(int $clinicId): Collection
    {
        return $this->query()
            ->where('clinic_id', $clinicId)
            ->get();
    }

    /**
     * Get active doctors
     */
    public function getActive(?int $clinicId = null): Collection
    {
        $query = $this->query()->where('is_active', true);
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->get();
    }

    /**
     * Check if doctor exists by email
     */
    public function existsByEmail(string $email, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('email', $email);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Check if doctor exists by phone
     */
    public function existsByPhone(string $phone, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('phone', $phone);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
