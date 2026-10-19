<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsRepository $reportsRepository)
    {
        $this->middleware('permission:view-reports')->only(['summary', 'byCategory', 'byStatus', 'byDoctor', 'trend']);
    }

    /**
     * Get cases summary statistics.
     * 
     * Returns total count, paid/unpaid distribution, and total value.
     * Role-based filtering: Doctors see only their own cases.
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

        $summary = $this->reportsRepository->getCasesSummary($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Cases summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'doctor_id' => $doctorId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get cases grouped by category.
     * 
     * Returns case counts and total value grouped by case category.
     * Useful for pie charts or bar charts showing treatment distribution.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function byCategory(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $doctorId = $this->getReportDoctorIdFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getCasesByCategory($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Cases by category retrieved successfully',
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
     * Get cases grouped by status.
     * 
     * Returns case counts grouped by status with colors.
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

        $doctorId = $this->getReportDoctorIdFilter($request);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = $this->reportsRepository->getCasesByStatus($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Cases by status retrieved successfully',
            'data' => $data,
            'filters' => [
                'doctor_id' => $doctorId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'donut',
        ]);
    }

    /**
     * Get cases grouped by doctor.
     * 
     * Returns case counts and total value grouped by doctor.
     * Useful for bar charts showing doctor productivity.
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

        $data = $this->reportsRepository->getCasesByDoctor($doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Cases by doctor retrieved successfully',
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
     * Get cases trend over time.
     * 
     * Returns case counts grouped by period (day, week, month, year).
     * Useful for line charts showing case volume trends.
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

        $data = $this->reportsRepository->getCasesTrend($doctorId, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Cases trend retrieved successfully',
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
}
