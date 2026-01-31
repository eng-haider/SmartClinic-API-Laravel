<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingDefinitionRequest;
use App\Http\Resources\SettingDefinitionResource;
use App\Models\SettingDefinition;
use App\Services\ClinicSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SettingDefinitionController
 * 
 * For Super Admin only.
 * Manages the master list of setting keys that all clinics will have.
 */
class SettingDefinitionController extends Controller
{
    public function __construct(
        private ClinicSettingService $clinicSettingService
    ) {
        // Only super_admin can manage setting definitions
        $this->middleware('permission:manage-setting-definitions');
    }

    /**
     * Get all setting definitions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SettingDefinition::query();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Order results
        $definitions = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'message' => 'Setting definitions retrieved successfully',
            'data' => SettingDefinitionResource::collection($definitions),
            'meta' => [
                'categories' => SettingDefinition::categories(),
                'types' => SettingDefinition::types(),
            ],
        ]);
    }

    /**
     * Create a new setting definition.
     * This will automatically create this setting for ALL existing clinics.
     */
    public function store(SettingDefinitionRequest $request): JsonResponse
    {
        try {
            $definition = SettingDefinition::create($request->validated());

            // Sync to all existing clinics
            $syncedCount = $this->clinicSettingService->syncDefinitionToAllClinics($definition);

            return response()->json([
                'success' => true,
                'message' => "Setting definition created and synced to {$syncedCount} clinics",
                'data' => new SettingDefinitionResource($definition),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get a single setting definition.
     */
    public function show(int $id): JsonResponse
    {
        $definition = SettingDefinition::find($id);

        if (!$definition) {
            return response()->json([
                'success' => false,
                'message' => 'Setting definition not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting definition retrieved successfully',
            'data' => new SettingDefinitionResource($definition),
        ]);
    }

    /**
     * Update a setting definition.
     * Updates metadata for all clinics.
     */
    public function update(SettingDefinitionRequest $request, int $id): JsonResponse
    {
        $definition = SettingDefinition::find($id);

        if (!$definition) {
            return response()->json([
                'success' => false,
                'message' => 'Setting definition not found',
            ], 404);
        }

        try {
            $definition->update($request->validated());

            // Update metadata in all clinic settings
            $updatedCount = $this->clinicSettingService->updateSettingMetadataFromDefinition($definition);

            return response()->json([
                'success' => true,
                'message' => "Setting definition updated. {$updatedCount} clinic settings updated.",
                'data' => new SettingDefinitionResource($definition),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a setting definition.
     * Optionally removes from all clinics.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $definition = SettingDefinition::find($id);

        if (!$definition) {
            return response()->json([
                'success' => false,
                'message' => 'Setting definition not found',
            ], 404);
        }

        try {
            $settingKey = $definition->setting_key;
            $removedCount = 0;

            // If requested, also remove from all clinics
            if ($request->boolean('remove_from_clinics', false)) {
                $removedCount = $this->clinicSettingService->removeSettingFromAllClinics($settingKey);
            }

            $definition->delete();

            return response()->json([
                'success' => true,
                'message' => $removedCount > 0 
                    ? "Setting definition deleted. Removed from {$removedCount} clinics."
                    : 'Setting definition deleted. Clinic settings preserved.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SettingDefinition::categories(),
        ]);
    }

    /**
     * Get available types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SettingDefinition::types(),
        ]);
    }

    /**
     * Sync all definitions to all clinics.
     * Useful for ensuring all clinics have all settings.
     */
    public function syncAll(): JsonResponse
    {
        try {
            $definitions = SettingDefinition::active()->get();
            $totalSynced = 0;

            foreach ($definitions as $definition) {
                $totalSynced += $this->clinicSettingService->syncDefinitionToAllClinics($definition);
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$totalSynced} settings across all clinics",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
