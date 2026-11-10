<?php

namespace App\Services\AI\Agent\Flows;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Language\Messages;
use App\Services\AI\Agent\Language\TextNormalizer;
use Carbon\CarbonImmutable;

/**
 * Patient (search) -> day -> free time (available-slots) -> doctor -> confirm
 * -> re-check the time is still free -> POST /reservations.
 */
class BookAppointmentFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'create_appointment');

        $patient = $this->resolvePatient($s);
        $date = $this->resolveDate($s, 'date', 'date_text');
        $day = $this->openDay($s, $date, 'date', 'date_text');
        $time = $this->resolveTime($s, $day, 'time', 'time_text');
        $doctor = $this->resolveDoctor($s);

        $params = [
            'patient_id' => $patient['id'],
            'doctor_id' => $doctor['id'],
            'reservation_start_date' => $date,
            'reservation_end_date' => $date,
            'reservation_from_time' => $time . ':00',
            'reservation_to_time' => $this->addMinutes($time, (int) ($day['duration'] ?? 30)),
            'notes' => $s->slot('notes'),
        ];

        return $this->confirm($s, 'create_appointment', $params,
            $this->t($s, 'confirm_booking_title'), 'confirm_booking_question', 'confirm_booking_btn',
            [
                ['f.patient', $this->patientLine($patient)],
                ['f.date', Messages::date($date, $s->language)],
                ['f.time', Messages::time($time, $s->language)],
                ['f.doctor', $this->doctorLabel($s, $doctor['name'])],
            ],
            ['date' => $date, 'time' => $time, 'patient_id' => $patient['id'], 'patient_name' => $patient['name'], 'doctor_name' => $doctor['name']],
        );
    }

    public function execute(AgentSession $s, AiAgentAction $action): AgentReply
    {
        $snapshot = $action->snapshot;

        // The time may have been taken since the card was shown
        $day = $this->call($s, 'get_available_slots', ['date' => $snapshot['date']]);
        if (!$day->ok()) {
            $this->actions->finish($action, AiAgentAction::FAILED, $day->status, null, $day->message());

            return $this->apiFailure($s, $day);
        }

        $free = $day->data()['free_times'] ?? [];
        if (!in_array($snapshot['time'], $free, true)) {
            $this->actions->finish($action, AiAgentAction::FAILED, 409, null, 'Time no longer free');
            $s->forget('time', 'time_text');
            $reply = $this->ask($s, 'time_text', $this->t($s, 'slot_taken', ['time' => Messages::time($snapshot['time'], $s->language)]),
                $this->timeOptions($s, $this->nearest($free, $snapshot['time']), 'time'));
            $reply->kind = AgentReply::ERROR;

            return $reply;
        }

        return $this->conclude($s, $action, $this->call($s, 'create_appointment', $action->params, $action));
    }

    public function verify(AgentSession $s, AiAgentAction $action): ?array
    {
        $p = $action->params;
        $r = $this->tools->run($s, 'list_appointments', [
            'patient_id' => $p['patient_id'],
            'from_date' => $p['reservation_start_date'],
            'to_date' => $p['reservation_start_date'],
            'per_page' => 100,
        ]);
        if (!$r->ok()) {
            return null;
        }

        $since = ($action->confirmed_at ?? $action->created_at)->copy()->subMinutes(2);
        foreach ($r->data([]) as $row) {
            if (substr((string) $row['reservation_from_time'], 0, 5) === substr($p['reservation_from_time'], 0, 5)
                && (int) $row['status_id'] !== (int) config('ai_agent.statuses.cancelled', 4)
                && (!isset($row['created_at']) || CarbonImmutable::parse($row['created_at'])->gte($since))) {
                return $row;
            }
        }

        return [];
    }

    public function successReply(AgentSession $s, AiAgentAction $action, array $data): AgentReply
    {
        $snapshot = $action->snapshot;
        $date = $data['reservation_start_date'] ?? $snapshot['date'];
        $time = isset($data['reservation_from_time']) ? substr($data['reservation_from_time'], 0, 5) : $snapshot['time'];

        if (!empty($data['patient']['id'])) {
            $s->setFocusPatient((int) $data['patient']['id'], $data['patient']['name'], $data['patient']['phone'] ?? null);
        }

        return AgentReply::make(AgentReply::SUCCESS, $this->t($s, 'booked'), $s->language)
            ->field('f.reference', isset($data['id']) ? '#' . $data['id'] : null)
            ->field('f.patient', $data['patient']['name'] ?? $snapshot['patient_name'])
            ->field('f.date', Messages::date($date, $s->language))
            ->field('f.time', Messages::time($time, $s->language))
            ->field('f.doctor', $this->doctorLabel($s, $data['doctor']['name'] ?? $snapshot['doctor_name']));
    }

    /**
     * The doctor: the one named, the signed-in doctor, the clinic's only doctor,
     * or the user's pick. Never a guess between several.
     *
     * @return array{id: ?int, name: ?string}
     */
    private function resolveDoctor(AgentSession $s): array
    {
        if ($s->slot('doctor_id') || $s->slot('doctor_none')) {
            return ['id' => $s->slot('doctor_id'), 'name' => $s->slot('doctor_label')];
        }

        $doctors = array_values(array_map(fn ($d) => ['id' => (int) $d['id'], 'name' => $d['name']],
            $this->read($s, 'list_active_doctors')->data([])));
        $none = $this->option($this->t($s, 'no_doctor'), ['doctor_none' => true]);
        $choices = fn (array $list) => array_merge(array_map(
            fn ($d) => $this->option($this->doctorLabel($s, $d['name']), ['doctor_id' => $d['id'], 'doctor_label' => $d['name']]),
            array_slice($list, 0, 8),
        ), [$none]);

        if ($name = $s->slot('doctor_name')) {
            $wanted = $this->bareName($name);
            $matches = array_values(array_filter($doctors, fn ($d) => $wanted !== '' && str_contains($this->bareName($d['name']), $wanted)));
            $s->forget('doctor_name');

            if (count($matches) === 1) {
                $s->setSlots(['doctor_id' => $matches[0]['id'], 'doctor_label' => $matches[0]['name']]);

                return $matches[0];
            }
            if ($matches === []) {
                throw new Halt($this->ask($s, 'choice', $this->t($s, 'doctor_not_found', ['name' => $name]), $choices($doctors)));
            }

            throw new Halt($this->ask($s, 'choice', $this->t($s, 'choose_doctor'), $choices($matches)));
        }

        if ($s->isPlainDoctor()) {
            foreach ($doctors as $d) {
                if ($d['id'] === $s->userId) {
                    $s->setSlots(['doctor_id' => $d['id'], 'doctor_label' => $d['name']]);

                    return $d;
                }
            }
        }

        if (count($doctors) === 1) {
            $s->setSlots(['doctor_id' => $doctors[0]['id'], 'doctor_label' => $doctors[0]['name']]);

            return $doctors[0];
        }
        if ($doctors === []) {
            $s->setSlots(['doctor_none' => true]);

            return ['id' => null, 'name' => null];
        }

        throw new Halt($this->ask($s, 'choice', $this->t($s, 'choose_doctor'), $choices($doctors)));
    }

    private function bareName(string $name): string
    {
        $name = TextNormalizer::normalize($name);
        $name = preg_replace('/^(الدكتور|دكتور|الدكتوره|دكتوره|د\.|د |dr\.?|doctor)\s*/u', '', $name) ?? $name;

        return trim($name);
    }
}
