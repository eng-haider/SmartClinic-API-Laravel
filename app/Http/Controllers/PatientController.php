<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private PatientService $patientService)
    {
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
        $patients = $this->patientService->getAllPatients($filters, $perPage);

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
            $patient = $this->patientService->createPatient($request->validated());

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
        $patient = $this->patientService->getPatient($id);

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
            $patient = $this->patientService->updatePatient($id, $request->validated());

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
            $this->patientService->deletePatient($id);

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
        $patient = $this->patientService->searchByPhone($phone);

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
        $patient = $this->patientService->searchByEmail($email);

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
}
