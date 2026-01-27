<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillRequest;
use App\Http\Resources\BillResource;
use App\Repositories\BillRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private BillRepository $billRepository)
    {
        // View bills - any of these permissions
        $this->middleware('permission:view-bills,view-all-bills,view-clinic-bills')->only(['index', 'show', 'statistics']);
        $this->middleware('permission:create-bill')->only(['store']);
        $this->middleware('permission:edit-bill')->only(['update', 'markAsPaid', 'markAsUnpaid']);
        $this->middleware('permission:delete-bill')->only(['destroy']);
    }

    /**
     * Display a listing of bills.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'filter',
            'sort',
            'include',
        ]);

        $perPage = $request->input('per_page', 15);

        // Get clinic_id based on user role
        $clinicId = $this->getClinicIdByRole();

        $bills = $this->billRepository->getAllWithFilters($filters, $perPage, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Bills retrieved successfully',
            'data' => BillResource::collection($bills),
            'pagination' => [
                'total' => $bills->total(),
                'per_page' => $bills->perPage(),
                'current_page' => $bills->currentPage(),
                'last_page' => $bills->lastPage(),
                'from' => $bills->firstItem(),
                'to' => $bills->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created bill.
     */
    public function store(BillRequest $request): JsonResponse
    {
        try {
            $bill = $this->billRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Bill created successfully',
                'data' => new BillResource($bill->load(['patient', 'doctor', 'clinic'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified bill.
     */
    public function show(int $id): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $bill = $this->billRepository->getById($id, $clinicId);

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bill retrieved successfully',
            'data' => new BillResource($bill),
        ]);
    }

    /**
     * Update the specified bill.
     */
    public function update(BillRequest $request, int $id): JsonResponse
    {
        try {
            $clinicId = $this->getClinicIdByRole();
            $bill = $this->billRepository->update($id, $request->validated(), $clinicId);

            return response()->json([
                'success' => true,
                'message' => 'Bill updated successfully',
                'data' => new BillResource($bill),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified bill.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $clinicId = $this->getClinicIdByRole();
            $this->billRepository->delete($id, $clinicId);

            return response()->json([
                'success' => true,
                'message' => 'Bill deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mark bill as paid.
     */
    public function markAsPaid(int $id): JsonResponse
    {
        try {
            $clinicId = $this->getClinicIdByRole();
            $bill = $this->billRepository->markAsPaid($id, $clinicId);

            return response()->json([
                'success' => true,
                'message' => 'Bill marked as paid successfully',
                'data' => new BillResource($bill),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mark bill as unpaid.
     */
    public function markAsUnpaid(int $id): JsonResponse
    {
        try {
            $clinicId = $this->getClinicIdByRole();
            $bill = $this->billRepository->markAsUnpaid($id, $clinicId);

            return response()->json([
                'success' => true,
                'message' => 'Bill marked as unpaid successfully',
                'data' => new BillResource($bill),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get bills by patient.
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $clinicId = $this->getClinicIdByRole();

        $bills = $this->billRepository->getByPatient($patientId, $perPage, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Patient bills retrieved successfully',
            'data' => BillResource::collection($bills),
            'pagination' => [
                'total' => $bills->total(),
                'per_page' => $bills->perPage(),
                'current_page' => $bills->currentPage(),
                'last_page' => $bills->lastPage(),
                'from' => $bills->firstItem(),
                'to' => $bills->lastItem(),
            ],
        ]);
    }

    /**
     * Get bill statistics.
     */
    public function statistics(): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $statistics = $this->billRepository->getStatistics($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Bill statistics retrieved successfully',
            'data' => $statistics,
        ]);
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();

        // Super admin can see all bills from all clinics
        if ($user->hasRole('super_admin')) {
            return null;
        }

        // All other roles see only their clinic's bills
        return $user->clinic_id ?? $user->clinics_id;
    }
}
