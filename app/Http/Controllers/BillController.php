<?php

namespace App\Http\Controllers;

use App\Http\Helpers\BillsIsolationHelper;
use App\Http\Requests\BillRequest;
use App\Http\Resources\BillResource;
use App\Http\Controllers\Traits\DoctorFilterTrait;
use App\Repositories\BillRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillController extends Controller
{
    use DoctorFilterTrait;

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

        // Respects the clinic's doctor_bills_isolation setting
        $doctorId = $this->getBillsDoctorIdFilter();

        $bills = $this->billRepository->getAllWithFilters([], $perPage, $doctorId);

        return response()->json([
            'success'    => true,
            'message'    => 'Bills retrieved successfully',
            'data'       => BillResource::collection($bills),
            'pagination' => [
                'total'        => $bills->total(),
                'per_page'     => $bills->perPage(),
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
                'from'         => $bills->firstItem(),
                'to'           => $bills->lastItem(),
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
                'data'    => new BillResource($bill->load(['patient', 'doctor'])),
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
        $bill = $this->billRepository->getById($id);

        if (!$bill || $this->doctorCannotAccessBill($bill)) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bill retrieved successfully',
            'data'    => new BillResource($bill),
        ]);
    }

    /**
     * Update the specified bill.
     */
    public function update(BillRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            // price and bill_date can only be updated for case bills
            if (array_key_exists('price', $data) || array_key_exists('bill_date', $data)) {
                $bill = $this->billRepository->getById($id);

                if (!$bill || $this->doctorCannotAccessBill($bill)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bill not found',
                    ], 404);
                }

                if (!in_array($bill->billable_type, ['Case', 'CaseModel', 'App\Models\Case', 'App\Models\CaseModel'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'price and bill_date can only be updated for case bills',
                    ], 422);
                }
            }

            // Check ownership before updating
            $bill = $this->billRepository->getById($id);
            if (!$bill || $this->doctorCannotAccessBill($bill)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill not found',
                ], 404);
            }

            $bill = $this->billRepository->update($id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Bill updated successfully',
                'data'    => new BillResource($bill),
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
            $bill = $this->billRepository->getById($id);

            if (!$bill || $this->doctorCannotAccessBill($bill)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill not found',
                ], 404);
            }

            $this->billRepository->delete($id);

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
            $bill = $this->billRepository->getById($id);

            if (!$bill || $this->doctorCannotAccessBill($bill)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill not found',
                ], 404);
            }

            $bill = $this->billRepository->markAsPaid($id);

            return response()->json([
                'success' => true,
                'message' => 'Bill marked as paid successfully',
                'data'    => new BillResource($bill),
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
            $bill = $this->billRepository->getById($id);

            if (!$bill || $this->doctorCannotAccessBill($bill)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill not found',
                ], 404);
            }

            $bill = $this->billRepository->markAsUnpaid($id);

            return response()->json([
                'success' => true,
                'message' => 'Bill marked as unpaid successfully',
                'data'    => new BillResource($bill),
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
        $perPage  = $request->input('per_page', 15);
        $doctorId = $this->getBillsDoctorIdFilter();

        $bills = $this->billRepository->getByPatient($patientId, $perPage, $doctorId);

        return response()->json([
            'success'    => true,
            'message'    => 'Patient bills retrieved successfully',
            'data'       => BillResource::collection($bills),
            'pagination' => [
                'total'        => $bills->total(),
                'per_page'     => $bills->perPage(),
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
                'from'         => $bills->firstItem(),
                'to'           => $bills->lastItem(),
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
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:users,id',
        ]);

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to'   => $request->input('date_to'),
        ];

        // Respects the clinic's doctor_bills_isolation setting
        $doctorId = $this->getBillsDoctorIdFilter();
        if (!$doctorId && $request->has('doctor_id') && $request->input('doctor_id')) {
            $doctorId = $request->input('doctor_id');
        }

        $statistics = $this->billRepository->getStatisticsWithFilters($filters, 15, $doctorId);

        return response()->json([
            'success' => true,
            'message' => 'Bill statistics retrieved successfully',
            'data'    => $statistics,
            'filters' => $filters,
            'debug'   => [
                'doctor_id_filter'   => $doctorId,
                'user_id'            => auth()->id(),
                'has_view_all_bills' => auth()->user()->hasPermissionTo('view-all-bills'),
            ],
        ]);
    }

    /**
     * Returns true if the current user is a doctor with isolation ON
     * and the bill does NOT belong to them.
     *
     * Used to gate show / update / markAsPaid / markAsUnpaid / destroy.
     * Admins and super-doctors are never blocked.
     */
    private function doctorCannotAccessBill(\App\Models\Bill $bill): bool
    {
        $user = Auth::user();

        // Admins / super-doctors / secretaries can always access any bill
        if (
            $user->hasRole('clinic_super_doctor') ||
            $user->hasRole('secretary') ||
            $user->hasRole('super_admin')
        ) {
            return false;
        }

        // Plain doctor + isolation ON → block if bill doesn't belong to them
        if ($user->hasRole('doctor') && BillsIsolationHelper::isEnabled()) {
            return (int) $bill->doctor_id !== (int) $user->id;
        }

        return false;
    }
}
