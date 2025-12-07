<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return User::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(User::class)
            ->allowedFilters([
                'name',
                'email',
                'phone',
                'role',
                'is_active',
            ])
            ->allowedSorts([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Get all users with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $builder = $this->queryBuilder();

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
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
     * Get user by ID
     */
    public function getById(int $id): ?User
    {
        return $this->query()->find($id);
    }

    /**
     * Get user by email
     */
    public function getByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    /**
     * Get user by phone
     */
    public function getByPhone(string $phone): ?User
    {
        return $this->query()->where('phone', $phone)->first();
    }

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        return $this->query()->create($data);
    }

    /**
     * Update user
     */
    public function update(int $id, array $data): User
    {
        $user = $this->getById($id);

        if (!$user) {
            throw new \Exception("User with ID {$id} not found");
        }

        $user->update($data);

        return $user->fresh();
    }

    /**
     * Delete user
     */
    public function delete(int $id): bool
    {
        $user = $this->getById($id);

        if (!$user) {
            throw new \Exception("User with ID {$id} not found");
        }

        return $user->delete();
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('email', $email);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Check if phone exists
     */
    public function phoneExists(string $phone, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('phone', $phone);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
