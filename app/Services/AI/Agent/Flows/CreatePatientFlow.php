<?php

namespace App\Services\AI\Agent\Flows;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Nlu\Intent;

/**
 * POST /patients after confirmation. Warns about a same-name patient. When it
 * was started from "patient not found" while booking, the booking resumes with
 * the new patient.
 */
class CreatePatientFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'create_patient');

        $name = trim((string) ($s->slot('name') ?: $s->slot('patient_name')));
        if ($name === '') {
            throw new Halt($this->ask($s, 'name', $this->t($s, 'ask_patient_name')));
        }
        $phone = $s->slot('phone') ?: $s->slot('patient_phone');
        $age = $s->slot('age');

        $notes = [];
        if ($this->tools->can($s, 'search_patients')) {
            $existing = $this->read($s, 'search_patients', ['search' => $name, 'per_page' => 5])->data([]);
            if ($existing !== []) {
                $notes[] = $this->t($s, 'duplicate_patient_warning', [
                    'names' => implode('، ', array_map(fn ($p) => $p['name'] . (!empty($p['phone']) ? ' ' . $p['phone'] : ''), $existing)),
                ]);
            }
        }

        return $this->confirm($s, 'create_patient',
            ['name' => $name, 'phone' => $phone, 'age' => $age ? (int) $age : null],
            $this->t($s, 'confirm_create_patient_title'), 'confirm_create_patient_question', 'confirm_create_patient_btn',
            [['f.name', $name], ['f.phone', $phone]],
            ['name' => $name],
            $notes,
        );
    }

    public function execute(AgentSession $s, AiAgentAction $action): AgentReply
    {
        return $this->conclude($s, $action, $this->call($s, 'create_patient', $action->params, $action));
    }

    public function verify(AgentSession $s, AiAgentAction $action): ?array
    {
        $r = $this->tools->run($s, 'search_patients', ['search' => $action->params['name'], 'per_page' => 20]);
        if (!$r->ok()) {
            return null;
        }
        $since = ($action->confirmed_at ?? $action->created_at)->copy()->subMinutes(2);
        foreach ($r->data([]) as $p) {
            if (!empty($p['created_at']) && \Carbon\CarbonImmutable::parse($p['created_at'])->gte($since)) {
                return $p;
            }
        }

        return [];
    }

    public function successReply(AgentSession $s, AiAgentAction $action, array $data): AgentReply
    {
        $created = AgentReply::make(AgentReply::SUCCESS, $this->t($s, 'patient_created'), $s->language)
            ->field('f.name', $data['name'] ?? $action->params['name'])
            ->field('f.phone', $data['phone'] ?? null);

        if (empty($data['id'])) {
            return $created;
        }
        $s->setFocusPatient((int) $data['id'], $data['name'], $data['phone'] ?? null);

        // Pick up the booking that was waiting for this patient
        $resume = $s->state['resume'] ?? null;
        unset($s->state['resume']);
        if (is_array($resume) && ($resume['intent'] ?? null) === Intent::BOOK_APPOINTMENT) {
            $s->state['draft'] = $resume;
            $s->setSlots(['patient_id' => (int) $data['id'], 'patient_label' => $data['name'], 'patient_phone_display' => $data['phone'] ?? null]);
            $s->forget('patient_name', 'patient_phone');

            try {
                $next = app(BookAppointmentFlow::class)->handle($s);
            } catch (Halt $halt) {
                $next = $halt->reply;
            }

            return $next->prepend($this->t($s, 'patient_created'));
        }

        return $created;
    }
}
