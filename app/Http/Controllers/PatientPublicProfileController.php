<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPublicProfileController extends Controller
{
    /**
     * Get the public profile settings for a patient.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getPublicProfile(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'public_token' => $patient->public_token,
                'is_public_profile_enabled' => $patient->is_public_profile_enabled,
                'public_profile_url' => $patient->public_profile_url,
                'qr_code_content' => $patient->public_profile_url,
            ],
        ]);
    }

    /**
     * Enable public profile for a patient.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function enablePublicProfile(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $patient->update(['is_public_profile_enabled' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Public profile enabled successfully.',
            'data' => [
                'patient_id' => $patient->id,
                'public_token' => $patient->public_token,
                'is_public_profile_enabled' => true,
                'public_profile_url' => $patient->public_profile_url,
                'qr_code_content' => $patient->public_profile_url,
            ],
        ]);
    }

    /**
     * Disable public profile for a patient.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function disablePublicProfile(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $patient->update(['is_public_profile_enabled' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Public profile disabled successfully.',
            'data' => [
                'patient_id' => $patient->id,
                'is_public_profile_enabled' => false,
            ],
        ]);
    }

    /**
     * Regenerate public token for a patient.
     * This invalidates the old QR code and generates a new one.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function regenerateToken(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $newToken = $patient->regeneratePublicToken();

        return response()->json([
            'success' => true,
            'message' => 'Public token regenerated successfully. Old QR codes will no longer work.',
            'data' => [
                'patient_id' => $patient->id,
                'public_token' => $newToken,
                'public_profile_url' => $patient->public_profile_url,
                'qr_code_content' => $patient->public_profile_url,
            ],
        ]);
    }

    /**
     * Get QR code data for a patient.
     * Returns the URL that should be encoded in the QR code.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getQrCodeData(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        if (!$patient->is_public_profile_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Public profile is not enabled for this patient. Enable it first to generate QR code.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'qr_code_content' => $patient->public_profile_url,
                'public_token' => $patient->public_token,
                'instructions' => 'Use this URL to generate a QR code. When scanned, it will redirect to the patient\'s public profile.',
            ],
        ]);
    }
}
