<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaseRequest;
use App\Http\Resources\CaseResource;
use App\Repositories\CaseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private CaseRepository $caseRepository)
    {
        // index method will check permissions inside the method
        $this->middleware('permission:create-case')->only(['store']);
        $this->middleware('permission:view-clinic-cases')->only(['show']);
        $this->middleware('permission:edit-case')->only(['update']);
        $this->middleware('permission:delete-case')->only(['destroy']);
    }

    /**
     * Display a listing of all cases.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has either permission
        $user = Auth::user();
        if (!$user->hasPermissionTo('view-clinic-cases') && !$user->hasPermissionTo('create-bill')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You need either view-clinic-cases or create-bill permission.',
            ], 403);
        }

        $filters = $request->only([
            'search',
            'filter',
            'sort',
            'include',
        ]);

        $perPage = $request->input('per_page', 15);
        
        // Get clinic_id and doctor_id based on user role
        [$clinicId, $doctorId] = $this->getFiltersByRole();
        
        $cases = $this->caseRepository->getAllWithFilters($filters, $perPage, $clinicId, $doctorId);

        return response()->json([
            'success' => true,
            'message' => 'Cases retrieved successfully',
            'data' => CaseResource::collection($cases),
            'pagination' => [
                'total' => $cases->total(),
                'per_page' => $cases->perPage(),
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'from' => $cases->firstItem(),
                'to' => $cases->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created case in storage.
     */
    public function store(CaseRequest $request): JsonResponse
    {
        try {
            $case = $this->caseRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Case created successfully',
                'data' => new CaseResource($case->load(['patient', 'doctor', 'clinic', 'category', 'status'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified case.
     */
    public function show(int $id): JsonResponse
    {
        [$clinicId, $doctorId] = $this->getFiltersByRole();
        $case = $this->caseRepository->getById($id, $clinicId, $doctorId);

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Case retrieved successfully',
            'data' => new CaseResource($case),
        ]);
    }

    /**
     * Update the specified case in storage.
     */
    public function update(CaseRequest $request, int $id): JsonResponse
    {
        try {
            $case = $this->caseRepository->getById($id);

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found',
                ], 404);
            }

            $updated = $this->caseRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Case updated successfully',
                'data' => new CaseResource($updated),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified case from storage (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $case = $this->caseRepository->getById($id);

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found',
                ], 404);
            }

            $this->caseRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Case deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();
        
        // Super admin can see all cases from all clinics
        if ($user->hasRole('super_admin')) {
            return null;
        }
        
        // All other roles (clinic_super_doctor, doctor, secretary) see only their clinic
        return $user->clinic_id;
    }

    /**
     * Get filters (clinic_id and doctor_id) based on user role.
     * Returns [clinic_id, doctor_id] array.
     * 
     * - Super Admin: sees ALL cases from ALL clinics [null, null]
     * - Clinic Super Doctor: sees all cases from their clinic [clinic_id, null]
     * - Doctor: sees ONLY their own cases [clinic_id, user_id]
     * - Secretary: sees all cases from their clinic [clinic_id, null]
     */
    private function getFiltersByRole(): array
    {
        $user = Auth::user();
        
        // Super admin can see all cases from all clinics
        if ($user->hasRole('super_admin')) {
            return [null, null];
        }
        
        // Clinic super doctor and secretary see all cases from their clinic
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary')) {
            return [$user->clinic_id, null];
        }
        
        // Doctor sees only their own cases
        if ($user->hasRole('doctor')) {
            return [$user->clinic_id, $user->id];
        }
        
        // Default: filter by clinic only
        return [$user->clinic_id, null];
    }
}
