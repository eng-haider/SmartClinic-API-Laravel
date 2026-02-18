<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Repositories\BillRepository;

class BillReportController extends Controller
{
    use DoctorFilterTrait;

    public function __construct(private BillRepository $billRepository)
    {
        // permission middleware can be added here if needed
    }

    /**
     * Return bill statistics (counts and sums) with optional filters.
     * Query params: date_from, date_to, doctor_id
     * 
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * 
     * Role-based filtering:
     * - Doctor: Returns only their own bills
     * - Clinic Admin/Secretary: Can filter by doctor_id or see all
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'doctor_id' => 'nullable|integer|exists:users,id',
        ]);

        $filters = $request->only(['date_from', 'date_to']);
        
        // Get doctor ID filter based on user role
        $doctorId = $this->getDoctorIdFilter();
        
        // If user is not a doctor but provided doctor_id, use that for filtering
        if (!$doctorId && $request->has('doctor_id') && $request->input('doctor_id')) {
            $doctorId = $request->input('doctor_id');
        }

        // Multi-tenancy: No need for clinic_id filter
        $stats = $this->billRepository->getStatisticsWithFilters($filters, 15, $doctorId);

        return response()->json([
            'success' => true,
            'message' => 'Bill report retrieved successfully',
            'data' => $stats,
        ]);
    }
}
