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
        $this->middleware('permission:view-clinic-reservations|view-all-reservations')->only(['index', 'show']);
        $this->middleware('permission:create-reservation')->only(['store']);
        $this->middleware('permission:edit-reservation')->only(['update']);
        $this->middleware('permission:delete-reservation')->only(['destroy']);
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
        
        // Get clinic_id and doctor_id based on user role
        [$clinicId, $doctorId] = $this->getFiltersByRole();
        
        $reservations = $this->reservationRepository->getAllWithFilters($filters, $perPage, $clinicId, $doctorId);

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
                'data' => new ReservationResource($reservation->load(['patient', 'doctor', 'clinic', 'status'])),
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
        [$clinicId, $doctorId] = $this->getFiltersByRole();
        $reservation = $this->reservationRepository->getById($id, $clinicId, $doctorId);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found',
            ], 404);
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
     * Get filters (clinic_id and doctor_id) based on user role.
     * Returns [clinic_id, doctor_id] array.
     * 
     * - Super Admin: sees ALL reservations from ALL clinics [null, null]
     * - Clinic Super Doctor: sees all reservations from their clinic [clinic_id, null]
     * - Doctor: sees ONLY their own reservations [clinic_id, user_id]
     * - Secretary: sees all reservations from their clinic [clinic_id, null]
     */
    private function getFiltersByRole(): array
    {
        $user = Auth::user();
        
        // Super admin can see all reservations from all clinics
        if ($user->hasRole('super_admin')) {
            return [null, null];
        }
        
        // Clinic super doctor and secretary see all reservations from their clinic
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary')) {
            return [$user->clinic_id, null];
        }
        
        // Doctor sees only their own reservations
        if ($user->hasRole('doctor')) {
            return [$user->clinic_id, $user->id];
        }
        
        // Default: filter by clinic only
        return [$user->clinic_id, null];
    }
}
