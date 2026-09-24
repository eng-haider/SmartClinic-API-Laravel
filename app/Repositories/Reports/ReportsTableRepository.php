<?php

namespace App\Repositories\Reports;

use App\Models\CaseModel;
use App\Models\ClinicExpense;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\BillingOverviewRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregation for the tabular / drill-down Reports pages (doctors, patient
 * accounts, case categories, revenue payments, expenses, appointments,
 * outstanding balances, overview).
 *
 * Deliberately separate from ReportsRepository (which backs the existing
 * chart/summary "dashboard analytics" endpoints) so neither file grows
 * unbounded. Reuses ReportsRepository and BillingOverviewRepository for any
 * calculation that already exists instead of re-deriving it — in particular
 * BillingOverviewRepository owns the single authoritative "outstanding
 * balance" calculation (per-case clamped, so an overpayment on one case never
 * offsets another case's balance) and every "remaining" figure in this class
 * is built the same way.
 */
class ReportsTableRepository
{
    /**
     * Billable types that count as case payments — the bills table is
     * polymorphic and legacy rows store the morph class under any of these
     * variants (mirrors ReportsRepository::CASE_BILLABLE_TYPES).
     */
    private const CASE_BILLABLE_TYPES = [
        'App\Models\Case',
        'App\Models\CaseModel',
        'Case',
        'CaseModel',
    ];

    public function __construct(
        private ReportsRepository $reportsRepository,
        private BillingOverviewRepository $billingOverview,
    ) {
    }

