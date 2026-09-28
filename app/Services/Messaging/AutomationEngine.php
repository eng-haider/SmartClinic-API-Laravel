<?php

namespace App\Services\Messaging;

use App\Models\AutomationRule;
use App\Models\AutomationTarget;
use App\Models\CaseModel;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutomationEngine
{
    /**
     * Fire an event trigger and create automation targets for matching rules.
     */
    public function fireTrigger(string $triggerType, array $context): void
    {
        $rules = AutomationRule::active()
            ->forTrigger($triggerType)
            ->get();

        foreach ($rules as $rule) {
            if (!$rule->matchesConditions($context)) {
                continue;
            }

            $this->createTarget($rule, $context);
        }
    }

    /**
     * Create an automation target from a rule and context.
     */
    public function createTarget(AutomationRule $rule, array $context): AutomationTarget
    {
        $scheduledFor = $this->calculateScheduledTime($rule);

        return AutomationTarget::create([
            'automation_rule_id' => $rule->id,
            'patient_id' => $context['patient_id'],
            'case_id' => $context['case_id'] ?? null,
            'scheduled_for' => $scheduledFor,
            'status' => AutomationTarget::STATUS_PENDING,
        ]);
    }

    /**
     * Manually trigger a rule for a specific patient.
     */
    public function triggerManual(
        int $ruleId,
        int $patientId,
        ?int $caseId = null,
        ?Carbon $scheduledFor = null
    ): AutomationTarget {
        $rule = AutomationRule::findOrFail($ruleId);

        return AutomationTarget::create([
            'automation_rule_id' => $rule->id,
            'patient_id' => $patientId,
            'case_id' => $caseId,
            'scheduled_for' => $scheduledFor ?? $this->calculateScheduledTime($rule),
            'status' => AutomationTarget::STATUS_PENDING,
        ]);
    }

    /**
     * Schedule a message at a specific date/time for a patient.
     */
    public function scheduleAt(
        int $ruleId,
        int $patientId,
        Carbon $datetime,
        ?int $caseId = null
    ): AutomationTarget {
        $rule = AutomationRule::findOrFail($ruleId);

        return AutomationTarget::create([
            'automation_rule_id' => $rule->id,
            'patient_id' => $patientId,
            'case_id' => $caseId,
            'scheduled_for' => $datetime,
            'status' => AutomationTarget::STATUS_PENDING,
        ]);
    }

    /**
     * Generate recurring targets for periodic rules.
     * Called by scheduler to create next occurrence.
     */
    public function generatePeriodicTargets(): int
    {
        $periodicRules = AutomationRule::active()
            ->where('is_periodic', true)
            ->whereNotNull('periodic_interval_days')
            ->get();

        $created = 0;

        foreach ($periodicRules as $rule) {
            // Find patients that should receive the next periodic message
            // Get latest target per patient for this rule
            $latestTargets = AutomationTarget::where('automation_rule_id', $rule->id)
                ->whereIn('status', [AutomationTarget::STATUS_SENT, AutomationTarget::STATUS_PENDING])
                ->selectRaw('patient_id, MAX(scheduled_for) as last_scheduled')
                ->groupBy('patient_id')
                ->get();

            foreach ($latestTargets as $lt) {
                $nextSchedule = Carbon::parse($lt->last_scheduled)
                    ->addDays($rule->periodic_interval_days);

                // Only create if next occurrence doesn't already exist
                $exists = AutomationTarget::where('automation_rule_id', $rule->id)
                    ->where('patient_id', $lt->patient_id)
                    ->where('scheduled_for', $nextSchedule)
                    ->exists();

                if (!$exists && $nextSchedule->isFuture()) {
                    AutomationTarget::create([
                        'automation_rule_id' => $rule->id,
                        'patient_id' => $lt->patient_id,
                        'scheduled_for' => $nextSchedule,
                        'status' => AutomationTarget::STATUS_PENDING,
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Cancel all pending targets for a patient (e.g., when patient is deleted).
     */
    public function cancelPendingForPatient(int $patientId): int
    {
        return AutomationTarget::where('patient_id', $patientId)
            ->pending()
            ->update(['status' => AutomationTarget::STATUS_CANCELLED]);
    }

    /**
     * Cancel all pending targets for a specific case.
     */
    public function cancelPendingForCase(int $caseId): int
    {
        return AutomationTarget::where('case_id', $caseId)
            ->pending()
            ->update(['status' => AutomationTarget::STATUS_CANCELLED]);
    }

    /**
     * Calculate the scheduled time based on rule configuration.
     */
    private function calculateScheduledTime(AutomationRule $rule): Carbon
    {
        // Fixed datetime takes priority
        if ($rule->exact_datetime) {
            return Carbon::parse($rule->exact_datetime);
        }

        $delayMinutes = $rule->getDelayInMinutes();

        if ($delayMinutes > 0) {
            return now()->addMinutes($delayMinutes);
        }

        // Immediate
        return now();
    }
}
