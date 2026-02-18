<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationReportController extends Controller
{
    use DoctorFilterTrait;
    public function __construct(private ReportsRepository $reportsRepository)
    {
        // Permissions can be added here if needed
        // $this->middleware('permission:view-reports')->only(['summary', 'byStatus', 'byDoctor', 'trend']);
    }

    /**
     * Get reservations summary statistics.
     * 
     * Returns total count, waiting/confirmed distribution with percentages.
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get doctor ID filter based on user role
        $doctorIdFilter = $this->getDoctorIdFilter();

        // Multi-tenancy: No need for clinic_id filter
        $summary = $this->reportsRepository->getReservationsSummary($doctorIdFilter, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get reservations grouped by status.
     * 
     * Returns reservation counts grouped by status with colors.
     * Useful for pie charts or status boards.
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function byStatus(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get doctor ID filter based on user role
        $doctorIdFilter = $this->getDoctorIdFilter();

        // Multi-tenancy: No need for clinic_id filter
        $data = $this->reportsRepository->getReservationsByStatus($doctorIdFilter, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations by status retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'donut',
        ]);
    }

    /**
     * Get reservations grouped by doctor.
     * 
     * Returns reservation counts grouped by doctor.
     * Useful for bar charts showing doctor appointment distribution.
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function byDoctor(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get doctor ID filter based on user role
        $doctorIdFilter = $this->getDoctorIdFilter();
        
        // Multi-tenancy: No need for clinic_id filter
        $data = $this->reportsRepository->getReservationsByDoctor($doctorIdFilter, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations by doctor retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'bar',
        ]);
    }

    /**
     * Get reservations trend over time.
     * 
     * Returns reservation counts grouped by period (day, week, month, year).
     * Useful for line charts showing appointment volume trends.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trend(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|in:day,week,month,year',
        ]);

        // Multi-tenancy: Database isolation via InitializeTenancyByHeader middleware
        // No clinic_id filtering needed
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period', 'month');

        // Get doctor ID filter based on user role
        $doctorIdFilter = $this->getDoctorIdFilter();

        $data = $this->reportsRepository->getReservationsTrend($doctorIdFilter, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations trend retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
            ],
            'chart_type' => 'line',
        ]);
    }
}
