<?php

namespace App\Repositories;

use App\Models\Bill;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class BillingOverviewRepository
{
    private const CASE_TYPES = ['App\\Models\\Case', 'App\\Models\\CaseModel', 'Case', 'CaseModel'];

    /**
     * Lifetime balances: aggregate payments before joining cases so each case
     * contributes its price exactly once, including cases with no bills yet.
     * All queries use the current (tenant) database connection.
     */
    private function balanceQuery(array $filters): QueryBuilder
    {
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) AS paid_amount, MAX(created_at) AS last_payment_at')
            ->whereIn('billable_type', self::CASE_TYPES)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        // Keep lifetime payments intact; aggregate matching payments separately.
        $conditions = [];
        $bindings = [];
        if (! empty($filters['date_from'])) {
            $conditions[] = 'created_at >= ?';
            $bindings[] = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        }
        if (! empty($filters['date_to'])) {
            $conditions[] = 'created_at < ?';
            $bindings[] = CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay();
        }
        $period = $conditions ? implode(' AND ', $conditions) : '1 = 1';
        $paymentsByCase
            ->selectRaw("SUM(CASE WHEN {$period} THEN price ELSE 0 END) AS period_paid_amount", $bindings)
            ->selectRaw("SUM(CASE WHEN {$period} THEN 1 ELSE 0 END) AS period_payment_count", $bindings)
            ->selectRaw("MAX(CASE WHEN {$period} THEN created_at ELSE NULL END) AS period_last_payment_at", $bindings);

        $caseBalances = DB::table('cases')
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'cases.id')
            ->whereNull('cases.deleted_at')
            ->select('cases.patient_id')
            ->selectRaw('COUNT(*) AS case_count, SUM(COALESCE(cases.price, 0)) AS total_price')
            ->selectRaw('SUM(COALESCE(payments.paid_amount, 0)) AS paid_amount')
            // Clamp per case: overpaying one case must not settle another case.
            ->selectRaw('SUM(CASE WHEN COALESCE(cases.price, 0) > COALESCE(payments.paid_amount, 0)
                THEN COALESCE(cases.price, 0) - COALESCE(payments.paid_amount, 0) ELSE 0 END) AS unpaid_amount')
            ->selectRaw('SUM(COALESCE(payments.period_paid_amount, 0)) AS period_paid_amount')
            ->selectRaw('SUM(COALESCE(payments.period_payment_count, 0)) AS period_payment_count')
            ->selectRaw('MAX(payments.period_last_payment_at) AS last_payment_at')
            ->groupBy('cases.patient_id');

        if (! empty($filters['doctor_id'])) {
            $caseBalances->where('cases.doctor_id', $filters['doctor_id']);
        }
        // Internal history lookup; the public balances request does not expose this filter.
        if (! empty($filters['patient_id'])) {
            $caseBalances->where('cases.patient_id', $filters['patient_id']);
        }

        $query = DB::table('patients')
            ->joinSub($caseBalances, 'balances', 'balances.patient_id', '=', 'patients.id')
            ->whereNull('patients.deleted_at')
            ->select('patients.id', 'patients.name', 'patients.phone', 'balances.case_count',
                'balances.total_price', 'balances.paid_amount', 'balances.unpaid_amount', 'balances.last_payment_at',
                'balances.period_paid_amount', 'balances.period_payment_count');

        $this->applySearch($query, $filters);
        if (($filters['payment_status'] ?? null) === 'paid') {
            $query->where('balances.unpaid_amount', '=', 0);
        } elseif (($filters['payment_status'] ?? null) === 'unpaid') {
            $query->where('balances.unpaid_amount', '>', 0);
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $query->where('balances.period_payment_count', '>', 0);
        }

        return $query;
    }

    public function patientBalances(array $filters): array
    {
        $query = $this->balanceQuery($filters);
        $hasPeriod = ! empty($filters['date_from']) || ! empty($filters['date_to']);
        $totals = DB::query()->fromSub(clone $query, 'filtered_balances')
            ->selectRaw('COUNT(*) AS patient_count, COALESCE(SUM(total_price), 0) AS total_price,
                COALESCE(SUM(paid_amount), 0) AS paid_amount, COALESCE(SUM(unpaid_amount), 0) AS unpaid_amount,
                COALESCE(SUM(period_paid_amount), 0) AS period_paid_amount')
            ->first();

        $sort = $filters['sort'] ?? '-last_payment_at';
        $column = ltrim($sort, '-');
        if ($column === 'last_payment_at') {
            $query->orderByRaw('balances.last_payment_at IS NULL');
        }
        $query->orderBy($column === 'name' ? 'patients.name' : 'balances.'.$column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('patients.id', 'desc');

        $patients = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $patients->through(fn ($patient) => [
            'id' => (int) $patient->id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'case_count' => (int) $patient->case_count,
            'total_price' => (int) $patient->total_price,
            'paid_amount' => (int) $patient->paid_amount,
            ...($hasPeriod ? ['period_paid_amount' => (int) $patient->period_paid_amount] : []),
            'unpaid_amount' => (int) $patient->unpaid_amount,
            // The schema has no paid_at. This is the recorded bill creation date.
            'last_payment_at' => $patient->last_payment_at,
            'payment_status' => (int) $patient->unpaid_amount === 0 ? 'paid' : 'unpaid',
        ]);

        return [
            'records' => $patients,
            'summary' => [
                'total_price' => (int) $totals->total_price,
                'paid_amount' => (int) $totals->paid_amount,
                ...($hasPeriod ? ['period_paid_amount' => (int) $totals->period_paid_amount] : []),
                'unpaid_amount' => (int) $totals->unpaid_amount,
                'patient_count' => (int) $totals->patient_count,
            ],
        ];
    }

    public function payments(array $filters): array
    {
        $query = $this->caseBills($filters)->where('bills.is_paid', true);
        if (! empty($filters['payment_status'])) {
            $query->whereIn('cases.patient_id', $this->balanceQuery($filters)->select('patients.id'));
        }
        $totals = (clone $query)->toBase()->select([])
            ->selectRaw('COUNT(*) AS payment_count, COALESCE(SUM(bills.price), 0) AS paid_amount')->first();

        return [
            'records' => $this->paginateBills($query, $filters),
            'summary' => [
                'paid_amount' => (int) $totals->paid_amount,
                'payment_count' => (int) $totals->payment_count,
            ],
        ];
    }

    public function patientBills(int $patientId, array $filters): array
    {
        $patient = Patient::query()->findOrFail($patientId);
        $filters['patient_id'] = $patientId;
        // Refresh the lifetime balance independently of history search, dates and pagination.
        $balances = $this->patientBalances([
            'patient_id' => $patientId,
            'doctor_id' => $filters['doctor_id'] ?? null,
            'per_page' => 1,
        ]);

        return [
            'records' => $this->paginateBills($this->caseBills($filters), $filters),
            'patient_balance' => $balances['records']->first() ?? [
                'id' => (int) $patient->id,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'case_count' => 0,
                'total_price' => 0,
                'paid_amount' => 0,
                'unpaid_amount' => 0,
                'last_payment_at' => null,
                'payment_status' => 'paid',
            ],
        ];
    }

    private function caseBills(array $filters): Builder
    {
        $query = Bill::query()
            ->join('cases', 'cases.id', '=', 'bills.billable_id')
            ->join('patients', 'patients.id', '=', 'cases.patient_id')
            ->whereIn('bills.billable_type', self::CASE_TYPES)
            ->whereNull('cases.deleted_at')
            ->whereNull('patients.deleted_at')
            // The case owns the balance, including legacy bills without patient_id.
            ->select('bills.*', 'cases.patient_id as patient_id')
            ->with(['patient', 'doctor', 'creator', 'updator', 'billable.patient', 'billable.doctor', 'billable.category', 'billable.status']);

        $this->applySearch($query, $filters);
        if (! empty($filters['doctor_id'])) {
            $query->where('cases.doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['patient_id'])) {
            $query->where('cases.patient_id', $filters['patient_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('bills.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            // Exclusive next midnight includes the entire selected end date.
            $query->where('bills.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        return $query;
    }

    private function paginateBills(Builder $query, array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? '-created_at';

        return $query->orderBy('bills.'.ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('bills.id', 'desc')
            ->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }

    private function applySearch(Builder|QueryBuilder $query, array $filters): void
    {
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('patients.name', 'like', '%'.$search.'%')
                    ->orWhere('patients.phone', 'like', '%'.$search.'%');
            });
        }
    }
}
