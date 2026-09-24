<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientAccountReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Patient accounts report: per-patient case value/paid/remaining, with a
     * "fully paid vs has balance" secondary filter.
     */
    public function index(Request $request): JsonResponse
    {
        // sort feeds a raw ORDER BY column in BillingOverviewRepository — it
        // MUST be restricted to a fixed allow-list, never accepted as free text.
        $sortColumns = ['name', 'case_count', 'total_price', 'paid_amount', 'unpaid_amount', 'last_payment_at', 'last_visit_at'];
        $sorts = array_merge($sortColumns, array_map(fn ($c) => '-'.$c, $sortColumns));

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'sort' => ['nullable', 'string', Rule::in($sorts)],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $filters = [
            'doctor_id' => $this->getDoctorIdFilter(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'payment_status' => $request->input('payment_status'),
            'sort' => $request->input('sort'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ];

        $result = $this->reportsTableRepository->getPatientAccounts($filters);

        return response()->json([
            'success' => true,
            'message' => 'Patient accounts report retrieved successfully',
            'data' => $result['records']->items(),
            'summary' => $result['summary'],
            'pagination' => [
                'total' => $result['records']->total(),
                'per_page' => $result['records']->perPage(),
                'current_page' => $result['records']->currentPage(),
                'last_page' => $result['records']->lastPage(),
            ],
        ]);
    }

    /**
     * Drill-down: a single patient's cases + running balance.
     *
     * Reuses BillingOverviewRepository::patientBills — the same authoritative
     * per-case balance query used by the Bills page — instead of duplicating it.
     */
    public function show(Request $request, int $patient, \App\Repositories\BillingOverviewRepository $billingOverview): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $filters = array_filter([
            'doctor_id' => $this->getDoctorIdFilter(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ], fn ($v) => $v !== null);

        $data = $billingOverview->patientBills($patient, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Patient account detail retrieved successfully',
            'data' => \App\Http\Resources\BillResource::collection($data['records']->items())->resolve(),
            'patient_balance' => $data['patient_balance'],
            'pagination' => [
                'total' => $data['records']->total(),
                'per_page' => $data['records']->perPage(),
                'current_page' => $data['records']->currentPage(),
                'last_page' => $data['records']->lastPage(),
            ],
        ]);
    }
}
