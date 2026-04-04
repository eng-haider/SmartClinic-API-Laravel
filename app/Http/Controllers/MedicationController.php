<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicationRequest;
use App\Http\Resources\MedicationResource;
use App\Repositories\MedicationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function __construct(private MedicationRepository $repository)
    {
    }

    /**
     * Display all medications for the authenticated clinic.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $medications = $this->repository->getAll($search);

        return response()->json(
            MedicationResource::collection($medications)
        );
    }

    /**
     * Store a new medication, or return the existing one if name already exists.
     */
    public function store(MedicationRequest $request): JsonResponse
    {
        $name = $request->validated()['name'];

        $existing = $this->repository->findByName($name);

        if ($existing) {
            return response()->json(new MedicationResource($existing), 200);
        }

        $medication = $this->repository->create($name);

        return response()->json(new MedicationResource($medication), 201);
    }

    /**
     * Delete a medication owned by the authenticated clinic.
     */
    public function destroy(int $id): JsonResponse
    {
        $medication = $this->repository->findById($id);

        if (!$medication) {
            return response()->json([
                'success' => false,
                'message' => 'Medication not found',
            ], 404);
        }

        $this->repository->delete($medication);

        return response()->json(null, 204);
    }
}
