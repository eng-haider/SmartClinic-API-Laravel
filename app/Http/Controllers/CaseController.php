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
        
        // Multi-tenancy: Database is already isolated by tenant
        // Only filter by doctor_id for regular doctors
        $doctorId = $this->getDoctorIdFilter();
        
        $cases = $this->caseRepository->getAllWithFilters($filters, $perPage, null, $doctorId);

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
                'data' => new CaseResource($case->load(['patient', 'doctor', 'category', 'status'])),
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
        // Multi-tenancy: Database is already isolated by tenant
        $doctorId = $this->getDoctorIdFilter();
        $case = $this->caseRepository->getById($id, null, $doctorId);

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
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * We only need to filter by doctor for regular doctors who should only see their own cases.
     * 
     * - Super Doctor/Secretary: sees all cases in their tenant database [null]
     * - Doctor: sees ONLY their own cases [user_id]
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();
        
        // Super doctor and secretary see all cases in this tenant
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            return null;
        }
        
        // Doctor sees only their own cases
        if ($user->hasRole('doctor')) {
            return $user->id;
        }
        
        // Default: show all cases in this tenant
        return null;
    }
}
