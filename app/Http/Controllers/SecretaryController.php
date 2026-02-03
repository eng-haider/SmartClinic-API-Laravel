<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecretaryRequest;
use App\Http\Resources\SecretaryResource;
use App\Repositories\SecretaryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SecretaryController extends Controller
{
    public function __construct(private SecretaryRepository $secretaryRepository)
    {
        $this->middleware('permission:view-clinic-users')->only(['index', 'show', 'availablePermissions']);
        $this->middleware('permission:create-user')->only(['store']);
        $this->middleware('permission:edit-user')->only(['update', 'updatePermissions', 'toggleStatus']);
        $this->middleware('permission:delete-user')->only(['destroy']);
    }

    /**
     * Display a listing of secretaries in the clinic.
     * 
     * @group Secretary Management
     * 
     * @queryParam search string Filter by name, phone, or email. Example: Sarah
     * @queryParam is_active boolean Filter by active status. Example: true
     * @queryParam per_page integer Items per page. Example: 15
     * @queryParam sort string Sort field. Example: name
     * @queryParam direction string Sort direction (asc/desc). Example: asc
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $filters = [
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
            'sort' => $request->input('sort', 'created_at'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $perPage = $request->input('per_page', 15);
        
        // Multi-tenancy: Database is already isolated by tenant, no need for clinic_id filtering
        $secretaries = $this->secretaryRepository->getAllForClinic(null, $filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Secretaries retrieved successfully',
            'data' => SecretaryResource::collection($secretaries),
            'pagination' => [
                'total' => $secretaries->total(),
                'per_page' => $secretaries->perPage(),
                'current_page' => $secretaries->currentPage(),
                'last_page' => $secretaries->lastPage(),
                'from' => $secretaries->firstItem(),
                'to' => $secretaries->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created secretary.
     * 
     * @group Secretary Management
     * 
     * @bodyParam name string required Secretary's full name. Example: Sarah Johnson
     * @bodyParam email string required Secretary's email address. Example: sarah@smartclinic.com
     * @bodyParam phone string required Secretary's phone number. Example: 07701234567
     * @bodyParam password string required Secretary's password (min 8 characters). Example: 12345678
     * @bodyParam is_active boolean Secretary's active status. Default: true. Example: true
     * @bodyParam permissions array Custom permissions for this secretary. Example: ["create-patient", "view-clinic-patients"]
     */
    public function store(SecretaryRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $secretary = $this->secretaryRepository->create(
                $request->validated(),
                $user->clinic_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Secretary created successfully',
                'data' => new SecretaryResource($secretary),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create secretary',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified secretary.
     * 
     * @group Secretary Management
     * 
     * @urlParam secretary integer required Secretary ID. Example: 1
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $secretary = $this->secretaryRepository->findInClinic($id, $user->clinic_id);

        if (!$secretary) {
            return response()->json([
                'success' => false,
                'message' => 'Secretary not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Secretary details retrieved successfully',
            'data' => new SecretaryResource($secretary),
        ]);
    }

    /**
     * Update the specified secretary.
     * 
     * @group Secretary Management
     * 
     * @urlParam secretary integer required Secretary ID. Example: 1
     * @bodyParam name string required Secretary's full name. Example: Sarah Johnson
     * @bodyParam email string required Secretary's email address. Example: sarah@smartclinic.com
     * @bodyParam phone string required Secretary's phone number. Example: 07701234567
     * @bodyParam password string Secretary's new password (optional). Example: newpassword123
     * @bodyParam is_active boolean Secretary's active status. Example: true
     * @bodyParam permissions array Custom permissions for this secretary. Example: ["create-patient", "view-clinic-bills"]
     */
    public function update(SecretaryRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $secretary = $this->secretaryRepository->findInClinic($id, $user->clinic_id);

            if (!$secretary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secretary not found',
                ], 404);
            }

            // Update basic info
            $secretary = $this->secretaryRepository->update($secretary, $request->validated());

            // Update permissions if provided
            if ($request->has('permissions')) {
                $secretary = $this->secretaryRepository->updatePermissions($secretary, $request->permissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Secretary updated successfully',
                'data' => new SecretaryResource($secretary),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update secretary',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified secretary.
     * 
     * @group Secretary Management
     * 
     * @urlParam secretary integer required Secretary ID. Example: 1
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $secretary = $this->secretaryRepository->findInClinic($id, $user->clinic_id);

            if (!$secretary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secretary not found',
                ], 404);
            }

            $this->secretaryRepository->delete($secretary);

            return response()->json([
                'success' => true,
                'message' => 'Secretary deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete secretary',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update secretary's permissions only.
     * 
     * @group Secretary Management
     * 
     * @urlParam secretary integer required Secretary ID. Example: 1
     * @bodyParam permissions array required Array of permission names. Example: ["create-patient", "edit-patient", "view-clinic-bills"]
     */
    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $availablePermissions = array_keys(array_merge(...array_values($this->secretaryRepository->getAvailablePermissions())));
        
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => [
                'string',
                Rule::in($availablePermissions),
            ],
        ]);

        try {
            $user = Auth::user();
            
            $secretary = $this->secretaryRepository->findInClinic($id, $user->clinic_id);

            if (!$secretary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secretary not found',
                ], 404);
            }

            $secretary = $this->secretaryRepository->updatePermissions($secretary, $request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Secretary permissions updated successfully',
                'data' => new SecretaryResource($secretary),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle secretary's active status.
     * 
     * @group Secretary Management
     * 
     * @urlParam secretary integer required Secretary ID. Example: 1
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $secretary = $this->secretaryRepository->findInClinic($id, $user->clinic_id);

            if (!$secretary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secretary not found',
                ], 404);
            }

            $secretary = $this->secretaryRepository->toggleStatus($secretary);

            return response()->json([
                'success' => true,
                'message' => 'Secretary status updated successfully',
                'data' => [
                    'id' => $secretary->id,
                    'name' => $secretary->name,
                    'is_active' => $secretary->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available permissions that can be assigned to secretaries.
     * 
     * @group Secretary Management
     */
    public function availablePermissions(): JsonResponse
    {
        $grouped = $this->secretaryRepository->getAvailablePermissions();
        $base = $this->secretaryRepository->getBaseRolePermissions();

        return response()->json([
            'success' => true,
            'message' => 'Available permissions retrieved successfully',
            'data' => [
                'grouped_permissions' => $grouped,
                'base_role_permissions' => $base,
                'all_permissions' => $this->secretaryRepository->getAllPermissions(),
                'note' => 'Secretaries have no default permissions. All permissions must be assigned individually by clinic_super_doctor from the available list.',
            ],
        ]);
    }
}
