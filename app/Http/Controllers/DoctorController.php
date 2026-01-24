<?php

namespace App\Http\Controllers;

use App\Http\Requests\DoctorRequest;
use App\Http\Resources\UserResource;
use App\Repositories\DoctorRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private DoctorRepository $doctorRepository)
    {
        // Uncomment when permissions are set up
        // $this->middleware('permission:view-doctors')->only(['index', 'show']);
        // $this->middleware('permission:create-doctor')->only(['store']);
        // $this->middleware('permission:edit-doctor')->only(['update']);
        // $this->middleware('permission:delete-doctor')->only(['destroy']);
    }

    /**
     * Display a listing of all doctors.
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
        
        $doctors = $this->doctorRepository->getAllWithFilters($filters, $perPage, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Doctors retrieved successfully',
            'data' => UserResource::collection($doctors),
            'pagination' => [
                'total' => $doctors->total(),
                'per_page' => $doctors->perPage(),
                'current_page' => $doctors->currentPage(),
                'last_page' => $doctors->lastPage(),
                'from' => $doctors->firstItem(),
                'to' => $doctors->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created doctor in storage.
     */
    public function store(DoctorRequest $request): JsonResponse
    {
        try {
            $doctor = $this->doctorRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Doctor created successfully',
                'data' => new UserResource($doctor),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified doctor.
     */
    public function show(int $id): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $doctor = $this->doctorRepository->getById($id, $clinicId);

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor retrieved successfully',
            'data' => new UserResource($doctor),
        ]);
    }

    /**
     * Update the specified doctor in storage.
     */
    public function update(DoctorRequest $request, int $id): JsonResponse
    {
        try {
            $doctor = $this->doctorRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Doctor updated successfully',
                'data' => new UserResource($doctor),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified doctor from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->doctorRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Doctor deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get doctors by clinic.
     */
    public function byClinic(int $clinicId): JsonResponse
    {
        $doctors = $this->doctorRepository->getByClinic($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Doctors retrieved successfully',
            'data' => UserResource::collection($doctors),
        ]);
    }

    /**
     * Get active doctors only.
     */
    public function active(Request $request): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $doctors = $this->doctorRepository->getActive($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Active doctors retrieved successfully',
            'data' => UserResource::collection($doctors),
        ]);
    }

    /**
     * Search doctor by email.
     */
    public function searchByEmail(string $email): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $doctor = $this->doctorRepository->getByEmail($email, $clinicId);

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor found',
            'data' => new UserResource($doctor),
        ]);
    }

    /**
     * Search doctor by phone.
     */
    public function searchByPhone(string $phone): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $doctor = $this->doctorRepository->getByPhone($phone, $clinicId);

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor found',
            'data' => new UserResource($doctor),
        ]);
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();
        
        // Super admin can see all doctors from all clinics
        if ($user->hasRole('super_admin')) {
            return null;
        }
        
        // All other roles see only their clinic
        return $user->clinic_id;
    }
}
