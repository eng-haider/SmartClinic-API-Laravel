<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Expenses report table. Category/trend charts reuse the existing
     * FinancialReportController expensesByCategory / expensesTrend endpoints.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:clinic_expense_categories,id',
            'sort' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $filters = [
            'doctor_id' => $this->getDoctorIdFilter(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'sort' => $request->input('sort'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ];

        $result = $this->reportsTableRepository->getExpensesReport($filters);

        return response()->json([
            'success' => true,
            'message' => 'Expenses report retrieved successfully',
            'data' => $result['records']->items(),
            'summary' => $result['summary'],
            'pagination' => [
                'total' => $result['records']->total(),
                'per_page' => $result['records']->perPage(),
                'current_page' => $result['records']->currentPage(),
                'last_page' => $result['records']->lastPage(),
            ],
        ]);
    }
}
