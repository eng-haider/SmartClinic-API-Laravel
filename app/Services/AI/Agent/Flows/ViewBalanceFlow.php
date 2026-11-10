<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;

/**
 * A patient's balance: GET bills/patient-balances/{id}/bills for users with
 * view-all-bills, otherwise from their cases (GET /cases, e.g. secretaries).
 * The patient stays the subject, so "سجل تسديد 50 ألف" can follow.
 */
class ViewBalanceFlow extends Flow
{
    use ReadsCaseBalances;

    public function handle(AgentSession $s): AgentReply
    {
        if (!$this->tools->can($s, 'get_patient_balance') && !$this->tools->can($s, 'list_patient_cases')) {
            throw new Halt($this->forbidden($s, 'op.view_bills'));
        }

        $patient = $this->resolvePatient($s, true);
        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'balance_title', ['name' => $patient['name']]), $s->language);

        if ($this->tools->can($s, 'get_patient_balance')) {
            $balance = $this->read($s, 'get_patient_balance', ['patient_id' => $patient['id'], 'per_page' => 1])->get('patient_balance', []);

            return $reply
                ->field('f.total', $this->money($s, $balance['total_price'] ?? 0))
                ->field('f.paid', $this->money($s, $balance['paid_amount'] ?? 0))
                ->field('f.remaining', $this->money($s, $balance['unpaid_amount'] ?? 0))
                ->field('f.unpaid_cases', isset($balance['unpaid_case_count']) ? (string) $balance['unpaid_case_count'] : null)
                ->field('f.last_payment', !empty($balance['last_payment_at']) ? substr($balance['last_payment_at'], 0, 10) : null);
        }

        $cases = $this->cases($s, $patient['id']);
        $total = array_sum(array_column($cases, 'price'));
        $paid = array_sum(array_column($cases, 'paid'));
        $remaining = array_sum(array_column($cases, 'remaining'));

        return $reply
            ->field('f.total', $this->money($s, $total))
            ->field('f.paid', $this->money($s, $paid))
            ->field('f.remaining', $this->money($s, $remaining))
            ->field('f.unpaid_cases', (string) count(array_filter($cases, fn ($c) => $c['remaining'] > 0)));
    }
}
