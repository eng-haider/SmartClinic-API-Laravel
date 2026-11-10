<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Language\Messages;

/** Appointments for a day or period, optionally one patient's (GET /reservations). */
class ViewAppointmentsFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'list_appointments');
        $today = $this->clock->now();

        [$from, $to] = [$today->toDateString(), $today->toDateString()];
        if ($text = $s->slot('date_text')) {
            $result = $this->clock->resolveDate($text);
            if (!empty($result['date'])) {
                $from = $to = $result['date'];
            }
        } elseif ($s->slot('period') === 'tomorrow') {
            $from = $to = $today->addDay()->toDateString();
        } elseif ($s->slot('period') === 'week') {
            $to = $today->addDays(6)->toDateString();
        } elseif ($s->slot('period') === 'month') {
            $to = $today->addDays(30)->toDateString();
        }

        $patient = null;
        if ($s->slot('patient_id') || $s->slot('patient_name') || $s->slot('patient_phone') || $s->slot('patient_ref') === 'previous') {
            $patient = $this->resolvePatient($s, true);
            if (!$s->slot('date_text') && !$s->slot('period')) {
                $to = $today->addDays(90)->toDateString();
            }
        }

        $rows = $this->read($s, 'list_appointments', array_filter([
            'patient_id' => $patient['id'] ?? null,
            'from_date' => $from,
            'to_date' => $to,
            'per_page' => 100,
        ]))->data([]);

        $range = $from === $to
            ? ($from === $today->toDateString() ? $this->t($s, 'range.today') : Messages::date($from, $s->language))
            : Messages::date($from, $s->language) . ' – ' . Messages::date($to, $s->language);

        if ($rows === []) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_appointments', ['range' => $range]), $s->language);
        }

        $list = array_map(fn ($r) => $this->compactReservation($r) + ['status' => $r['status'] ?? null], $rows);
        usort($list, fn ($a, $b) => [$a['date'], $a['from']] <=> [$b['date'], $b['from']]);

        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'appointments_title', ['range' => $range]), $s->language);
        foreach (array_slice($list, 0, 30) as $r) {
            $when = Messages::time($r['from'], $s->language) . ($from !== $to ? ' — ' . Messages::date($r['date'], $s->language) : '');
            $status = $r['status'] ? ($s->language === 'en' ? ($r['status']['name'] ?? null) : ($r['status']['name_ar'] ?? $r['status']['name'] ?? null)) : null;
            $reply->item($when . ' — ' . ($r['patient_name'] ?? ''), $r['doctor_name'] ? $this->doctorLabel($s, $r['doctor_name']) : null, $status);
        }

        return $reply;
    }
}
