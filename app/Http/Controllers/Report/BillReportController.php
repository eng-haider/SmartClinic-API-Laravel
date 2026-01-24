<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Repositories\BillRepository;

class BillReportController extends Controller
{
    public function __construct(private BillRepository $billRepository)
    {
        // permission middleware can be added here if needed
    }

    /**
     * Return bill statistics (counts and sums) with optional filters.
     * Query params: date_from, date_to, doctor_id
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'doctor_id' => 'nullable|integer|exists:users,id',
        ]);

        $filters = $request->only(['date_from', 'date_to', 'doctor_id']);

        $user = Auth::user();
        $clinicId = null;
        if (!$user->hasRole('super_admin')) {
            $clinicId = $user->clinic_id;
        }

        $stats = $this->billRepository->getStatisticsWithFilters($filters, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Bill report retrieved successfully',
            'data' => $stats,
        ]);
    }
}
