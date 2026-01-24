<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientReportController extends Controller
{
    public function __construct(private ReportsRepository $reportsRepository)
    {
        // Permissions can be added here if needed
        // $this->middleware('permission:view-reports')->only(['summary', 'bySource', 'byDoctor', 'trend', 'ageDistribution']);
    }

    /**
     * Get patient summary statistics.
     * 
     * Returns total count, gender distribution with percentages.
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

        $summary = $this->reportsRepository->getPatientsSummary($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patient summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'clinic_id' => $clinicId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get patients grouped by referral source.
     * 
     * Returns patient counts grouped by from_where_come (marketing source).
     * Useful for pie charts or bar charts showing patient acquisition channels.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bySource(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $clinicId = $this->getClinicIdByRole();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getPatientsBySource($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patients by source retrieved successfully',
            'data' => $data,
            'filters' => [
                'clinic_id' => $clinicId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'pie',
        ]);
    }

    /**
     * Get patients grouped by doctor.
     * 
     * Returns patient counts grouped by assigned doctor.
     * Useful for bar charts showing doctor workload distribution.
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

        $data = $this->reportsRepository->getPatientsByDoctor($clinicId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patients by doctor retrieved successfully',
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
     * Get patient registration trend over time.
     * 
     * Returns patient counts grouped by period (day, week, month, year).
     * Useful for line charts showing patient growth trends.
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

        $data = $this->reportsRepository->getPatientsTrend($clinicId, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patient trend retrieved successfully',
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
     * Get patient age distribution.
     * 
     * Returns patient counts grouped by age ranges.
     * Useful for bar charts or histograms showing demographic distribution.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ageDistribution(Request $request): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();

        $data = $this->reportsRepository->getPatientsAgeDistribution($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Patient age distribution retrieved successfully',
            'data' => $data,
            'filters' => [
                'clinic_id' => $clinicId,
            ],
            'chart_type' => 'bar',
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
