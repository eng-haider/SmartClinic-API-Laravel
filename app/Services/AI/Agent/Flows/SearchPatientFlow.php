<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Language\TextNormalizer;

/** Patient search (GET /patients?search=), trying common spellings of the name. */
class SearchPatientFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'search_patients');

        $query = $s->slot('patient_phone') ?: $s->slot('patient_name');
        if (!$query) {
            throw new Halt($this->ask($s, 'patient_name', $this->t($s, 'ask_patient')));
        }

        $variants = $s->slot('patient_phone') ? [$query] : array_slice(TextNormalizer::nameVariants($query), 0, 4);
        $found = [];
        foreach ($variants as $variant) {
            foreach ($this->read($s, 'search_patients', ['search' => $variant, 'per_page' => 10])->data([]) as $p) {
                $found[$p['id']] = $p;
            }
        }

        if ($found === []) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'patient_not_found', ['name' => $query]), $s->language);
        }

        if (count($found) === 1) {
            $p = reset($found);
            $s->setFocusPatient((int) $p['id'], $p['name'], $p['phone'] ?? null);
        }

        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'patients_found', ['count' => count($found)]), $s->language);
        foreach (array_slice($found, 0, 10) as $p) {
            $reply->item($p['name'], $p['phone'] ?? null, isset($p['age']) && $p['age'] ? (string) $p['age'] : null);
        }

        return $reply;
    }
}
