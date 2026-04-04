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
                'bills',
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
     * Get expense by ID — respects ?include= query parameter
     */
    public function getById(int $id): ?ClinicExpense
    {
        return $this->queryBuilder()
            ->with(['category', 'doctor', 'creator', 'updator'])
            ->find($id);
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
     * Mark all unpaid expenses within a date range as paid.
     * Creates a covering Bill for each expense that still has a remaining balance.
     * Returns counts of processed and skipped expenses.
     */
    public function bulkMarkAsPaidByDateRange(string $startDate, string $endDate, ?int $categoryId = null): array
    {
        $expenses = $this->query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_paid', false)
            ->when($categoryId, fn($q) => $q->where('clinic_expense_category_id', $categoryId))
            ->get();

        $marked  = 0;
        $skipped = 0;

        foreach ($expenses as $expense) {
            $alreadyPaid = (int) $expense->bills()->sum('price');
            $total       = (int) round(($expense->quantity ?? 1) * $expense->price);
            $remaining   = $total - $alreadyPaid;

            if ($remaining > 0) {
                \App\Models\Bill::create([
                    'billable_id'   => $expense->id,
                    'billable_type' => \App\Models\ClinicExpense::class,
                    'price'         => $remaining,
                    'doctor_id'     => $expense->doctor_id,
                    'bill_date'     => now(),
                ]);
                // is_paid synced automatically via Bill::saved() → syncIsPaid()
                $marked++;
            } else {
                $expense->updateQuietly(['is_paid' => true]);
                $skipped++;
            }
        }

        return [
            'marked'  => $marked,
            'skipped' => $skipped,
            'total'   => $expenses->count(),
        ];
    }

    /**
     * Get total expenses
     */
    public function getTotal(?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        
        return $query->sum(DB::raw('price * COALESCE(quantity, 1)'));
    }

    /**
     * Get unpaid expenses
     */
    public function getUnpaid(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('is_paid', false)
            ->with(['category', 'doctor'])
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Mark expense as paid — creates a covering Bill for any remaining balance.
     */
    public function markAsPaid(int $id): ClinicExpense
    {
        $expense = $this->query()->findOrFail($id);

        $alreadyPaid = (int) $expense->bills()->sum('price');
        $total       = (int) round(($expense->quantity ?? 1) * $expense->price);
        $remaining   = $total - $alreadyPaid;

        if ($remaining > 0) {
            \App\Models\Bill::create([
                'billable_id'   => $expense->id,
                'billable_type' => \App\Models\ClinicExpense::class,
                'price'         => $remaining,
                'doctor_id'     => $expense->doctor_id,
                'bill_date'     => now(),
            ]);
            // is_paid is synced automatically via Bill::saved() → syncIsPaid()
        } else {
            $expense->update(['is_paid' => true]);
        }

        return $expense->fresh();
    }

    /**
     * Mark expense as unpaid — removes all bill instalments.
     */
    public function markAsUnpaid(int $id): ClinicExpense
    {
        $expense = $this->query()->findOrFail($id);

        // Delete all linked bill instalments; Bill::deleted() will call syncIsPaid()
        $expense->bills()->delete();

        return $expense->fresh();
    }

    /**
     * Get expense statistics
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = $this->query();
        
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
