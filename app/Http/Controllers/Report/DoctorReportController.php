<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Doctors report: per-doctor patients/cases/value/paid/remaining for the
     * selected period, paginated, searchable and sortable.
     *
     * Role-based filtering: a doctor without view-all-bills only ever sees
     * their own row (getDoctorIdFilter -> restrict_doctor_id), preventing
     * IDOR via a doctor_id query override.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $restrictDoctorId = $this->getDoctorIdFilter();

        $filters = [
            'restrict_doctor_id' => $restrictDoctorId,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'sort' => $request->input('sort'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ];

        $result = $this->reportsTableRepository->getDoctorsReport($filters);

        return response()->json([
            'success' => true,
            'message' => 'Doctors report retrieved successfully',
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

    /**
     * Drill-down: a single doctor's cases within the selected period.
     *
     * IDOR guard: a doctor-scoped user may only view their own detail page.
     */
    public function show(Request $request, int $doctor): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $restrictDoctorId = $this->getDoctorIdFilter();
        if ($restrictDoctorId !== null && $restrictDoctorId !== $doctor) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $this->reportsTableRepository->getDoctorCases($doctor, [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Doctor case detail retrieved successfully',
            'data' => $data['records']->items(),
            'doctor' => $data['doctor'],
            'pagination' => [
                'total' => $data['records']->total(),
                'per_page' => $data['records']->perPage(),
                'current_page' => $data['records']->currentPage(),
                'last_page' => $data['records']->lastPage(),
            ],
        ]);
    }
}
