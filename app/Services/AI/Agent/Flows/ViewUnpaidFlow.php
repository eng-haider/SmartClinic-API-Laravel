<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;

/**
 * Unpaid invoices: one patient's unpaid cases, or the clinic's patients with
 * a balance (GET bills/patient-balances?payment_status=unpaid).
 */
class ViewUnpaidFlow extends Flow
{
    use ReadsCaseBalances;

    public function handle(AgentSession $s): AgentReply
    {
        $aboutPatient = $s->slot('patient_id') || $s->slot('patient_name') || $s->slot('patient_phone') || $s->slot('patient_ref') === 'previous';

        if ($aboutPatient) {
            $this->requirePermission($s, 'list_patient_cases');
            $patient = $this->resolvePatient($s, true);
            $unpaid = array_filter($this->cases($s, $patient['id']), fn ($c) => $c['remaining'] > 0);

            if ($unpaid === []) {
                return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_unpaid_cases', ['name' => $patient['name']]), $s->language);
            }

            $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'unpaid_cases_title', ['name' => $patient['name']]), $s->language);
            foreach ($unpaid as $c) {
                $reply->item($c['label'], $this->t($s, 'f.remaining') . ': ' . $this->money($s, $c['remaining']),
                    $this->t($s, 'f.total') . ': ' . $this->money($s, $c['price']));
            }

            return $reply->field('f.remaining', $this->money($s, array_sum(array_column($unpaid, 'remaining'))));
        }

        $this->requirePermission($s, 'list_outstanding_balances');
        $r = $this->read($s, 'list_outstanding_balances', ['per_page' => 10]);
        $rows = $r->data([]);

        if ($rows === []) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_unpaid'), $s->language);
        }

        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'unpaid_title'), $s->language);
        foreach ($rows as $row) {
            $reply->item($row['name'], $this->t($s, 'f.remaining') . ': ' . $this->money($s, $row['unpaid_amount']), $row['phone'] ?? null);
        }

        return $reply->note($this->t($s, 'total_outstanding', ['amount' => $this->money($s, $r->get('summary.unpaid_amount', 0))]));
    }
}
