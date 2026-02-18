<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardReportController extends Controller
{
    use DoctorFilterTrait;
    public function __construct(private ReportsRepository $reportsRepository)
    {
        // Permissions can be added here if needed
        // $this->middleware('permission:view-reports')->only(['overview', 'today']);
    }

    /**
     * Get dashboard overview statistics.
     * 
     * Returns aggregated statistics for patients, bills, reservations, cases, and expenses.
     * Supports date filtering.
     * 
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * Role-based filtering: Doctors see only their own data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function overview(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $doctorId = $this->getDoctorIdFilter();

        // Multi-tenancy: No need for clinic_id filter
        // Doctor ID filter applied - doctors see only their own data
        $overview = $this->reportsRepository->getDashboardOverview($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview retrieved successfully',
            'data' => $overview,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get today's quick statistics.
     * 
     * Returns key metrics for the current day including new patients,
     * reservations, revenue, cases, and expenses.
     * 
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * Role-based filtering: Doctors see only their own data.
     *
     * @return JsonResponse
     */
    public function today(): JsonResponse
    {
        $doctorId = $this->getDoctorIdFilter();

        // Multi-tenancy: No need for clinic_id filter
        // Doctor ID filter applied - doctors see only their own data
        $todaySummary = $this->reportsRepository->getTodaySummary($doctorId);

        return response()->json([
            'success' => true,
            'message' => 'Today\'s summary retrieved successfully',
            'data' => $todaySummary,
            'filters' => [
                'date' => now()->toDateString(),
            ],
        ]);
    }

    // Note: getClinicIdByRole() method removed - no longer needed with multi-tenancy
    // Database isolation is handled automatically by the tenancy middleware
}
