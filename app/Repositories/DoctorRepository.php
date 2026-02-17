<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                'roles',
                'permissions',
            ]);
    }

    /**
     * Get all doctors with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();

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
    public function getById(int $id): ?User
    {
        $query = $this->query();
        
        return $query->find($id);
    }

    /**
     * Create a new doctor
     */
    public function create(array $data): User
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $hashedPassword = Hash::make($data['password']);
            $data['password'] = $hashedPassword;
        }

        // Remove clinic_id from data for tenant databases (they don't have this column)
        // clinic_id is only used in central database
        $userData = collect($data)->except(['clinic_id'])->toArray();

        // Create user in tenant database
        $doctor = User::create($userData);

        // Assign doctor role if role is specified
        if (isset($data['role'])) {
            $doctor->assignRole($data['role']);
        } else {
            // Default to 'doctor' role
            $doctor->assignRole('doctor');
        }

        // Also create user in central database for smart login (if in tenant context)
        try {
            $authUser = Auth::user();
            if ($authUser && $authUser->clinic_id) {
                $centralConnection = config('tenancy.database.central_connection');
                
                // Check if user already exists in central database
                $existingCentralUser = User::on($centralConnection)
                    ->where('phone', $doctor->phone)
                    ->first();
                
                if (!$existingCentralUser) {
                    // Create user in central database for smart login
                    $centralUser = User::on($centralConnection)->create([
                        'name' => $doctor->name,
                        'phone' => $doctor->phone,
                        'email' => $doctor->email ?? null,
                        'password' => $doctor->password, // Already hashed
                        'is_active' => $doctor->is_active,
                    ]);
                    
                    // Set clinic_id separately
                    $centralUser->clinic_id = $authUser->clinic_id;
                    $centralUser->save();
                    
                    Log::info('Doctor created in central database for smart login', [
                        'tenant_user_id' => $doctor->id,
                        'central_user_id' => $centralUser->id,
                        'phone' => $doctor->phone,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail the operation if central DB creation fails
            Log::warning('Failed to create doctor in central database', [
                'phone' => $doctor->phone,
                'error' => $e->getMessage(),
            ]);
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
    public function getByEmail(string $email): ?User
    {
        $query = $this->query()->where('email', $email);
        
        return $query->first();
    }

    /**
     * Get doctor by phone
     */
    public function getByPhone(string $phone): ?User
    {
        $query = $this->query()->where('phone', $phone);
        
        return $query->first();
    }

    

    /**
     * Get active doctors
     */
    public function getActive(): Collection
    {
        $query = $this->query()->where('is_active', true);
        
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
