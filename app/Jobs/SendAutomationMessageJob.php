<?php

namespace App\Jobs;

use App\Models\AutomationTarget;
use App\Services\Messaging\MessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAutomationMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(
        public int $automationTargetId,
        public string $tenantId,
    ) {}

    public function handle(MessageService $messageService): void
    {
        $target = AutomationTarget::with(['rule', 'patient'])->find($this->automationTargetId);

        if (!$target) {
            Log::warning('SendAutomationMessageJob: target not found', [
                'target_id' => $this->automationTargetId,
            ]);
            return;
        }

        if ($target->status !== AutomationTarget::STATUS_PENDING) {
            return; // Already processed or cancelled
        }

        if (!$target->rule || !$target->rule->is_active) {
            $target->cancel();
            return;
        }

        if (!$target->patient || !$target->patient->phone) {
            $target->markFailed('Patient has no phone number');
            return;
        }

        try {
            $messageService->sendForTarget($target);
        } catch (\Throwable $e) {
            Log::error('SendAutomationMessageJob failed', [
                'target_id' => $this->automationTargetId,
                'error' => $e->getMessage(),
            ]);

            $target->markFailed($e->getMessage());
        }
    }

    public function tags(): array
    {
        return [
            'automation',
            'tenant:' . $this->tenantId,
            'target:' . $this->automationTargetId,
        ];
    }
}
