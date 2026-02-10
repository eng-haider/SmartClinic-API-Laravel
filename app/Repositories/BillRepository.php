<?php

namespace App\Repositories;

use App\Models\Bill;
use App\Models\CaseModel;
use App\Models\ClinicExpense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class BillRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Bill::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        $query = QueryBuilder::for(Bill::class)
            ->allowedFilters([
                'patient_id',
                'doctor_id',
                'clinics_id',
                'is_paid',
                'use_credit',
                'billable_type',
                AllowedFilter::exact('price'),
                AllowedFilter::scope('paid'),
                AllowedFilter::scope('unpaid'),
                AllowedFilter::scope('by_patient', 'byPatient'),
                AllowedFilter::scope('by_doctor', 'byDoctor'),
            ])
            ->allowedSorts([
                'id',
                'price',
                'is_paid',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'patient',
                'doctor',
                'creator',
                'updator',
                'billable',
                'billable.patient',
                'billable.doctor',
                'billable.category',
                'billable.status',
                'notes',
            ])
            ->defaultSort('-created_at');


        // If billable is requested, automatically load nested relationships
        $includes = request()->get('include', []);
        if (is_string($includes)) {
            $includes = explode(',', $includes);
        }
        if (in_array('billable', $includes)) {
            $query->with([
                'billable.patient',
                'billable.doctor',
                'billable.category',
                'billable.status'
            ]);
        }

        return $query;
    }

    /**
     * Get all bills with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();

        return $query->paginate($perPage);
    }

    /**
     * Get bill by ID
     */
    public function getById(int $id): ?Bill
    {
        $query = $this->query()->with(['patient', 'doctor', 'billable.patient', 'billable.doctor', 'billable.category', 'billable.status']);

        return $query->find($id);
    }

    /**
     * Create a new bill
     */
    public function create(array $data): Bill
    {
        return $this->query()->create($data);
    }

    /**
     * Update bill
     */
    public function update(int $id, array $data): Bill
    {
        $bill = $this->getById($id);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        $bill->update($data);

        return $bill->fresh(['patient', 'doctor', 'billable']);
    }

    /**
     * Delete bill
     */
    public function delete(int $id): bool
    {
        $bill = $this->getById($id);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        return $bill->delete();
    }

    /**
     * Mark bill as paid
     */
    public function markAsPaid(int $id): Bill
    {
        $bill = $this->getById($id);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        $bill->markAsPaid();

        return $bill->fresh(['patient', 'doctor', 'billable']);
    }

    /**
     * Mark bill as unpaid
     */
    public function markAsUnpaid(int $id): Bill
    {
        $bill = $this->getById($id);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        $bill->markAsUnpaid();

        return $bill->fresh(['patient', 'doctor', 'billable']);
    }

    /**
     * Get bills by patient
     */
    public function getByPatient(int $patientId, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'billable.patient', 'billable.doctor', 'billable.category', 'billable.status'])
            ->byPatient($patientId);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get bills by doctor
     */
    public function getByDoctor(int $doctorId, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'billable.patient', 'billable.doctor', 'billable.category', 'billable.status'])
            ->byDoctor($doctorId);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get total revenue for a clinic
     */
    public function getTotalRevenue(): int
    {
        $query = $this->query()->paid();

        return $query->sum('price');
    }

    /**
     * Get total outstanding for a clinic
     */
    public function getTotalOutstanding(): int
    {
        $query = $this->query()->unpaid();

        return $query->sum('price');
    }

    /**
     * Get bill statistics
     */
    public function getStatistics(): array
    {
        $query = $this->query();

        $totalBills = $query->count();
        $paidBills = (clone $query)->paid()->count();
        $unpaidBills = (clone $query)->unpaid()->count();
        $totalPrice = (clone $query)->sum('price') ?? 0;
        $totalPaidPrice = (clone $query)->paid()->sum('price') ?? 0;
        $totalUnpaidPrice = (clone $query)->unpaid()->sum('price') ?? 0;

        return [
            'total_bills' => $totalBills,
            'paid_bills' => $paidBills,
            'unpaid_bills' => $unpaidBills,
            'total_price' => $totalPrice, // Total price of all bills (paid + unpaid)
            'total_paid_price' => $totalPaidPrice,
            'total_unpaid_price' => $totalUnpaidPrice,
            'remaining_amount' => $totalUnpaidPrice, // Remaining to be paid
            'total_revenue' => $totalUnpaidPrice, // Unpaid cases price (total_price - total_paid_price)
            'total_outstanding' => $totalUnpaidPrice, // Alias for backward compatibility
        ];
    }

    /**
     * Get bill statistics with optional filters (date range, doctor)
     *
     * Filters supported:
     * - date_from (Y-m-d or Y-m-d H:i:s)
     * - date_to (Y-m-d or Y-m-d H:i:s)
     * - doctor_id
     */
    public function getStatisticsWithFilters(array $filters = []): array
    {
        $query = $this->query();

        if (!empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $totalBills = $query->count();
        $paidBills = (clone $query)->paid()->count();
        $unpaidBills = (clone $query)->unpaid()->count();
        $totalPaidPrice = (clone $query)->paid()->sum('price') ?? 0;
        $totalUnpaidPrice = (clone $query)->unpaid()->sum('price') ?? 0;

        // Get cases with same date filter to calculate total case prices
        $casesQuery = CaseModel::query();
        
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $casesQuery->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $casesQuery->where('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $casesQuery->where('created_at', '<=', $filters['date_to']);
        }

        $totalPrice = $casesQuery->sum('price') ?? 0; // Total of all cases price
        $totalPaidCases = (clone $casesQuery)->where('is_paid', true)->sum('price') ?? 0;

        // Get expenses with same date filter
        $expensesQuery = ClinicExpense::query();
        
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $expensesQuery->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $expensesQuery->where('date', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $expensesQuery->where('date', '<=', $filters['date_to']);
        }

        $totalExpenses = $expensesQuery->sum(DB::raw('price * COALESCE(quantity, 1)')) ?? 0;
        $totalPaidExpenses = (clone $expensesQuery)->where('is_paid', true)->sum(DB::raw('price * COALESCE(quantity, 1)')) ?? 0;
        $totalUnpaidExpenses = (clone $expensesQuery)->where('is_paid', false)->sum(DB::raw('price * COALESCE(quantity, 1)')) ?? 0;

        // Calculate unpaid case price (total_price - total_paid_price)
        $unpaidCasePrice = $totalPrice - $totalPaidCases;

        return [
            'total_bills' => $totalBills,
            'paid_bills' => $paidBills,
            'unpaid_bills' => $unpaidBills,
            'total_price' => $totalPrice, // Total of ALL cases price (not bills)
            'total_paid_price' => $totalPaidCases, // Total paid cases price
            'total_unpaid_price' => $totalUnpaidPrice,
            'unpaid_case_price' => $unpaidCasePrice, // total_price - total_paid_price
            'remaining_amount' => $totalUnpaidPrice, // Remaining to be paid
            'total_revenue' => $totalUnpaidPrice, // Unpaid cases price (total_price - total_paid_price)
            'total_outstanding' => $totalUnpaidPrice, // Alias for backward compatibility
            'total_expenses' => $totalExpenses, // Total expenses (paid + unpaid)
            'total_paid_expenses' => $totalPaidExpenses,
            'total_unpaid_expenses' => $totalUnpaidExpenses,
        ];
    }
}