<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Http\Resources\NoteResource;
use App\Repositories\NoteRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private NoteRepository $noteRepository)
    {
    }

    /**
     * Display a listing of notes.
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
        
        $notes = $this->noteRepository->getAllWithFilters($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => NoteResource::collection($notes),
            'pagination' => [
                'total' => $notes->total(),
                'per_page' => $notes->perPage(),
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'from' => $notes->firstItem(),
                'to' => $notes->lastItem(),
            ],
        ]);
    }

    /**
     * Get notes for a specific noteable (patient, case, etc.)
     */
    public function byNoteable(Request $request, string $noteableType, int $noteableId): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        
        // Convert short type to full class name
        $typeMap = [
            'patient' => 'App\Models\Patient',
            'case' => 'App\Models\CaseModel',
        ];
        
        $fullType = $typeMap[$noteableType] ?? $noteableType;
        
        $notes = $this->noteRepository->getByNoteable($fullType, $noteableId, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => NoteResource::collection($notes),
            'pagination' => [
                'total' => $notes->total(),
                'per_page' => $notes->perPage(),
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'from' => $notes->firstItem(),
                'to' => $notes->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created note.
     */
    public function store(NoteRequest $request): JsonResponse
    {
        try {
            $note = $this->noteRepository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => new NoteResource($note->load(['creator', 'noteable'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified note.
     */
    public function show(int $id): JsonResponse
    {
        $note = $this->noteRepository->getById($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Note retrieved successfully',
            'data' => new NoteResource($note),
        ]);
    }

    /**
     * Update the specified note.
     */
    public function update(NoteRequest $request, int $id): JsonResponse
    {
        try {
            $note = $this->noteRepository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => new NoteResource($note),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified note.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->noteRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
