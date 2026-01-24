<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationReportController extends Controller
{
    public function __construct(private ReportsRepository $reportsRepository)
    {
        // Permissions can be added here if needed
        // $this->middleware('permission:view-reports')->only(['summary', 'byStatus', 'byDoctor', 'trend']);
    }

    /**
     * Get reservations summary statistics.
     * 
     * Returns total count, waiting/confirmed distribution with percentages.
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

        $clinicId = $this->getClinicIdByRole();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $summary = $this->reportsRepository->getReservationsSummary($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'clinic_id' => $clinicId,
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

        $clinicId = $this->getClinicIdByRole();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getReservationsByStatus($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations by status retrieved successfully',
            'data' => $data,
            'filters' => [
                'clinic_id' => $clinicId,
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

        $clinicId = $this->getClinicIdByRole();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getReservationsByDoctor($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations by doctor retrieved successfully',
            'data' => $data,
            'filters' => [
                'clinic_id' => $clinicId,
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

        $clinicId = $this->getClinicIdByRole();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period', 'month');

        $data = $this->reportsRepository->getReservationsTrend($clinicId, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Reservations trend retrieved successfully',
            'data' => $data,
            'filters' => [
                'clinic_id' => $clinicId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
            ],
            'chart_type' => 'line',
        ]);
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();

        // Super admin can see all data from all clinics
        if ($user->hasRole('super_admin')) {
            return null;
        }

        // All other roles see only their clinic's data
        return $user->clinic_id;
    }
}
