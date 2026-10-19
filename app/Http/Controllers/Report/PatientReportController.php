<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientReportController extends Controller
{
    use DoctorFilterTrait;
    public function __construct(private ReportsRepository $reportsRepository)
    {
        $this->middleware('permission:view-reports')->only(['summary', 'bySource', 'byDoctor', 'trend', 'ageDistribution']);
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

        $doctorId = $this->getReportDoctorIdFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $summary = $this->reportsRepository->getPatientsSummary($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patient summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'doctor_id' => $doctorId,
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

        $doctorId = $this->getReportDoctorIdFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getPatientsBySource($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patients by source retrieved successfully',
            'data' => $data,
            'filters' => [
                'doctor_id' => $doctorId,
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

        $doctorId = $this->getReportDoctorIdFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getPatientsByDoctor($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patients by doctor retrieved successfully',
            'data' => $data,
            'filters' => [
                'doctor_id' => $doctorId,
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

        $doctorId = $this->getReportDoctorIdFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period', 'month');

        $data = $this->reportsRepository->getPatientsTrend($doctorId, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Patient trend retrieved successfully',
            'data' => $data,
            'filters' => [
                'doctor_id' => $doctorId,
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
        $doctorId = $this->getReportDoctorIdFilter($request);

        $data = $this->reportsRepository->getPatientsAgeDistribution($doctorId);

        return response()->json([
            'success' => true,
            'message' => 'Patient age distribution retrieved successfully',
            'data' => $data,
            'filters' => [
                'doctor_id' => $doctorId,
            ],
            'chart_type' => 'bar',
        ]);
    }
}
