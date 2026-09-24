<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutstandingBalanceReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Outstanding balances report: patients who currently owe money, sorted
     * by remaining balance (highest first) by default, with an amount-range
     * filter. Reuses BillingOverviewRepository's authoritative balance query.
     */
    public function index(Request $request): JsonResponse
    {
        // sort feeds a raw ORDER BY column in BillingOverviewRepository — it
        // MUST be restricted to a fixed allow-list, never accepted as free text.
        $sortColumns = ['name', 'case_count', 'total_price', 'paid_amount', 'unpaid_amount', 'last_payment_at', 'last_visit_at'];
        $sorts = array_merge($sortColumns, array_map(fn ($c) => '-'.$c, $sortColumns));

        $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => ['nullable', 'string', Rule::in($sorts)],
            'balance_min' => 'nullable|numeric|min:0',
            'balance_max' => 'nullable|numeric|min:0|gte:balance_min',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $filters = [
            'doctor_id' => $this->getDoctorIdFilter(),
            'search' => $request->input('search'),
            'sort' => $request->input('sort'),
            'balance_min' => $request->input('balance_min'),
            'balance_max' => $request->input('balance_max'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ];

        $result = $this->reportsTableRepository->getOutstandingBalances($filters);

        return response()->json([
            'success' => true,
            'message' => 'Outstanding balances retrieved successfully',
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
}
