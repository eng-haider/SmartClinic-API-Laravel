<?php

namespace App\Services\AI\Agent\Flows;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Language\Messages;

/**
 * Finds the upcoming appointment (GET /reservations), confirms, checks it is
 * still active, then PATCH /reservations/{id}/status to Cancelled. The record
 * is kept, as when staff cancel from the schedule.
 */
class CancelAppointmentFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'cancel_appointment');
        $r = $this->findAppointment($s);

        return $this->confirm($s, 'cancel_appointment', ['id' => $r['id']],
            $this->t($s, 'confirm_cancel_title'), 'confirm_cancel_question', 'confirm_cancel_btn',
            $this->fields($s, $r),
            $r,
        );
    }

    public function execute(AgentSession $s, AiAgentAction $action): AgentReply
    {
        $current = $this->currentReservation($s, $action->snapshot);
        if (!$current || (int) $current['status_id'] === (int) config('ai_agent.statuses.cancelled', 4)) {
            $this->actions->finish($action, AiAgentAction::FAILED, 409, null, 'Appointment changed before execution');
            $s->clearDraft();

            return AgentReply::make(AgentReply::ERROR, $this->t($s, 'appointment_gone'), $s->language);
        }

        return $this->conclude($s, $action, $this->call($s, 'cancel_appointment', $action->params, $action));
    }

    public function verify(AgentSession $s, AiAgentAction $action): ?array
    {
        try {
            $current = $this->currentReservation($s, $action->snapshot);
        } catch (Halt) {
            return null;
        }

        return $current && (int) $current['status_id'] === (int) config('ai_agent.statuses.cancelled', 4) ? $current : [];
    }

    public function successReply(AgentSession $s, AiAgentAction $action, array $data): AgentReply
    {
        $reply = AgentReply::make(AgentReply::SUCCESS, $this->t($s, 'appointment_cancelled'), $s->language);
        foreach ($this->fields($s, $action->snapshot) as [$label, $value]) {
            $reply->field($label, $value);
        }

        return $reply;
    }

    private function fields(AgentSession $s, array $r): array
    {
        return [
            ['f.patient', $r['patient_name'] . (!empty($r['patient_phone']) ? ' (' . $r['patient_phone'] . ')' : '')],
            ['f.date', Messages::date($r['date'], $s->language)],
            ['f.time', Messages::time($r['from'], $s->language)],
            ['f.doctor', $this->doctorLabel($s, $r['doctor_name'] ?? null)],
        ];
    }
}
