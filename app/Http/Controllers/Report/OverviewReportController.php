<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Reports overview: collected revenue, expenses, net income, outstanding
     * balances and a clinic activity summary for the selected period.
     *
     * Multi-tenancy: database is already isolated by tenant via middleware.
     * Role-based filtering: doctors see only their own data.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $doctorId = $this->getDoctorIdFilter();
        $filters = [
            'doctor_id' => $doctorId,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $data = $this->reportsTableRepository->getOverview($filters);

        return response()->json([
            'success' => true,
            'message' => 'Reports overview retrieved successfully',
            'data' => $data,
            'filters' => $filters,
        ]);
    }
}
