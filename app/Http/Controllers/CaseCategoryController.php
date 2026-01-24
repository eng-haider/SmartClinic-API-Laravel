<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaseCategoryRequest;
use App\Http\Resources\CaseCategoryResource;
use App\Repositories\CaseCategoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseCategoryController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private CaseCategoryRepository $caseCategoryRepository)
    {
        // $this->middleware('permission:view-clinic-cases')->only(['index', 'show']);
        // $this->middleware('permission:create-case')->only(['store']);
        // $this->middleware('permission:edit-case')->only(['update']);
        // $this->middleware('permission:delete-case')->only(['destroy']);
    }

    /**
     * Display a listing of case categories.
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
        
        $categories = $this->caseCategoryRepository->getAllWithFilters($filters, $perPage, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Case categories retrieved successfully',
            'data' => CaseCategoryResource::collection($categories),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created case category.
     */
    public function store(CaseCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->caseCategoryRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Case category created successfully',
                'data' => new CaseCategoryResource($category),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified case category.
     */
    public function show(int $id): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $category = $this->caseCategoryRepository->getById($id, $clinicId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Case category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Case category retrieved successfully',
            'data' => new CaseCategoryResource($category),
        ]);
    }

    /**
     * Update the specified case category.
     */
    public function update(CaseCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->caseCategoryRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Case category updated successfully',
                'data' => new CaseCategoryResource($category),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified case category.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->caseCategoryRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Case category deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();
        
        // Super admin can see all case categories from all clinics
        if ($user->hasRole('super_admin')) {
            return null;
        }
        
        // All other roles see only their clinic's categories
        return $user->clinic_id;
    }
}
