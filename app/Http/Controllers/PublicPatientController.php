<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicPatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPatientController extends Controller
{
    /**
     * Get patient public profile by token.
     *
     * This endpoint is publicly accessible (no authentication required).
     * Used for QR code scanning to view patient information.
     *
     * @param string $token
     * @return JsonResponse
     */
    public function show(string $token): JsonResponse
    {
        $patient = Patient::findByPublicToken($token);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found or not publicly accessible.',
            ], 404);
        }

        // Load relationships for public display
        $patient->load([
            'doctor:id,name',
            'clinic:id,name,address,whatsapp_phone',
            'cases' => function ($query) {
                $query->with(['category:id,name,name_en,name_ar', 'status:id,name_en,name_ar,color'])
                    ->select('id', 'patient_id', 'case_categores_id', 'status_id', 'tooth_num', 'notes', 'created_at');
            },
            'images' => function ($query) {
                $query->select('id', 'path', 'disk', 'type', 'alt_text', 'imageable_id', 'imageable_type', 'created_at')
                    ->orderBy('created_at', 'desc');
            },
            'reservations' => function ($query) {
                $query->select('id', 'patient_id', 'doctor_id', 'status_id', 'reservation_start_date', 'reservation_from_time', 'notes', 'created_at')
                    ->where('reservation_start_date', '>=', now()->toDateString())
                    ->orderBy('reservation_start_date', 'asc')
                    ->with(['doctor:id,name', 'status:id,name_en,name_ar,color']);
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => new PublicPatientResource($patient),
        ]);
    }

    /**
     * Get patient cases by public token.
     *
     * @param string $token
     * @return JsonResponse
     */
    public function cases(string $token): JsonResponse
    {
        $patient = Patient::findByPublicToken($token);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found or not publicly accessible.',
            ], 404);
        }

        $cases = $patient->cases()
            ->with(['category:id,name,name_en,name_ar', 'status:id,name_en,name_ar,color', 'doctor:id,name'])
            ->select('id', 'patient_id', 'doctor_id', 'case_categores_id', 'status_id', 'tooth_num', 'notes', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cases->map(function ($case) {
                return [
                    'id' => $case->id,
                    'tooth_num' => $case->tooth_num,
                    'notes' => $case->notes,
                    'category' => $case->category ? [
                        'id' => $case->category->id,
                        'name' => $case->category->name,
                        'name_en' => $case->category->name_en,
                        'name_ar' => $case->category->name_ar,
                    ] : null,
                    'status' => $case->status ? [
                        'id' => $case->status->id,
                        'name_en' => $case->status->name_en,
                        'name_ar' => $case->status->name_ar,
                        'color' => $case->status->color,
                    ] : null,
                    'doctor' => $case->doctor ? [
                        'id' => $case->doctor->id,
                        'name' => $case->doctor->name,
                    ] : null,
                    'created_at' => $case->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Get patient images by public token.
     *
     * @param string $token
     * @return JsonResponse
     */
    public function images(string $token): JsonResponse
    {
        $patient = Patient::findByPublicToken($token);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found or not publicly accessible.',
            ], 404);
        }

        $images = $patient->images()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->url,
                    'type' => $image->type,
                    'alt_text' => $image->alt_text,
                    'created_at' => $image->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Get patient upcoming reservations by public token.
     *
     * @param string $token
     * @return JsonResponse
     */
    public function reservations(string $token): JsonResponse
    {
        $patient = Patient::findByPublicToken($token);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found or not publicly accessible.',
            ], 404);
        }

        $reservations = $patient->reservations()
            ->with(['doctor:id,name', 'status:id,name_en,name_ar,color'])
            ->where('reservation_start_date', '>=', now()->toDateString())
            ->orderBy('reservation_start_date', 'asc')
            ->orderBy('reservation_from_time', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'date' => $reservation->reservation_start_date?->format('Y-m-d'),
                    'time' => $reservation->reservation_from_time,
                    'status' => $reservation->status ? [
                        'name_en' => $reservation->status->name_en,
                        'name_ar' => $reservation->status->name_ar,
                        'color' => $reservation->status->color,
                    ] : null,
                    'notes' => $reservation->notes,
                    'doctor' => $reservation->doctor ? [
                        'id' => $reservation->doctor->id,
                        'name' => $reservation->doctor->name,
                    ] : null,
                    'created_at' => $reservation->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }
}
