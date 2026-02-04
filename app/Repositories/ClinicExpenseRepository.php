<?php

namespace App\Repositories;

use App\Models\ClinicExpense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ClinicExpenseRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return ClinicExpense::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(ClinicExpense::class)
            ->allowedFilters([
                'name',
                'clinic_id',
                'clinic_expense_category_id',
                'is_paid',
                'doctor_id',
                AllowedFilter::scope('date_from', 'dateFrom'),
                AllowedFilter::scope('date_to', 'dateTo'),
            ])
            ->allowedSorts([
                'id',
                'name',
                'date',
                'price',
                'quantity',
                'is_paid',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'category',
                'doctor',
                'creator',
                'updator',
            ])
            ->defaultSort('-date');
    }

    /**
     * Get all expenses with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        return $query->paginate($perPage);
    }

    /**
     * Get expense by ID
     */
    public function getById(int $id): ?ClinicExpense
    {
        $query = $this->query()->with(['category', 'doctor', 'creator', 'updator']);
        
        return $query->find($id);
    }

    /**
     * Create a new expense
     */
    public function create(array $data): ClinicExpense
    {
        return $this->query()->create($data);
    }

    /**
     * Update an expense
     */
    public function update(int $id, array $data): ClinicExpense
    {
        $expense = $this->query()->findOrFail($id);
        $expense->update($data);
        
        return $expense->fresh(['category', 'doctor', 'creator', 'updator']);
    }

    /**
     * Delete an expense (soft delete)
     */
    public function delete(int $id): bool
    {
        $expense = $this->query()->findOrFail($id);
        
        return $expense->delete();
    }

    

    /**
     * Get expenses by category
     */
    public function getByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('clinic_expense_category_id', $categoryId)
            ->with(['doctor'])
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    /**
     * Get expenses by date range
     */
    public function getByDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->query()
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['category', 'doctor']);
        
        return $query->orderByDesc('date')->get();
    }

    /**
     * Get total expenses for a clinic
     */
    public function getTotalByClinic( ?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->query()->where('clinic_id');
        
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        
        return $query->sum(DB::raw('price * COALESCE(quantity, 1)'));
    }

    /**
     * Get unpaid expenses for a clinic
     */
    public function getUnpaidByClinic(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('is_paid', false)
            ->with(['category', 'doctor'])
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Mark expense as paid
     */
    public function markAsPaid(int $id): ClinicExpense
    {
        $expense = $this->query()->findOrFail($id);
        $expense->update(['is_paid' => true]);
        
        return $expense->fresh();
    }

    /**
     * Mark expense as unpaid
     */
    public function markAsUnpaid(int $id): ClinicExpense
    {
        $expense = $this->query()->findOrFail($id);
        $expense->update(['is_paid' => false]);
        
        return $expense->fresh();
    }

    /**
     * Get expense statistics for a clinic
     */
    public function getStatistics( ?string $startDate = null, ?string $endDate = null): array
    {
        $query = $this->query()->where('clinic_id');
        
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        
        $totalExpenses = (clone $query)->sum(DB::raw('price * COALESCE(quantity, 1)'));
        $paidExpenses = (clone $query)->where('is_paid', true)->sum(DB::raw('price * COALESCE(quantity, 1)'));
        $unpaidExpenses = (clone $query)->where('is_paid', false)->sum(DB::raw('price * COALESCE(quantity, 1)'));
        $totalCount = (clone $query)->count();
        
        return [
            'total_expenses' => $totalExpenses,
            'paid_expenses' => $paidExpenses,
            'unpaid_expenses' => $unpaidExpenses,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Get summary statistics for filtered expenses
     */
    public function getFilteredSummary(array $filters): array
    {
        $query = $this->queryBuilder();
        
        // Clone the query to get different aggregations
        $baseQuery = clone $query;
        
        $totalExpensesAmount = (clone $baseQuery)->sum(DB::raw('price * COALESCE(quantity, 1)'));
        $totalPaidAmount = (clone $baseQuery)->where('is_paid', true)->sum(DB::raw('price * COALESCE(quantity, 1)'));
        $totalUnpaidAmount = (clone $baseQuery)->where('is_paid', false)->sum(DB::raw('price * COALESCE(quantity, 1)'));
        $expensesCount = (clone $baseQuery)->count();
        $paidExpensesCount = (clone $baseQuery)->where('is_paid', true)->count();
        $unpaidExpensesCount = (clone $baseQuery)->where('is_paid', false)->count();
        
        return [
            'expenses_count' => $expensesCount,
            'paid_expenses_count' => $paidExpensesCount,
            'unpaid_expenses_count' => $unpaidExpensesCount,
            'total_expenses_amount' => (float) ($totalExpensesAmount ?? 0),
            'total_paid_amount' => (float) ($totalPaidAmount ?? 0),
            'total_unpaid_amount' => (float) ($totalUnpaidAmount ?? 0),
        ];
    }
}
