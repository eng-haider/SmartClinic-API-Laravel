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
     * Supports date filtering and role-based clinic filtering.
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

        $clinicId = $this->getClinicIdByRole();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $overview = $this->reportsRepository->getDashboardOverview($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview retrieved successfully',
            'data' => $overview,
            'filters' => [
                'clinic_id' => $clinicId,
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
     * @return JsonResponse
     */
    public function today(): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();

        $todaySummary = $this->reportsRepository->getTodaySummary($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Today\'s summary retrieved successfully',
            'data' => $todaySummary,
            'filters' => [
                'clinic_id' => $clinicId,
                'date' => now()->toDateString(),
            ],
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
