<?php

namespace App\Services\AI\Agent\Flows;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Language\Messages;

/**
 * Finds the appointment, works out the new day and time (an unchanged part is
 * kept), checks the new time is free, confirms, re-checks both, then
 * PUT /reservations/{id}. The current status is sent along because the
 * endpoint resets it to "New" otherwise.
 */
class RescheduleAppointmentFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'reschedule_appointment');
        $r = $this->findAppointment($s);

        $hasNew = $s->slot('new_date') || $s->slot('new_date_text') || $s->slot('new_time') || $s->slot('new_time_text');
        if (!$hasNew) {
            throw new Halt($this->ask($s, 'new_datetime', $this->t($s, 'ask_new_datetime')));
        }

        $newDate = $this->newDate($s, $r);
        $day = $this->openDay($s, $newDate, 'new_date', 'new_date_text');

        // "لباچر" has no time in it: keep the current one
        $timeText = $s->slot('new_time_text');
        if ($timeText && $this->clock->resolveTime($timeText)['status'] === 'none') {
            $s->forget('new_time_text');
        }

        if (!$s->slot('new_time') && !$s->slot('new_time_text')) {
            // Only the day changes: keep the time
            if ($newDate === $r['date']) {
                throw new Halt($this->ask($s, 'new_datetime', $this->t($s, 'same_slot')));
            }
            $s->setSlots(['new_time' => $r['from']]);
        }

        $newTime = $this->resolveTime($s, $day, 'new_time', 'new_time_text');

        if ($newDate === $r['date'] && $newTime === $r['from']) {
            $s->forget('new_date', 'new_time');
            throw new Halt($this->ask($s, 'new_datetime', $this->t($s, 'same_slot')));
        }

        $length = $this->length($r);
        $params = [
            'id' => $r['id'],
            'patient_id' => $r['patient_id'],
            'status_id' => $r['status_id'] ?: (int) config('ai_agent.statuses.new', 1),
            'doctor_id' => $r['doctor_id'],
            'reservation_start_date' => $newDate,
            'reservation_end_date' => $newDate,
            'reservation_from_time' => $newTime . ':00',
            'reservation_to_time' => $length ? $this->addMinutes($newTime, $length) : null,
        ];

        return $this->confirm($s, 'reschedule_appointment', $params,
            $this->t($s, 'confirm_reschedule_title'), 'confirm_reschedule_question', 'confirm_reschedule_btn',
            [
                ['f.patient', $r['patient_name'] . (!empty($r['patient_phone']) ? ' (' . $r['patient_phone'] . ')' : '')],
                ['f.current', Messages::date($r['date'], $s->language) . ' — ' . Messages::time($r['from'], $s->language)],
                ['f.new', Messages::date($newDate, $s->language) . ' — ' . Messages::time($newTime, $s->language)],
                ['f.doctor', $this->doctorLabel($s, $r['doctor_name'] ?? null)],
            ],
            $r + ['new_date' => $newDate, 'new_time' => $newTime],
        );
    }

    public function execute(AgentSession $s, AiAgentAction $action): AgentReply
    {
        $snapshot = $action->snapshot;
        $current = $this->currentReservation($s, $snapshot);

        if (!$current || (int) $current['status_id'] === (int) config('ai_agent.statuses.cancelled', 4)
            || $current['date'] !== $snapshot['date'] || $current['from'] !== $snapshot['from']) {
            $this->actions->finish($action, AiAgentAction::FAILED, 409, null, 'Appointment changed before execution');
            $s->clearDraft();

            return AgentReply::make(AgentReply::ERROR, $this->t($s, 'appointment_gone'), $s->language);
        }

        $day = $this->call($s, 'get_available_slots', ['date' => $snapshot['new_date']]);
        if (!$day->ok()) {
            $this->actions->finish($action, AiAgentAction::FAILED, $day->status, null, $day->message());

            return $this->apiFailure($s, $day);
        }
        $free = $day->data()['free_times'] ?? [];
        if (!in_array($snapshot['new_time'], $free, true)) {
            $this->actions->finish($action, AiAgentAction::FAILED, 409, null, 'Time no longer free');
            $s->forget('new_time', 'new_time_text');
            $reply = $this->ask($s, 'new_time_text', $this->t($s, 'slot_taken', ['time' => Messages::time($snapshot['new_time'], $s->language)]),
                $this->timeOptions($s, $this->nearest($free, $snapshot['new_time']), 'new_time'));
            $reply->kind = AgentReply::ERROR;

            return $reply;
        }

        return $this->conclude($s, $action, $this->call($s, 'reschedule_appointment', $action->params, $action));
    }

    public function verify(AgentSession $s, AiAgentAction $action): ?array
    {
        $snapshot = $action->snapshot;
        try {
            $moved = $this->currentReservation($s, ['id' => $snapshot['id'], 'patient_id' => $snapshot['patient_id'], 'date' => $snapshot['new_date']]);
        } catch (Halt) {
            return null;
        }

        return $moved && $moved['from'] === $snapshot['new_time'] ? $moved : [];
    }

    public function successReply(AgentSession $s, AiAgentAction $action, array $data): AgentReply
    {
        $snapshot = $action->snapshot;

        return AgentReply::make(AgentReply::SUCCESS, $this->t($s, 'appointment_rescheduled'), $s->language)
            ->field('f.patient', $snapshot['patient_name'])
            ->field('f.new', Messages::date($snapshot['new_date'], $s->language) . ' — ' . Messages::time($snapshot['new_time'], $s->language))
            ->field('f.doctor', $this->doctorLabel($s, $snapshot['doctor_name'] ?? null));
    }

    /** The new day: from the user's words when they give one, else the current day. */
    private function newDate(AgentSession $s, array $r): string
    {
        if ($date = $s->slot('new_date')) {
            return $date;
        }

        $text = $s->slot('new_date_text');
        if ($text) {
            $result = $this->clock->resolveDate($text);
            // "خليه الساعة 6" has no day in it: keep the current one
            if ($result['status'] !== 'none') {
                return $this->resolveDate($s, 'new_date', 'new_date_text', 'ask_new_datetime');
            }
            $s->forget('new_date_text');
        }

        $s->setSlots(['new_date' => $r['date']]);

        return $r['date'];
    }

    private function length(array $r): ?int
    {
        if (empty($r['to']) || empty($r['from'])) {
            return null;
        }
        $minutes = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $length = $minutes($r['to']) - $minutes($r['from']);

        return $length > 0 ? $length : null;
    }
}
