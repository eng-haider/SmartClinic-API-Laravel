<?php

namespace App\Http\Controllers;

use App\Models\AutomationTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationTargetController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt');
    }

    /**
     * List automation targets with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AutomationTarget::with(['rule', 'patient', 'message']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('rule_id')) {
            $query->where('automation_rule_id', $request->input('rule_id'));
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        $perPage = $request->input('per_page', 20);
        $targets = $query->orderBy('scheduled_for', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $targets->items(),
            'pagination' => [
                'total' => $targets->total(),
                'per_page' => $targets->perPage(),
                'current_page' => $targets->currentPage(),
                'last_page' => $targets->lastPage(),
            ],
        ]);
    }

    /**
     * Show single target.
     */
    public function show(int $id): JsonResponse
    {
        $target = AutomationTarget::with(['rule', 'patient', 'message', 'caseModel'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $target,
        ]);
    }

    /**
     * Cancel a pending target.
     */
    public function cancel(int $id): JsonResponse
    {
        $target = AutomationTarget::findOrFail($id);

        if ($target->status !== AutomationTarget::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending targets can be cancelled',
            ], 422);
        }

        $target->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Automation target cancelled',
            'data' => $target->fresh(),
        ]);
    }
}
