<?php

namespace App\Repositories;

use App\Models\Bill;
use App\Models\CaseModel;
use App\Models\ClinicExpense;
use Carbon\CarbonImmutable;
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
        // Strip nested billable includes (billable.* ) from the request — morphWith() handles them automatically
        $includes = request('include', '');
        if (is_string($includes) && str_contains($includes, 'billable.')) {
            $filtered = collect(explode(',', $includes))
                ->map(fn($i) => trim($i))
                ->filter(fn($i) => !str_starts_with($i, 'billable.'))
                ->implode(',');
            request()->merge(['include' => $filtered]);
        }

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
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $filters = request('filter');
                    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
                        $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
                    }
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    // Handled by date_from callback
                }),
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
                'notes',
            ])


            ->defaultSort('-created_at');

        return $query;
    }

    /**
     * Get all bills with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15, ?int $doctorId = null): LengthAwarePaginator
    {
        $query = $this->queryBuilder();

        // Filter by doctor if provided
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get bill by ID
     */
    public function getById(int $id): ?Bill
    {
        $query = $this->query()->with(['patient', 'doctor', 'billable']);

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
    public function getByPatient(int $patientId, int $perPage = 15, ?int $doctorId = null): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'billable.patient', 'billable.doctor', 'billable.category', 'billable.status'])
            ->byPatient($patientId);

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

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
    public function getStatisticsWithFilters(array $filters, int $perPage = 15, $doctorId = null): array
    {
        // total_price        = sum of case prices (cases table) in the range.
        // total_paid_price   = paid CASE bills within the requested date range (money collected).
        // total_unpaid_price = remaining on the cases in range: per case, price minus every
        //                      paid bill recorded for it (any date), clamped at zero. This is the
        //                      same math as bills/patient-balances, so the card matches that list.
        //                      It is NOT total_price - total_paid_price: a payment collected in
        //                      the period may belong to a case created outside it.
        $caseBillableTypes = ['Case', 'CaseModel', 'App\\Models\\Case', 'App\\Models\\CaseModel'];

        // A plain Y-m-d date_to covers the whole end day on datetime columns (as patient-balances
        // does); other callers may pass a full datetime, which is used as an inclusive bound.
        $from = ! empty($filters['date_from']) ? $filters['date_from'] : null;
        $to = ! empty($filters['date_to']) ? $filters['date_to'] : null;
        $toExclusive = $to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
            ? CarbonImmutable::parse($to)->addDay()->startOfDay()
            : null;
        $applyCreatedRange = function ($query, string $column) use ($from, $to, $toExclusive) {
            if ($from !== null) {
                $query->where($column, '>=', $from);
            }
            if ($toExclusive !== null) {
                $query->where($column, '<', $toExclusive);
            } elseif ($to !== null) {
                $query->where($column, '<=', $to);
            }
        };

        $casesQuery = CaseModel::query();

        if ($doctorId !== null) {
            $casesQuery->where('cases.doctor_id', $doctorId);
        }
        $applyCreatedRange($casesQuery, 'cases.created_at');

        $totalPrice = (clone $casesQuery)->sum('price') ?? 0; // Total of all case prices in range

        // total_paid_price = paid CASE bills within the requested date range (money collected
        // in that period). The bills table is polymorphic, so restrict to case billable types
        // (the morph map is non-enforcing; the DB trigger stores 'App\Models\Case').
        $paidCaseBillsQuery = Bill::query()
            ->where('is_paid', true)
            ->whereIn('billable_type', $caseBillableTypes);

        if ($doctorId !== null) {
            $paidCaseBillsQuery->where('doctor_id', $doctorId);
        }
        $applyCreatedRange($paidCaseBillsQuery, 'bills.created_at');

        $totalPaidPrice = $paidCaseBillsQuery->sum('price') ?? 0;

        // Remaining on the cases in range (lifetime payments per case, clamped per case so an
        // overpaid case never settles another one).
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) AS paid_amount')
            ->whereIn('billable_type', $caseBillableTypes)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        $totalUnpaidPrice = (int) (clone $casesQuery)->toBase()
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'cases.id')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(cases.price, 0) > COALESCE(payments.paid_amount, 0)
                THEN COALESCE(cases.price, 0) - COALESCE(payments.paid_amount, 0) ELSE 0 END), 0) AS remaining')
            ->value('remaining');

        // Get expenses with same date filter
        $expensesQuery = ClinicExpense::query();

        if ($doctorId !== null) {
            $expensesQuery->where('doctor_id', $doctorId);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $expensesQuery->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $expensesQuery->where('date', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $expensesQuery->where('date', '<=', $filters['date_to']);
        }

        $totalExpenses = $expensesQuery->sum(DB::raw('price * COALESCE(quantity, 1)')) ?? 0;
        // $totalPaidExpenses = (clone $expensesQuery)->where('is_paid', true)->sum(DB::raw('price * COALESCE(quantity, 1)')) ?? 0;
        // $totalUnpaidExpenses = (clone $expensesQuery)->where('is_paid', false)->sum(DB::raw('price * COALESCE(quantity, 1)')) ?? 0;

        return [
            'total_price' => $totalPrice, // Total of ALL case prices in range
            'total_paid_price' => $totalPaidPrice, // Paid case bills (money collected toward cases)
            'total_unpaid_price' => $totalUnpaidPrice, // Remaining on cases in range (matches patient-balances)
            'total_expenses' => $totalExpenses,
            // 'total_paid_expenses' => $totalPaidExpenses,
            // 'total_unpaid_expenses' => $totalUnpaidExpenses,
        ];
    }
}