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
        // $this->middleware('permission:view-all-bills')->only(['index', 'show', 'statistics']);
        $this->middleware('permission:create-bill')->only(['store']);
        $this->middleware('permission:edit-bill')->only(['update', 'markAsPaid', 'markAsUnpaid']);
        $this->middleware('permission:delete-bill')->only(['destroy']);
    }

    /**
     * Display a listing of bills.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        // Multi-tenancy: Database is already isolated by tenant
        // Get doctor_id filter based on user role
        $doctorId = $this->getDoctorIdFilter();
        
        $bills = $this->billRepository->getAllWithFilters([], $perPage, $doctorId);


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
                'data' => new BillResource($bill->load(['patient', 'doctor'])),
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
        // Multi-tenancy: Database is already isolated by tenant
        $bill = $this->billRepository->getById($id, null);

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
        // try {
        //     // Multi-tenancy: Database is already isolated by tenant
        //     $bill = $this->billRepository->update($id, $request->validated(), null);

        //     return response()->json([
        //         'success' => true,
        //         'message' => 'Bill updated successfully',
        //         'data' => new BillResource($bill),
        //     ]);
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => $e->getMessage(),
        //     ], 422);
        // }
    }

    /**
     * Remove the specified bill.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Multi-tenancy: Database is already isolated by tenant
            $this->billRepository->delete($id, null);

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
            // Multi-tenancy: Database is already isolated by tenant
            $bill = $this->billRepository->markAsPaid($id, null);

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
            // Multi-tenancy: Database is already isolated by tenant
            $bill = $this->billRepository->markAsUnpaid($id, null);

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
        // Multi-tenancy: Database is already isolated by tenant
        $bills = $this->billRepository->getByPatient($patientId, $perPage, null);

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
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:users,id',
        ]);

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $doctorId = $this->getDoctorIdFilter();

      

        $statistics = $this->billRepository->getStatisticsWithFilters([], $perPage=15, $doctorId);;

        return response()->json([
            'success' => true,
            'message' => 'Bill statistics retrieved successfully',
            'data' => $statistics,
            'filters' => $filters,
        ]);
    }

    /**
     * Get doctor_id filter based on user role.
     * Returns doctor_id or null.
     * 
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * We only need to filter by doctor for regular doctors who should only see their own bills.
     * 
     * - Super Doctor/Secretary: sees all bills in their tenant database [null]
     * - Doctor: sees ONLY their own bills [user_id]
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();
        
        // Super doctor and secretary see all bills in this tenant
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            return null;
        }
        
        // Doctor sees only their own bills
        if ($user->hasRole('doctor')) {
            return $user->id;
        }
        
        // Default: show all bills in this tenant
        return null;
    }
}
