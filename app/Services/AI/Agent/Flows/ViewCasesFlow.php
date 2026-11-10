<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;

/** A patient's cases with their status and balance (GET /cases). */
class ViewCasesFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'list_patient_cases');
        $patient = $this->resolvePatient($s, true);
        $rows = $this->read($s, 'list_patient_cases', ['patient_id' => $patient['id'], 'per_page' => 50])->data([]);

        if ($rows === []) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_cases'), $s->language);
        }

        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'cases_title', ['name' => $patient['name']]), $s->language);
        foreach (array_slice($rows, 0, 15) as $c) {
            $status = $s->language === 'en' ? ($c['status']['name_en'] ?? null) : ($c['status']['name_ar'] ?? null);
            $category = $c['category']['name'] ?? '';
            $paid = (int) collect($c['bills'] ?? [])->filter(fn ($b) => !empty($b['is_paid']))->sum('price');
            $money = isset($c['price']) && $c['price'] !== null
                ? $this->t($s, 'f.remaining') . ': ' . $this->money($s, max((int) $c['price'] - $paid, 0))
                : null;

            $reply->item('#' . $c['id'] . ($category && $category !== 'N/A' ? ' — ' . $category : ''), $money, $status !== 'N/A' ? $status : null);
        }

        return $reply;
    }
}
