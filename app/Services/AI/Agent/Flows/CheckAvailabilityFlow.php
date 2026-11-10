<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Language\DateTimeResolver;
use App\Services\AI\Agent\Language\Messages;
use App\Services\AI\Agent\Nlu\Intent;

/**
 * Free times (GET reservations/available-slots). Each time is offered as a
 * button that starts a booking for it; nothing is booked from here.
 */
class CheckAvailabilityFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'get_available_slots');

        $date = $s->slot('date');
        if (!$date && ($text = $s->slot('date_text'))) {
            $date = $this->resolveDate($s, 'date', 'date_text');
        }
        if (!$date && $s->slot('period') === 'today') {
            $date = $this->clock->now()->toDateString();
        }
        if (!$date && $s->slot('period') === 'tomorrow') {
            $date = $this->clock->now()->addDay()->toDateString();
        }

        if (!$date) {
            $days = $this->read($s, 'get_available_slots', ['days' => 7])->data()['days'] ?? [];
            if ($days === []) {
                return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_free_times', ['date' => '']), $s->language);
            }

            $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'available_days_title'), $s->language);
            foreach ($days as $d) {
                $reply->item(Messages::date($d['date'], $s->language), Messages::time($d['first_free_time'], $s->language), (string) $d['free_times_count']);
            }

            return $this->offer($s, $reply, array_map(fn ($d) => $this->option(Messages::date($d['date'], $s->language), [], [
                'intent' => Intent::CHECK_AVAILABILITY,
                'with' => ['date' => $d['date']],
            ]), $days));
        }

        $day = $this->read($s, 'get_available_slots', ['date' => $date])->data([]);
        if (empty($day['is_open'])) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'clinic_closed', ['date' => Messages::date($date, $s->language)]), $s->language);
        }

        $free = $day['free_times'] ?? [];
        if (($period = $this->clock->resolveTime($s->slot('time_text'))) && ($period['status'] ?? null) === 'period') {
            [$from, $to] = DateTimeResolver::PERIOD_RANGES[$period['period']];
            $free = array_values(array_filter($free, fn ($t) => $t >= $from && $t < $to));
        }

        if ($free === []) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_free_times', ['date' => Messages::date($date, $s->language)]), $s->language);
        }

        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'free_times_title', ['date' => Messages::date($date, $s->language)]), $s->language);
        $reply->item(implode('، ', array_map(fn ($t) => Messages::time($t, $s->language), $free)));

        if ($this->tools->can($s, 'create_appointment')) {
            $this->offer($s, $reply, array_map(fn ($t) => $this->option(Messages::time($t, $s->language), [], [
                'intent' => Intent::BOOK_APPOINTMENT,
                'with' => array_filter(['date' => $date, 'time' => $t, 'patient_name' => $s->slot('patient_name')]),
            ]), array_slice($free, 0, 8)));
        }

        return $reply;
    }
}
