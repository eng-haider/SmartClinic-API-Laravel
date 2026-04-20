<?php

namespace App\Listeners;

use App\Events\CaseCreated;
use App\Models\AutomationRule;
use App\Services\Messaging\AutomationEngine;

class HandleCaseCreatedAutomation
{
    public function __construct(
        private AutomationEngine $automationEngine,
    ) {}

    public function handle(CaseCreated $event): void
    {
        $case = $event->case;

        $this->automationEngine->fireTrigger(AutomationRule::TRIGGER_CASE_CREATED, [
            'patient_id' => $case->patient_id,
            'case_id' => $case->id,
            'status_id' => $case->status_id,
            'category_id' => $case->case_categores_id,
        ]);
    }
}
