<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Repositories\ReservationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private ReservationRepository $reservationRepository)
    {
        // $this->middleware('permission:view-clinic-reservations')->only(['index', 'show']);
        // $this->middleware('permission:create-reservation')->only(['store']);
        // $this->middleware('permission:edit-reservation')->only(['update']);
        // $this->middleware('permission:delete-reservation')->only(['destroy']);
    }

    /**
     * Display a listing of reservations.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'filter',
            'sort',
            'include',
            'from_date',
            'to_date',
        ]);

        $perPage = $request->input('per_page', 15);

        // Multi-tenancy: Get doctor_id filter based on user role
        // Database is already isolated by tenant, no need for clinic_id
        $doctorId = $this->getDoctorIdFilter();

        $reservations = $this->reservationRepository->getAllWithFilters($filters, $perPage, null, $doctorId);

        return response()->json([
            'success' => true,
            'message' => 'Reservations retrieved successfully',
            'data' => ReservationResource::collection($reservations),
            'pagination' => [
                'total' => $reservations->total(),
                'per_page' => $reservations->perPage(),
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'from' => $reservations->firstItem(),
                'to' => $reservations->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created reservation.
     */
    public function store(ReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Reservation created successfully',
                'data' => new ReservationResource($reservation->load(['patient', 'doctor', 'status', 'reservationType'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified reservation.
     */
    public function show(int $id): JsonResponse
    {
        // Multi-tenancy: Database is already isolated by tenant
        $doctorId = $this->getDoctorIdFilter();
        $reservation = $this->reservationRepository->getById($id, null, $doctorId);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found',
            ], 404);
        }

        // Auto-mark as Completed (status_id=3) when opened, unless already Completed or Cancelled
        // Pending=1, In Progress=2 → auto-advance to Completed=3; Completed=3, Cancelled=4 → untouched
        if (in_array($reservation->status_id, [1, 2])) {
            $reservation->update(['status_id' => 3]);
            $reservation = $reservation->fresh(['patient', 'doctor', 'status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservation retrieved successfully',
            'data' => new ReservationResource($reservation),
        ]);
    }

    /**
     * Update the specified reservation.
     */
    public function update(ReservationRequest $request, int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Reservation updated successfully',
                'data' => new ReservationResource($reservation),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified reservation.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->reservationRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Reservation deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get doctor_id filter based on user role.
     * Returns doctor_id or null.
     * 
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * We only need to filter by doctor for regular doctors who should only see their own reservations.
     * 
     * - Super Doctor/Secretary: sees all reservations in their tenant database [null]
     * - Doctor: sees ONLY their own reservations [user_id]
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();

        // Super doctor and secretary see all reservations in this tenant
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            return null;
        }

        // Doctor sees only their own reservations
        if ($user->hasRole('doctor')) {
            return $user->id;
        }

        // Default: show all reservations in this tenant
        return null;
    }




    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status_id' => 'required|integer|exists:statuses,id',
        ]);

        try {
            $reservation = $this->reservationRepository->changeStatus($id, $request->input('status_id'));

            return response()->json([
                'success' => true,
                'message' => 'Reservation status updated successfully',
                'data' => new ReservationResource($reservation->load(['patient', 'doctor', 'status', 'reservationType'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
