<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseCategoryReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Cases report: cases grouped by case category, with value/paid/remaining
     * and each category's share of the total. Categories are loaded from the
     * clinic's own case_categories table — never hardcoded.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort' => 'nullable|string',
        ]);

        $filters = [
            'doctor_id' => $this->getDoctorIdFilter(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'sort' => $request->input('sort'),
        ];

        $result = $this->reportsTableRepository->getCaseCategoryReport($filters);

        return response()->json([
            'success' => true,
            'message' => 'Case category report retrieved successfully',
            'data' => $result['records'],
            'summary' => $result['summary'],
        ]);
    }

    /** Drill-down: individual cases within a category. */
    public function show(Request $request, int $category): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $data = $this->reportsTableRepository->getCategoryCases($category, [
            'doctor_id' => $this->getDoctorIdFilter(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Case category detail retrieved successfully',
            'data' => $data['records']->items(),
            'category' => $data['category'],
            'pagination' => [
                'total' => $data['records']->total(),
                'per_page' => $data['records']->perPage(),
                'current_page' => $data['records']->currentPage(),
                'last_page' => $data['records']->lastPage(),
            ],
        ]);
    }
}
