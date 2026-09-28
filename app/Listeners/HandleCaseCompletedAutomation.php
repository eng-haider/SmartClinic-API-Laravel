<?php

namespace App\Listeners;

use App\Events\CaseCompleted;
use App\Models\AutomationRule;
use App\Services\Messaging\AutomationEngine;

class HandleCaseCompletedAutomation
{
    public function __construct(
        private AutomationEngine $automationEngine,
    ) {}

    public function handle(CaseCompleted $event): void
    {
        $case = $event->case;

        $this->automationEngine->fireTrigger(AutomationRule::TRIGGER_CASE_COMPLETED, [
            'patient_id' => $case->patient_id,
            'case_id' => $case->id,
            'status_id' => $case->status_id,
            'category_id' => $case->case_categores_id,
        ]);
    }
}
