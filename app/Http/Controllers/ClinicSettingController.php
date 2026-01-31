<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClinicSettingRequest;
use App\Http\Resources\ClinicSettingResource;
use App\Models\SettingDefinition;
use App\Repositories\ClinicSettingRepository;
use App\Services\ClinicSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * ClinicSettingController
 * 
 * For Doctors to manage their clinic's settings.
 * Doctors can only UPDATE values, not create new setting keys.
 * Setting keys are defined by Super Admin via SettingDefinitionController.
 */
class ClinicSettingController extends Controller
{
    public function __construct(
        private ClinicSettingRepository $clinicSettingRepository,
        private ClinicSettingService $clinicSettingService
    ) {
        $this->middleware('permission:view-clinic-settings')->only(['index', 'show']);
        $this->middleware('permission:edit-clinic-settings')->only(['update', 'updateBulk', 'uploadLogo']);
    }

    /**
     * Display a listing of clinic settings.
     * Auto-syncs any missing settings from definitions.
     */
    public function index(Request $request): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        
        // Sync any missing settings from definitions
        $clinic = \App\Models\Clinic::find($clinicId);
        if ($clinic) {
            $this->clinicSettingService->syncAllDefinitionsToClinic($clinic);
        }
        
        $settings = $this->clinicSettingRepository->getAllByClinicGrouped($clinicId);

        return response()->json([
            'success' => true,
            'message' => 'Clinic settings retrieved successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Display the specified clinic setting.
     */
    public function show(string $key): JsonResponse
    {
        $clinicId = $this->getClinicIdByRole();
        $setting = $this->clinicSettingRepository->getByKey($clinicId, $key);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Clinic setting retrieved successfully',
            'data' => new ClinicSettingResource($setting),
        ]);
    }

    /**
     * Update the value of a clinic setting.
     * Doctors can only update values, not create new keys.
     * New keys are created by Super Admin via SettingDefinitionController.
     */
    public function update(ClinicSettingRequest $request, string $key): JsonResponse
    {
        try {
            $clinicId = $this->getClinicIdByRole();
            
            // Check if setting exists (must be defined by super admin)
            $setting = $this->clinicSettingRepository->getByKey($clinicId, $key);
            
            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found. Only Super Admin can create new setting keys.',
                ], 404);
            }

            // Update only the value
            $setting = $this->clinicSettingRepository->updateValue(
                $clinicId,
                $key,
                $request->input('setting_value')
            );

            return response()->json([
                'success' => true,
                'message' => 'Clinic setting updated successfully',
                'data' => new ClinicSettingResource($setting),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update multiple clinic settings at once.
     * Only updates existing settings, does not create new ones.
     * Settings without a value will be skipped.
     */
    public function updateBulk(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        try {
            $clinicId = $this->getClinicIdByRole();
            $updated = [];
            $skipped = [];

            foreach ($request->input('settings') as $settingData) {
                // Skip settings without a value (null or not provided)
                if (!array_key_exists('value', $settingData) || $settingData['value'] === null) {
                    continue;
                }
                
                $setting = $this->clinicSettingRepository->updateValue(
                    $clinicId,
                    $settingData['key'],
                    $settingData['value']
                );

                if ($setting) {
                    $updated[] = new ClinicSettingResource($setting);
                } else {
                    $skipped[] = $settingData['key'];
                }
            }

            $message = count($updated) . ' settings updated successfully';
            if (!empty($skipped)) {
                $message .= '. Skipped ' . count($skipped) . ' unknown keys: ' . implode(', ', $skipped);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'updated' => $updated,
                    'skipped' => $skipped,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload clinic logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        try {
            $clinicId = $this->getClinicIdByRole();

            // Get the old logo setting to delete old file
            $oldSetting = $this->clinicSettingRepository->getByKey($clinicId, 'logo');
            
            if ($oldSetting && $oldSetting->setting_value) {
                // Delete old logo file
                Storage::disk('public')->delete($oldSetting->setting_value);
            }

            // Store new logo
            $path = $request->file('logo')->store('clinic-logos', 'public');

            // Update setting
            $setting = $this->clinicSettingRepository->updateOrCreate(
                $clinicId,
                'logo',
                [
                    'setting_value' => $path,
                    'setting_type' => 'string',
                    'description' => 'Clinic logo image',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'logo_url' => Storage::url($path),
                    'logo_path' => $path,
                    'setting' => new ClinicSettingResource($setting),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a clinic setting.
     */
    public function destroy(string $key): JsonResponse
    {
        try {
            $clinicId = $this->getClinicIdByRole();
            $setting = $this->clinicSettingRepository->getByKey($clinicId, $key);

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found',
                ], 404);
            }

            // If it's a logo, delete the file
            if ($key === 'logo' && $setting->setting_value) {
                Storage::disk('public')->delete($setting->setting_value);
            }

            $this->clinicSettingRepository->delete($setting->id);

            return response()->json([
                'success' => true,
                'message' => 'Clinic setting deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get clinic ID based on user role.
     */
    private function getClinicIdByRole(): ?int
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }

        return $user->clinic_id;
    }
}
