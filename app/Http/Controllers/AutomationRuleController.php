<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Services\Messaging\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt');
    }

    /**
     * List all automation rules.
     */
    public function index(): JsonResponse
    {
        $rules = AutomationRule::with('template')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Create a new automation rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|string|in:' . implode(',', AutomationRule::TRIGGERS),
            'is_active' => 'boolean',
            'delay_minutes' => 'nullable|integer|min:0',
            'delay_days' => 'nullable|integer|min:0',
            'exact_datetime' => 'nullable|date',
            'is_periodic' => 'boolean',
            'periodic_interval_days' => 'nullable|integer|min:1',
            'template_key' => 'required|string|max:255',
            'channel' => 'string|in:whatsapp,email,push',
            'conditions_json' => 'nullable|array',
        ]);

        $validated['created_by'] = auth()->id();

        $rule = AutomationRule::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Automation rule created',
            'data' => $rule->load('template'),
        ], 201);
    }

    /**
     * Show a single automation rule.
     */
    public function show(int $id): JsonResponse
    {
        $rule = AutomationRule::with(['template', 'targets' => function ($q) {
            $q->latest()->limit(20);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rule,
        ]);
    }

    /**
     * Update an automation rule.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'trigger_type' => 'string|in:' . implode(',', AutomationRule::TRIGGERS),
            'is_active' => 'boolean',
            'delay_minutes' => 'nullable|integer|min:0',
            'delay_days' => 'nullable|integer|min:0',
            'exact_datetime' => 'nullable|date',
            'is_periodic' => 'boolean',
            'periodic_interval_days' => 'nullable|integer|min:1',
            'template_key' => 'string|max:255',
            'channel' => 'string|in:whatsapp,email,push',
            'conditions_json' => 'nullable|array',
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Automation rule updated',
            'data' => $rule->fresh()->load('template'),
        ]);
    }

    /**
     * Delete an automation rule.
     */
    public function destroy(int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Automation rule deleted',
        ]);
    }

    /**
     * Manually trigger a rule for a patient.
     */
    public function trigger(Request $request, int $id, AutomationEngine $engine): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'case_id' => 'nullable|integer|exists:cases,id',
            'scheduled_for' => 'nullable|date',
        ]);

        $scheduledFor = isset($validated['scheduled_for'])
            ? Carbon::parse($validated['scheduled_for'])
            : null;

        $target = $engine->triggerManual(
            $id,
            $validated['patient_id'],
            $validated['case_id'] ?? null,
            $scheduledFor,
        );

        return response()->json([
            'success' => true,
            'message' => 'Automation triggered',
            'data' => $target->load(['rule', 'patient']),
        ], 201);
    }
}
