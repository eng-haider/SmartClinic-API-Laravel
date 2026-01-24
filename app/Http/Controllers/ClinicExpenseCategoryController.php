<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClinicExpenseCategoryRequest;
use App\Http\Resources\ClinicExpenseCategoryResource;
use App\Repositories\ClinicExpenseCategoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicExpenseCategoryController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private ClinicExpenseCategoryRepository $repository)
    {
        // $this->middleware('permission:view-clinic-expenses')->only(['index', 'show']);
        // $this->middleware('permission:create-expense')->only(['store']);
        // $this->middleware('permission:edit-expense')->only(['update']);
        // $this->middleware('permission:delete-expense')->only(['destroy']);
    }

    /**
     * Display a listing of expense categories.
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
        
        $categories = $this->repository->getAllWithFilters($filters, $perPage, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Expense categories retrieved successfully',
            'data' => ClinicExpenseCategoryResource::collection($categories),
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
     * Store a newly created expense category.
     */
    public function store(ClinicExpenseCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->repository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Expense category created successfully',
                'data' => new ClinicExpenseCategoryResource($category),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified expense category.
     */
    public function show(int $id): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $category = $this->repository->getById($id, $clinicId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense category retrieved successfully',
            'data' => new ClinicExpenseCategoryResource($category),
        ]);
    }

    /**
     * Update the specified expense category.
     */
    public function update(ClinicExpenseCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->repository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Expense category updated successfully',
                'data' => new ClinicExpenseCategoryResource($category),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified expense category.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->repository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Expense category deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get active categories for a clinic.
     */
    public function active(Request $request): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        
        if (!$clinicId) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic ID is required',
            ], 400);
        }

        $categories = $this->repository->getActiveByClinic($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Active expense categories retrieved successfully',
            'data' => ClinicExpenseCategoryResource::collection($categories),
        ]);
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();
        
        // If user is super admin, return null to see all
        if ($user && $user->hasRole('super-admin')) {
            return null;
        }
        
        // Otherwise, return the user's clinic_id
        return $user?->clinic_id;
    }
}
