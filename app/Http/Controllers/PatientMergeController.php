<?php

namespace App\Http\Controllers;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Merging two patient records into one (PatientMergeService), for clinics with the
 * tenant's can_merge_patients flag. Only the secretary role may merge.
 */
class PatientMergeController extends Controller
{
    public function __construct(private PatientMergeService $merges)
    {
    }

    /**
     * Both records side by side, so staff can check before merging.
     */
    public function preview(int $id, int $otherId): JsonResponse
    {
        if ($denied = $this->checkPatients($id, $otherId)) {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'keep' => $this->merges->summary(Patient::find($id)),
                'merge' => $this->merges->summary(Patient::find($otherId)),
            ],
        ]);
    }

    /**
     * Merge patient merge_id into patient {id}, which is kept.
     */
    public function merge(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['merge_id' => 'required|integer']);

        if ($denied = $this->checkPatients($id, $data['merge_id'])) {
            return $denied;
        }

        $patient = $this->merges->merge(Patient::find($id), Patient::find($data['merge_id']));

        return response()->json([
            'success' => true,
            'message' => 'Patients merged',
            'message_ar' => 'تم دمج المراجعين بنجاح',
            'data' => new PatientResource($patient),
        ]);
    }

    private function checkPatients(int $id, int $otherId): ?JsonResponse
    {
        if (!tenant('can_merge_patients')) {
            return response()->json([
                'success' => false,
                'message' => 'Merging patients is not enabled for this clinic',
                'message_ar' => 'ميزة دمج المراجعين غير مفعّلة لهذه العيادة',
            ], 403);
        }

        if (!Auth::user()?->hasRole('secretary')) {
            return response()->json([
                'success' => false,
                'message' => 'Only the secretary can merge patients',
                'message_ar' => 'دمج المراجعين للسكرتارية فقط',
            ], 403);
        }

        if ($id === $otherId) {
            return response()->json([
                'success' => false,
                'message' => 'Choose two different patients',
                'message_ar' => 'اختر مراجعَين مختلفَين',
            ], 422);
        }

        if (Patient::whereKey([$id, $otherId])->count() !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found',
                'message_ar' => 'المراجع غير موجود',
            ], 404);
        }

        return null;
    }
}
