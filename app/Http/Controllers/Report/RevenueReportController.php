<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Revenue & payments report: one row per collected payment, paginated.
     * KPI cards for this page (case value, collected, outstanding, payment
     * count/average) are served by the existing FinancialReportController
     * endpoints (billsSummary, revenueByDoctor, revenueTrend) — reused here
     * rather than duplicated.
     */
    public function payments(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $filters = [
            'doctor_id' => $this->getDoctorIdFilter(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'sort' => $request->input('sort'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ];

        $result = $this->reportsTableRepository->getRevenuePayments($filters);

        return response()->json([
            'success' => true,
            'message' => 'Revenue payments retrieved successfully',
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
