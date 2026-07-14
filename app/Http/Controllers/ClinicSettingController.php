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
        $settings = $this->clinicSettingRepository->getAllByClinicGrouped();

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
        $setting = $this->clinicSettingRepository->getByKey($key);

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
            // Check if setting exists (must be defined by super admin)
            $setting = $this->clinicSettingRepository->getByKey($key);
            
            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found. Only Super Admin can create new setting keys.',
                ], 404);
            }

            // Update only the value
            $setting = $this->clinicSettingRepository->updateValue(
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
     * Creates the setting if it doesn't exist yet, otherwise updates it.
     *
     * Accepts either shape:
     *   { "settings": [ { "key": ..., "value": ..., "type": ... }, ... ] }
     *   [ { "key": ..., "value": ..., "type": ... }, ... ]   // bare array
     */
    public function updateBulk(Request $request): JsonResponse
    {
        // Support both a wrapped { "settings": [...] } payload and a bare top-level array.
        $settings = $request->input('settings');
        if (!is_array($settings)) {
            $all = $request->all();
            $settings = array_is_list($all) ? $all : [];
        }

        if (empty($settings)) {
            return response()->json([
                'success' => false,
                'message' => 'No settings provided. Send an array of { key, value, type } items.',
            ], 422);
        }

        try {
            $updated = [];
            $skipped = [];

            foreach ($settings as $settingData) {
                // Skip malformed items or items without a key.
                if (!is_array($settingData) ||
                    !array_key_exists('key', $settingData) ||
                    $settingData['key'] === null ||
                    $settingData['key'] === '' ||
                    !array_key_exists('value', $settingData)) {
                    $skipped[] = is_array($settingData) ? ($settingData['key'] ?? null) : null;
                    continue;
                }

                $setting = $this->clinicSettingRepository->updateOrCreate(
                    $settingData['key'],
                    [
                        'setting_value' => $settingData['value'],
                        'setting_type' => $settingData['type'] ?? 'string',
                    ]
                );

                $updated[] = new ClinicSettingResource($setting);
            }

            $skipped = array_values(array_filter($skipped));

            $message = count($updated) . ' settings updated successfully';
            if (!empty($skipped)) {
                $message .= '. Skipped ' . count($skipped) . ' invalid items: ' . implode(', ', $skipped);
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
            // Get the old logo setting to delete old file
            $oldSetting = $this->clinicSettingRepository->getByKey('logo');
            
            if ($oldSetting && $oldSetting->setting_value) {
                // Delete old logo file
                Storage::disk('public')->delete($oldSetting->setting_value);
            }

            // Store new logo
            $path = $request->file('logo')->store('clinic-logos', 'public');

            // Update setting
            $setting = $this->clinicSettingRepository->updateOrCreate(
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
            $setting = $this->clinicSettingRepository->getByKey($key);

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
}
