<?php

namespace App\Repositories;

use App\Models\CaseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class CaseCategoryRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return CaseCategory::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(CaseCategory::class)
            ->allowedFilters([
                'name',
                'clinic_id',
            ])
            ->allowedSorts([
                'id',
                'name',
                'order',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'cases',
            ])
            ->defaultSort('order');
    }

    /**
     * Get all case categories with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        return $query->paginate($perPage);
    }

    /**
     * Get case category by ID
     */
    public function getById(int $id): ?CaseCategory
    {
        $query = $this->query();
        
        return $query->find($id);
    }

    /**
     * Create a new case category
     */
    public function create(array $data): CaseCategory
    {
        return $this->query()->create($data);
    }

    /**
     * Update case category
     */
    public function update(int $id, array $data): CaseCategory
    {
        $category = $this->getById($id);

        if (!$category) {
            throw new \Exception("Case category with ID {$id} not found");
        }

        $category->update($data);

        return $category->fresh();
    }

    /**
     * Delete case category
     */
    public function delete(int $id): bool
    {
        $category = $this->getById($id);

        if (!$category) {
            throw new \Exception("Case category with ID {$id} not found");
        }

        return $category->delete();
    }
}
