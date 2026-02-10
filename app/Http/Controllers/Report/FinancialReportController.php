<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Repositories\Reports\ReportsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialReportController extends Controller
{
    public function __construct(private ReportsRepository $reportsRepository)
    {
        // Permissions can be added here if needed
        // $this->middleware('permission:view-reports')->only([...]);
    }

    /**
     * Get bills/revenue summary statistics.
     * 
     * Returns bill counts, paid/unpaid distribution, revenue and outstanding amounts.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function billsSummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $summary = $this->reportsRepository->getBillsSummary(null, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Bills summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get revenue grouped by doctor.
     * 
     * Returns revenue amounts grouped by doctor.
     * Useful for bar charts showing doctor revenue contribution.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function revenueByDoctor(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getRevenueByDoctor(null, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Revenue by doctor retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'bar',
        ]);
    }

    /**
     * Get revenue trend over time.
     * 
     * Returns revenue amounts grouped by period (day, week, month, year).
     * Useful for line charts or area charts showing revenue trends.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function revenueTrend(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|in:day,week,month,year',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period', 'month');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getRevenueTrend(null, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Revenue trend retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
            ],
            'chart_type' => 'area',
        ]);
    }

    /**
     * Get bills by payment status.
     * 
     * Returns bill counts grouped by paid/unpaid status with percentages.
     * Useful for pie charts or donut charts.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function billsByPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getBillsByPaymentStatus(null, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Bills by payment status retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'pie',
        ]);
    }

    /**
     * Get expenses summary statistics.
     * 
     * Returns expense counts, paid/unpaid distribution, and total amounts.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expensesSummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $summary = $this->reportsRepository->getExpensesSummary(null, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Expenses summary retrieved successfully',
            'data' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get expenses grouped by category.
     * 
     * Returns expense counts and amounts grouped by expense category.
     * Useful for pie charts showing expense distribution.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expensesByCategory(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getExpensesByCategory(null, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Expenses by category retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'chart_type' => 'pie',
        ]);
    }

    /**
     * Get expenses trend over time.
     * 
     * Returns expense amounts grouped by period (day, week, month, year).
     * Useful for line charts or area charts showing expense trends.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expensesTrend(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|in:day,week,month,year',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period', 'month');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getExpensesTrend(null, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Expenses trend retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
            ],
            'chart_type' => 'area',
        ]);
    }

    /**
     * Get profit/loss report.
     * 
     * Returns revenue, expenses, and profit/loss with profit margin.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getProfitLossReport(null, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Profit/Loss report retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get profit/loss trend over time.
     * 
     * Returns revenue, expenses, and profit/loss grouped by period.
     * Useful for combo charts showing financial performance trends.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profitLossTrend(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|in:day,week,month,year',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $period = $request->input('period', 'month');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getProfitLossTrend(null, $period, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Profit/Loss trend retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
            ],
            'chart_type' => 'combo',
        ]);
    }

    /**
     * Get doctor performance statistics.
     * 
     * Returns comprehensive performance metrics per doctor including
     * patients, cases, reservations, and revenue.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function doctorPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:users,id',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $doctorId = $request->input('doctor_id');

        // Multi-tenancy: No need for clinic_id, database is already isolated by tenant
        $data = $this->reportsRepository->getDoctorPerformance(null, $doctorId, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Doctor performance retrieved successfully',
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'doctor_id' => $doctorId,
            ],
            'chart_type' => 'table',
        ]);
    }
}
