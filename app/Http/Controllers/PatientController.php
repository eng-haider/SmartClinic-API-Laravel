<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Repositories\PatientRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private PatientRepository $patientRepository)
    {
        $this->middleware('permission:view-clinic-patients')->only(['index']);
        $this->middleware('permission:create-patient')->only(['store']);
        $this->middleware('permission:view-clinic-patients')->only(['show']);
        $this->middleware('permission:edit-patient')->only(['update']);
        $this->middleware('permission:delete-patient')->only(['destroy']);
        $this->middleware('permission:search-patient')->only(['searchByPhone', 'searchByEmail']);
    }

    /**
     * Display a listing of all patients.
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
        
        $patients = $this->patientRepository->getAllWithFilters($filters, $perPage, $clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Patients retrieved successfully',
            'data' => PatientResource::collection($patients),
            'pagination' => [
                'total' => $patients->total(),
                'per_page' => $patients->perPage(),
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'from' => $patients->firstItem(),
                'to' => $patients->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created patient in storage.
     */
    public function store(PatientRequest $request): JsonResponse
    {
        try {
            $patient = $this->patientRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Patient created successfully',
                'data' => new PatientResource($patient),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified patient.
     */
    public function show(int $id): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $patient = $this->patientRepository->getById($id, $clinicId);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Patient retrieved successfully',
            'data' => new PatientResource($patient),
        ]);
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(PatientRequest $request, int $id): JsonResponse
    {
        try {
            $patient = $this->patientRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Patient updated successfully',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->patientRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Patient deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Search patient by phone.
     */
    public function searchByPhone(string $phone): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $patient = $this->patientRepository->getByPhone($phone, $clinicId);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Patient found',
            'data' => new PatientResource($patient),
        ]);
    }

    /**
     * Search patient by email.
     */
    public function searchByEmail(string $email): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $patient = $this->patientRepository->getByEmail($email, $clinicId);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Patient found',
            'data' => new PatientResource($patient),
        ]);
    }

    /**
     * Get clinic ID based on user role.
     * Super admin sees all, others see only their clinic.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();
        
        // Super admin can see all patients from all clinics
        if ($user->hasRole('super_admin')) {
            return null;
        }
        
        // All other roles (clinic_super_doctor, doctor, secretary) see only their clinic
        return $user->clinic_id;
    }
}
