<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardReportController extends Controller
{
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

        // Multi-tenancy: No need for clinic_id filter
        $overview = $this->reportsRepository->getDashboardOverview(null, $dateFrom, $dateTo);

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
     *
     * @return JsonResponse
     */
    public function today(): JsonResponse
    {
        // Multi-tenancy: No need for clinic_id filter
        $todaySummary = $this->reportsRepository->getTodaySummary(null);

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