    /**
     * ============================
     * OVERVIEW
     * ============================
     *
     * Revenue = payments actually collected (not total case value).
     * Net income = collected payments - expenses (never derived from unpaid
     * case totals, per the app's accounting rules).
     */
    public function getOverview(array $filters): array
    {
        $doctorId = $filters['doctor_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $revenue = $this->reportsRepository->getRevenueByDateRange($doctorId, $dateFrom, $dateTo);
        $expenses = $this->reportsRepository->getExpensesTotalByDateRange($doctorId, $dateFrom, $dateTo);

        $balanceSummary = $this->billingOverview->patientBalances(array_filter([
            'doctor_id' => $doctorId,
            'per_page' => 1,
        ]))['summary'];

        $completedCases = CaseModel::query()
            ->where('status_id', CaseModel::COMPLETED_STATUS_ID)
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<', CarbonImmutable::parse($dateTo)->addDay()->startOfDay()))
            ->count();

        return [
            'revenue' => $revenue,
            'expenses' => round((float) $expenses, 2),
            'net_income' => round($revenue - $expenses, 2),
            'outstanding_balance' => (int) $balanceSummary['unpaid_amount'],
            'clinic_summary' => [
                'new_patients' => $this->reportsRepository->getNewPatientsCount($doctorId, $dateFrom, $dateTo),
                'new_cases' => $this->reportsRepository->getCasesCountByDateRange($doctorId, $dateFrom, $dateTo),
                'appointments' => $this->reportsRepository->getReservationsSummary($doctorId, $dateFrom, $dateTo)['total'],
                'completed_cases' => $completedCases,
            ],
        ];
    }

    /**
     * ============================
     * DOCTORS REPORT
     * ============================
     */
    public function getDoctorsReport(array $filters): array
    {
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) as paid_amount')
            ->whereIn('billable_type', self::CASE_BILLABLE_TYPES)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        $casesAgg = DB::table('cases')
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'cases.id')
            ->whereNull('cases.deleted_at')
            ->when(! empty($filters['date_from']), fn ($q) => $q->where('cases.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when(! empty($filters['date_to']), fn ($q) => $q->where('cases.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->select('cases.doctor_id')
            ->selectRaw('COUNT(*) as case_count')
            ->selectRaw('COUNT(DISTINCT cases.patient_id) as patient_count')
            ->selectRaw('SUM(CASE WHEN cases.status_id = ? THEN 1 ELSE 0 END) as completed_count', [CaseModel::COMPLETED_STATUS_ID])
            ->selectRaw('SUM(CASE WHEN cases.status_id IS NULL OR cases.status_id <> ? THEN 1 ELSE 0 END) as in_progress_count', [CaseModel::COMPLETED_STATUS_ID])
            ->selectRaw('SUM(COALESCE(cases.price, 0)) as total_value')
            ->selectRaw('SUM(COALESCE(payments.paid_amount, 0)) as paid_amount')
            ->selectRaw('SUM(GREATEST(COALESCE(cases.price, 0) - COALESCE(payments.paid_amount, 0), 0)) as remaining_amount')
            ->selectRaw('MAX(cases.created_at) as last_case_at')
            ->groupBy('cases.doctor_id');

        $reservationsAgg = DB::table('reservations')
            ->whereNull('deleted_at')
            ->when(! empty($filters['date_from']), fn ($q) => $q->where('reservation_start_date', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when(! empty($filters['date_to']), fn ($q) => $q->where('reservation_start_date', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->select('doctor_id')
            ->selectRaw('COUNT(*) as reservation_count')
            ->groupBy('doctor_id');

        // Resolve doctor-like user IDs via Eloquent/Spatie (mirrors
        // ReportsRepository::getDoctorPerformance) rather than hand-rolling
        // the permission package's pivot table schema.
        $doctorIds = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'clinic_super_doctor', 'super_admin']))
            ->pluck('id');

        $query = DB::table('users')
            ->whereIn('users.id', $doctorIds)
            ->leftJoinSub($casesAgg, 'agg', 'agg.doctor_id', '=', 'users.id')
            ->leftJoinSub($reservationsAgg, 'resv', 'resv.doctor_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->select(
                'users.id',
                'users.name',
                'users.phone',
                DB::raw('COALESCE(agg.case_count, 0) as case_count'),
                DB::raw('COALESCE(agg.patient_count, 0) as patient_count'),
                DB::raw('COALESCE(agg.completed_count, 0) as completed_count'),
                DB::raw('COALESCE(agg.in_progress_count, 0) as in_progress_count'),
                DB::raw('COALESCE(agg.total_value, 0) as total_value'),
                DB::raw('COALESCE(agg.paid_amount, 0) as paid_amount'),
                DB::raw('COALESCE(agg.remaining_amount, 0) as remaining_amount'),
                'agg.last_case_at',
                DB::raw('COALESCE(resv.reservation_count, 0) as reservation_count')
            );

        if (! empty($filters['restrict_doctor_id'])) {
            $query->where('users.id', $filters['restrict_doctor_id']);
        } elseif (! empty($filters['doctor_id'])) {
            $query->where('users.id', $filters['doctor_id']);
        }
        if (! empty($filters['search'])) {
            $query->where('users.name', 'like', '%'.$filters['search'].'%');
        }

        $totals = DB::query()->fromSub((clone $query), 'doctors_filtered')
            ->selectRaw('COUNT(*) as doctor_count, COALESCE(SUM(case_count),0) as case_count,
                COALESCE(SUM(total_value),0) as total_value, COALESCE(SUM(paid_amount),0) as paid_amount,
                COALESCE(SUM(remaining_amount),0) as remaining_amount')
            ->first();

        $sortMap = [
            'case_count' => 'case_count',
            'total_value' => 'total_value',
            'paid_amount' => 'paid_amount',
            'remaining_amount' => 'remaining_amount',
            'name' => 'users.name',
        ];
        $sort = $filters['sort'] ?? '-total_value';
        $column = $sortMap[ltrim($sort, '-')] ?? 'total_value';
        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('users.id');

        $paginator = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $paginator->through(function ($row) {
            $caseCount = (int) $row->case_count;

            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'phone' => $row->phone,
                'patient_count' => (int) $row->patient_count,
                'case_count' => $caseCount,
                'completed_count' => (int) $row->completed_count,
                'in_progress_count' => (int) $row->in_progress_count,
                'total_value' => (int) $row->total_value,
                'paid_amount' => (int) $row->paid_amount,
                'remaining_amount' => (int) $row->remaining_amount,
                'avg_case_value' => $caseCount > 0 ? (int) round($row->total_value / $caseCount) : 0,
                'reservation_count' => (int) $row->reservation_count,
                'last_case_at' => $row->last_case_at,
            ];
        });

        return [
            'records' => $paginator,
            'summary' => [
                'doctor_count' => (int) $totals->doctor_count,
                'case_count' => (int) $totals->case_count,
                'total_value' => (int) $totals->total_value,
                'paid_amount' => (int) $totals->paid_amount,
                'remaining_amount' => (int) $totals->remaining_amount,
            ],
        ];
    }

    /** Drill-down: a single doctor's cases within the selected period. */
    public function getDoctorCases(int $doctorId, array $filters): array
    {
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) as paid_amount')
            ->whereIn('billable_type', self::CASE_BILLABLE_TYPES)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        $query = CaseModel::query()
            ->where('doctor_id', $doctorId)
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'cases.id')
            ->with(['patient:id,name', 'category:id,name', 'status:id,name_ar,name_en,color'])
            ->select('cases.*', DB::raw('COALESCE(payments.paid_amount, 0) as paid_amount'));

        if (! empty($filters['date_from'])) {
            $query->where('cases.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('cases.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        $doctor = User::query()->select('id', 'name', 'phone')->findOrFail($doctorId);

        $query->orderByDesc('cases.created_at');
        $paginator = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $paginator->getCollection()->transform(function (CaseModel $case) {
            $paid = (int) $case->paid_amount;
            $price = (int) ($case->price ?? 0);

            return [
                'id' => $case->id,
                'patient' => $case->patient ? ['id' => $case->patient->id, 'name' => $case->patient->name] : null,
                'category' => $case->category?->name,
                'tooth_num' => $case->tooth_num,
                'price' => $price,
                'paid_amount' => $paid,
                'remaining_amount' => max($price - $paid, 0),
                'status' => $case->status ? ['name_ar' => $case->status->name_ar, 'name_en' => $case->status->name_en, 'color' => $case->status->color] : null,
                'case_date' => $case->case_date ?? $case->created_at,
            ];
        });

        return [
            'doctor' => ['id' => $doctor->id, 'name' => $doctor->name, 'phone' => $doctor->phone],
            'records' => $paginator,
        ];
    }

    /**
     * ============================
     * CASE CATEGORY REPORT
     * ============================
     */
    public function getCaseCategoryReport(array $filters): array
    {
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) as paid_amount')
            ->whereIn('billable_type', self::CASE_BILLABLE_TYPES)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        $query = DB::table('cases')
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'cases.id')
            ->join('case_categories', 'case_categories.id', '=', 'cases.case_categores_id')
            ->whereNull('cases.deleted_at')
            ->when(! empty($filters['doctor_id']), fn ($q) => $q->where('cases.doctor_id', $filters['doctor_id']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->where('cases.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when(! empty($filters['date_to']), fn ($q) => $q->where('cases.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->select('case_categories.id as category_id', 'case_categories.name as category_name')
            ->selectRaw('COUNT(*) as case_count')
            ->selectRaw('COUNT(DISTINCT cases.patient_id) as patient_count')
            ->selectRaw('COUNT(DISTINCT cases.doctor_id) as doctor_count')
            ->selectRaw('SUM(COALESCE(cases.price, 0)) as total_value')
            ->selectRaw('SUM(COALESCE(payments.paid_amount, 0)) as paid_amount')
            ->selectRaw('SUM(GREATEST(COALESCE(cases.price, 0) - COALESCE(payments.paid_amount, 0), 0)) as remaining_amount')
            ->groupBy('case_categories.id', 'case_categories.name');

        $rows = $query->get();
        $grandTotal = (float) $rows->sum('total_value');

        $records = $rows->map(function ($row) use ($grandTotal) {
            $caseCount = (int) $row->case_count;

            return [
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'case_count' => $caseCount,
                'patient_count' => (int) $row->patient_count,
                'doctor_count' => (int) $row->doctor_count,
                'total_value' => (int) $row->total_value,
                'paid_amount' => (int) $row->paid_amount,
                'remaining_amount' => (int) $row->remaining_amount,
                'avg_price' => $caseCount > 0 ? (int) round($row->total_value / $caseCount) : 0,
                'percentage' => $grandTotal > 0 ? round(($row->total_value / $grandTotal) * 100, 1) : 0,
            ];
        })->sortByDesc('total_value')->values();

        $sort = $filters['sort'] ?? null;
        if ($sort) {
            $column = ltrim($sort, '-');
            $desc = str_starts_with($sort, '-');
            $records = ($desc ? $records->sortByDesc($column) : $records->sortBy($column))->values();
        }

        return [
            'records' => $records->all(),
            'summary' => [
                'category_count' => $records->count(),
                'case_count' => (int) $rows->sum('case_count'),
                'total_value' => (int) $grandTotal,
                'paid_amount' => (int) $rows->sum('paid_amount'),
                'remaining_amount' => (int) $rows->sum('remaining_amount'),
            ],
        ];
    }

    /** Drill-down: cases within a single category. */
    public function getCategoryCases(int $categoryId, array $filters): array
    {
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) as paid_amount')
            ->whereIn('billable_type', self::CASE_BILLABLE_TYPES)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        $category = DB::table('case_categories')->where('id', $categoryId)->whereNull('deleted_at')->firstOrFail();

        $query = CaseModel::query()
            ->where('case_categores_id', $categoryId)
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'cases.id')
            ->with(['patient:id,name', 'doctor:id,name', 'status:id,name_ar,name_en,color'])
            ->select('cases.*', DB::raw('COALESCE(payments.paid_amount, 0) as paid_amount'));

        if (! empty($filters['doctor_id'])) {
            $query->where('cases.doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('cases.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('cases.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        $query->orderByDesc('cases.created_at');
        $paginator = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $paginator->getCollection()->transform(function (CaseModel $case) {
            $paid = (int) $case->paid_amount;
            $price = (int) ($case->price ?? 0);

            return [
                'id' => $case->id,
                'patient' => $case->patient ? ['id' => $case->patient->id, 'name' => $case->patient->name] : null,
                'doctor' => $case->doctor ? ['id' => $case->doctor->id, 'name' => $case->doctor->name] : null,
                'tooth_num' => $case->tooth_num,
                'price' => $price,
                'paid_amount' => $paid,
                'remaining_amount' => max($price - $paid, 0),
                'status' => $case->status ? ['name_ar' => $case->status->name_ar, 'name_en' => $case->status->name_en, 'color' => $case->status->color] : null,
                'case_date' => $case->case_date ?? $case->created_at,
            ];
        });

        return [
            'category' => ['id' => $category->id, 'name' => $category->name],
            'records' => $paginator,
        ];
    }

    /**
     * ============================
     * REVENUE / PAYMENTS TABLE
     * ============================
     *
     * One row per collected payment (Bill). "remaining_amount" is the case's
     * current total remaining balance (lifetime, clamped), not a historical
     * running balance at the moment of that specific payment.
     */
    public function getRevenuePayments(array $filters): array
    {
        $paymentsByCase = DB::table('bills')
            ->select('billable_id')
            ->selectRaw('SUM(price) as paid_amount')
            ->whereIn('billable_type', self::CASE_BILLABLE_TYPES)
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->groupBy('billable_id');

        $query = DB::table('bills')
            ->leftJoinSub($paymentsByCase, 'payments', 'payments.billable_id', '=', 'bills.billable_id')
            ->join('cases', 'cases.id', '=', 'bills.billable_id')
            ->join('patients', 'patients.id', '=', 'cases.patient_id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'bills.doctor_id')
            ->leftJoin('case_categories', 'case_categories.id', '=', 'cases.case_categores_id')
            ->whereIn('bills.billable_type', self::CASE_BILLABLE_TYPES)
            ->where('bills.is_paid', true)
            ->whereNull('bills.deleted_at')
            ->whereNull('cases.deleted_at')
            ->whereNull('patients.deleted_at')
            ->select(
                'bills.id',
                DB::raw('COALESCE(bills.bill_date, bills.created_at) as payment_date'),
                'patients.id as patient_id',
                'patients.name as patient_name',
                'doctors.id as doctor_id',
                'doctors.name as doctor_name',
                'case_categories.name as case_category',
                'cases.id as case_id',
                'cases.price as case_price',
                'bills.price as payment_amount',
                DB::raw('GREATEST(COALESCE(cases.price, 0) - COALESCE(payments.paid_amount, 0), 0) as remaining_amount')
            );

        if (! empty($filters['doctor_id'])) {
            $query->where('bills.doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('patients.name', 'like', "%{$search}%")->orWhere('patients.phone', 'like', "%{$search}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->whereRaw('COALESCE(bills.bill_date, bills.created_at) >= ?', [CarbonImmutable::parse($filters['date_from'])->startOfDay()]);
        }
        if (! empty($filters['date_to'])) {
            $query->whereRaw('COALESCE(bills.bill_date, bills.created_at) < ?', [CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()]);
        }

        // Aggregate from a derived table (not a clone + selectRaw): selectRaw()
        // on a clone APPENDS to the existing select list rather than replacing
        // it, which mixes aggregate and non-aggregate columns and errors under
        // ONLY_FULL_GROUP_BY. Wrapping the already-built row query as a
        // subquery (mirrors BillingOverviewRepository::patientBalances) avoids
        // that entirely.
        $totals = DB::query()->fromSub((clone $query), 'payments_filtered')
            ->selectRaw('COUNT(*) as payment_count, COALESCE(SUM(payment_amount),0) as paid_amount')
            ->first();

        $sort = $filters['sort'] ?? '-payment_date';
        $column = ltrim($sort, '-') === 'payment_date' ? 'payment_date' : 'payment_amount';
        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('bills.id', 'desc');

        $paginator = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $paginator->through(fn ($row) => [
            'id' => (int) $row->id,
            'payment_date' => $row->payment_date,
            'patient' => ['id' => (int) $row->patient_id, 'name' => $row->patient_name],
            'doctor' => $row->doctor_id ? ['id' => (int) $row->doctor_id, 'name' => $row->doctor_name] : null,
            'case_category' => $row->case_category,
            'case_price' => (int) $row->case_price,
            'payment_amount' => (int) $row->payment_amount,
            'remaining_amount' => (int) $row->remaining_amount,
        ]);

        return [
            'records' => $paginator,
            'summary' => [
                'payment_count' => (int) $totals->payment_count,
                'paid_amount' => (int) $totals->paid_amount,
                'avg_payment' => $totals->payment_count > 0 ? (int) round($totals->paid_amount / $totals->payment_count) : 0,
            ],
        ];
    }

    /**
     * ============================
     * EXPENSES TABLE
     * ============================
     */
    public function getExpensesReport(array $filters): array
    {
        $query = ClinicExpense::query()
            ->with(['category:id,name', 'creator:id,name'])
            ->select('clinic_expenses.*');

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('clinic_expense_category_id', $filters['category_id']);
        }
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        // See getRevenuePayments() above for why this aggregates via fromSub()
        // rather than cloning the row query and appending a selectRaw().
        $totals = DB::query()->fromSub((clone $query), 'expenses_filtered')
            ->selectRaw('COUNT(*) as expense_count, COALESCE(SUM(quantity * price), 0) as total_amount')
            ->first();

        $sort = $filters['sort'] ?? '-date';
        $column = in_array(ltrim($sort, '-'), ['date', 'price'], true) ? ltrim($sort, '-') : 'date';
        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id', 'desc');

        $paginator = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $paginator->through(fn (ClinicExpense $expense) => [
            'id' => $expense->id,
            'date' => $expense->date,
            'category' => $expense->category?->name,
            'name' => $expense->name,
            'amount' => (int) $expense->total,
            'creator' => $expense->creator?->name,
        ]);

        return [
            'records' => $paginator,
            'summary' => [
                'expense_count' => (int) $totals->expense_count,
                'total_amount' => (int) $totals->total_amount,
                'avg_amount' => $totals->expense_count > 0 ? (int) round($totals->total_amount / $totals->expense_count) : 0,
            ],
        ];
    }

    /**
     * ============================
     * APPOINTMENTS TABLE
     * ============================
     */
    public function getAppointmentsReport(array $filters): array
    {
        $query = Reservation::query()
            ->with(['patient:id,name', 'doctor:id,name', 'status:id,name_ar,name_en,color']);

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('patient', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->where('reservation_start_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('reservation_start_date', '<=', $filters['date_to']);
        }

        $statusBreakdown = $this->reportsRepository->getReservationsByStatus(
            $filters['doctor_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null
        );
        $findCount = function (array $needles) use ($statusBreakdown) {
            foreach ($statusBreakdown as $row) {
                $name = mb_strtolower($row['status_name'] ?? '');
                foreach ($needles as $needle) {
                    if (str_contains($name, $needle)) {
                        return (int) $row['count'];
                    }
                }
            }

            return 0;
        };
        $total = array_sum(array_column($statusBreakdown, 'count'));
        $upcoming = (clone $query)->where('reservation_start_date', '>=', now()->toDateString())->count();

        $query->orderByDesc('reservation_start_date')->orderByDesc('reservation_from_time');
        $paginator = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $paginator->through(fn (Reservation $r) => [
            'id' => $r->id,
            'patient' => $r->patient ? ['id' => $r->patient->id, 'name' => $r->patient->name] : null,
            'doctor' => $r->doctor ? ['id' => $r->doctor->id, 'name' => $r->doctor->name] : null,
            'date' => $r->reservation_start_date,
            'time' => $r->reservation_from_time,
            'status' => $r->status ? ['name_ar' => $r->status->name_ar, 'name_en' => $r->status->name_en, 'color' => $r->status->color] : null,
            'notes' => $r->notes ?? $r->reservation_type_note,
        ]);

        return [
            'records' => $paginator,
            'summary' => [
                'total' => $total,
                'completed' => $findCount(['complet', 'مكتمل']),
                'cancelled' => $findCount(['cancel', 'ملغ']),
                'no_show' => $findCount(['no show', 'no-show', 'لم يحضر', 'غياب']),
                'upcoming' => $upcoming,
            ],
        ];
    }

    /**
     * ============================
     * OUTSTANDING BALANCES
     * ============================
     *
     * Thin wrapper over BillingOverviewRepository::patientBalances — the
     * authoritative per-case-clamped balance calculation — restricted to
     * patients who currently owe money, with an optional amount-range filter.
     */
    public function getOutstandingBalances(array $filters): array
    {
        $balanceFilters = array_filter([
            'doctor_id' => $filters['doctor_id'] ?? null,
            'search' => $filters['search'] ?? null,
            'sort' => $filters['sort'] ?? '-unpaid_amount',
            'page' => $filters['page'] ?? null,
            'per_page' => $filters['per_page'] ?? null,
        ], fn ($v) => $v !== null);
        $balanceFilters['payment_status'] = 'unpaid';
        if (! empty($filters['balance_min'])) {
            $balanceFilters['balance_min'] = $filters['balance_min'];
        }
        if (! empty($filters['balance_max'])) {
            $balanceFilters['balance_max'] = $filters['balance_max'];
        }

        return $this->billingOverview->patientBalances($balanceFilters);
    }

    /**
     * ============================
     * PATIENT ACCOUNTS
     * ============================
     *
     * All patients (paid and unpaid), for the "حسابات المرضى" report —
     * reuses the same authoritative balance calculation, unfiltered by
     * payment status, with a payment-percentage added per row.
     */
    public function getPatientAccounts(array $filters): array
    {
        $balanceFilters = array_filter([
            'doctor_id' => $filters['doctor_id'] ?? null,
            'search' => $filters['search'] ?? null,
            'sort' => $filters['sort'] ?? null,
            'page' => $filters['page'] ?? null,
            'per_page' => $filters['per_page'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ], fn ($v) => $v !== null);
        if (! empty($filters['payment_status'])) {
            $balanceFilters['payment_status'] = $filters['payment_status'];
        }

        $result = $this->billingOverview->patientBalances($balanceFilters);
        $result['records']->through(function ($row) {
            $row['payment_percentage'] = $row['total_price'] > 0
                ? round(($row['paid_amount'] / $row['total_price']) * 100, 1)
                : 100.0;

            return $row;
        });

        return $result;
    }
}
