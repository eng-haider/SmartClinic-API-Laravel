<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingOverviewRequest;
use App\Http\Requests\BillRequest;
use App\Http\Resources\BillResource;
use App\Repositories\BillingOverviewRepository;
use App\Repositories\BillRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private BillRepository $billRepository)
    {
        $this->middleware('permission:view-all-bills')->only(['index', 'show', 'statistics', 'patientBalances', 'payments', 'patientBillHistory']);
        $this->middleware('permission:create-bill')->only(['store']);
        $this->middleware('permission:edit-bill')->only(['update', 'markAsPaid', 'markAsUnpaid']);
        $this->middleware('permission:delete-bill')->only(['destroy']);
    }

    public function patientBalances(BillingOverviewRequest $request, BillingOverviewRepository $overview): JsonResponse
    {
        return $this->overviewResponse($overview->patientBalances($request->validated()));
    }

    public function payments(BillingOverviewRequest $request, BillingOverviewRepository $overview): JsonResponse
    {
        return $this->overviewResponse($overview->payments($request->validated()), true);
    }

    public function patientBillHistory(BillingOverviewRequest $request, int $patientId, BillingOverviewRepository $overview): JsonResponse
    {
        return $this->overviewResponse($overview->patientBills($patientId, $request->validated()), true);
    }

    private function overviewResponse(array $result, bool $bills = false): JsonResponse
    {
        $records = $result['records'];
        $response = [
            'success' => true,
            'data' => $bills ? BillResource::collection($records) : $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
        ];
        if (isset($result['summary'])) {
            $response['summary'] = $result['summary'];
        }
        if (isset($result['patient_balance'])) {
            $response['patient_balance'] = $result['patient_balance'];
        }

        return response()->json($response);
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

        // Multi-tenancy: Database is already isolated by tenant
        $bills = $this->billRepository->getAllWithFilters($filters, $perPage, null);

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

        if (! $bill) {
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
            // Multi-tenancy: Database is already isolated by tenant
            $bill = $this->billRepository->update($id, $request->validated(), null);

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
    public function statistics(): JsonResponse
    {
        // Multi-tenancy: Database is already isolated by tenant
        $statistics = $this->billRepository->getStatistics(null);

        return response()->json([
            'success' => true,
            'message' => 'Bill statistics retrieved successfully',
            'data' => $statistics,
        ]);
    }
}
