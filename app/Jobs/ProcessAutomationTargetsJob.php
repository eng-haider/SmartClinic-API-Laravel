<?php

namespace App\Jobs;

use App\Models\AutomationTarget;
use App\Services\Messaging\AutomationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAutomationTargetsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
    ) {}

    public function handle(): void
    {
        // Fetch all ready targets (pending + due)
        $targets = AutomationTarget::ready()
            ->with('rule')
            ->limit(100) // process in batches
            ->get();

        foreach ($targets as $target) {
            if (!$target->rule || !$target->rule->is_active) {
                $target->cancel();
                continue;
            }

            SendAutomationMessageJob::dispatch($target->id, $this->tenantId);
        }

        // Generate next periodic targets
        $engine = app(AutomationEngine::class);
        $created = $engine->generatePeriodicTargets();

        if ($created > 0) {
            Log::info("ProcessAutomationTargetsJob: created {$created} periodic targets", [
                'tenant' => $this->tenantId,
            ]);
        }
    }

    public function tags(): array
    {
        return ['automation-processor', 'tenant:' . $this->tenantId];
    }
}
