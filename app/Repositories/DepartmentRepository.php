<?php

namespace App\Repositories;

use App\Models\CaseCategory;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DepartmentRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Department::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Department::class)
            ->allowedFilters([
                'name',
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts([
                'id',
                'name',
                'created_at',
            ])
            ->withCount('caseCategories')
            ->defaultSort('id');
    }

    /**
     * Get all departments with filters and pagination
     */
    public function getAllWithFilters(int $perPage = 100): LengthAwarePaginator
    {
        return $this->queryBuilder()->paginate($perPage);
    }

    /**
     * Get department by ID
     */
    public function getById(int $id): ?Department
    {
        return $this->query()->find($id);
    }

    /**
     * Create a new department
     */
    public function create(array $data): Department
    {
        return $this->query()->create($data);
    }

    /**
     * Update department
     */
    public function update(int $id, array $data): Department
    {
        $department = $this->getById($id);

        if (!$department) {
            throw new \Exception("Department with ID {$id} not found");
        }

        $department->update($data);

        return $department->fresh();
    }

    /**
     * Delete department. Its categories stay, just without a department
     * (soft deletes never fire the foreign key's ON DELETE SET NULL).
     */
    public function delete(int $id): bool
    {
        $department = $this->getById($id);

        if (!$department) {
            throw new \Exception("Department with ID {$id} not found");
        }

        return DB::transaction(function () use ($department) {
            CaseCategory::withTrashed()
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);

            return $department->delete();
        });
    }
}
