<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\Reports\ReportsTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private ReportsTableRepository $reportsTableRepository)
    {
        $this->middleware('permission:view-reports');
    }

    /**
     * Appointments report table + summary. The "over time" chart reuses the
     * existing ReservationReportController::trend endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'status_id' => 'nullable|integer|exists:statuses,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $restrictDoctorId = $this->getDoctorIdFilter();
        $doctorId = $restrictDoctorId ?? $request->input('doctor_id');

        $filters = [
            'doctor_id' => $doctorId,
            'status_id' => $request->input('status_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ];

        $result = $this->reportsTableRepository->getAppointmentsReport($filters);

        return response()->json([
            'success' => true,
            'message' => 'Appointments report retrieved successfully',
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
