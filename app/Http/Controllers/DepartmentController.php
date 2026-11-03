<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Repositories\DepartmentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Departments are managed next to case categories, so they share its permissions.
     */
    public function __construct(private DepartmentRepository $departmentRepository)
    {
        $this->middleware('permission:create-case')->only(['store']);
        $this->middleware('permission:edit-case')->only(['update']);
        $this->middleware('permission:delete-case')->only(['destroy']);
    }

    /**
     * Display a listing of departments.
     */
    public function index(Request $request): JsonResponse
    {
        $departments = $this->departmentRepository->getAllWithFilters((int) $request->input('per_page', 100));

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully',
            'data' => DepartmentResource::collection($departments),
            'pagination' => [
                'total' => $departments->total(),
                'per_page' => $departments->perPage(),
                'current_page' => $departments->currentPage(),
                'last_page' => $departments->lastPage(),
                'from' => $departments->firstItem(),
                'to' => $departments->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(DepartmentRequest $request): JsonResponse
    {
        $department = $this->departmentRepository->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => new DepartmentResource($department),
        ], 201);
    }

    /**
     * Display the specified department.
     */
    public function show(int $id): JsonResponse
    {
        $department = $this->departmentRepository->getById($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Department retrieved successfully',
            'data' => new DepartmentResource($department),
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(DepartmentRequest $request, int $id): JsonResponse
    {
        try {
            $department = $this->departmentRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => new DepartmentResource($department),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Remove the specified department.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->departmentRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
