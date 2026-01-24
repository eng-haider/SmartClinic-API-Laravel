<?php

namespace App\Repositories;

use App\Models\ClinicExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class ClinicExpenseCategoryRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return ClinicExpenseCategory::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(ClinicExpenseCategory::class)
            ->allowedFilters([
                'name',
                'clinic_id',
                'is_active',
            ])
            ->allowedSorts([
                'id',
                'name',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'clinic',
                'expenses',
                'creator',
                'updator',
            ])
            ->defaultSort('-created_at');
    }

    /**
     * Get all expense categories with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15, ?int $clinicId = null): LengthAwarePaginator
    {
        $query = $this->queryBuilder()
            ->withExpenseTotals(); // Add expense totals
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Get expense category by ID
     */
    public function getById(int $id, ?int $clinicId = null): ?ClinicExpenseCategory
    {
        $query = $this->query()
            ->withExpenseTotals(); // Add expense totals
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->find($id);
    }

    /**
     * Create a new expense category
     */
    public function create(array $data): ClinicExpenseCategory
    {
        return $this->query()->create($data);
    }

    /**
     * Update an expense category
     */
    public function update(int $id, array $data): ClinicExpenseCategory
    {
        $category = $this->query()->findOrFail($id);
        $category->update($data);
        
        return $category->fresh();
    }

    /**
     * Delete an expense category (soft delete)
     */
    public function delete(int $id): bool
    {
        $category = $this->query()->findOrFail($id);
        
        return $category->delete();
    }

    /**
     * Get active categories for a clinic
     */
    public function getActiveByClinic(int $clinicId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->withExpenseTotals() // Add expense totals
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if category exists for clinic
     */
    public function existsForClinic(int $id, int $clinicId): bool
    {
        return $this->query()
            ->where('id', $id)
            ->where('clinic_id', $clinicId)
            ->exists();
    }
}
