<?php

namespace App\Services\AI\Agent\Flows;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use Carbon\CarbonImmutable;

/**
 * Records money the clinic already received, against one of the patient's
 * cases (the clinic's invoices): POST /bills with billable = the case.
 *
 * Patient -> amount (shorthand like "50" is asked about) -> unpaid cases from
 * GET /cases?include=bills -> confirm with the balance before and after ->
 * re-read the balance (a changed balance means a new confirmation) -> POST.
 * Nothing here collects money; the reply says so.
 */
class RecordPaymentFlow extends Flow
{
    use ReadsCaseBalances;

    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'record_payment');

        $patient = $this->resolvePatient($s, true);
        $amount = $this->resolveAmount($s, $patient);
        $case = $this->pickCase($s, $patient, $this->cases($s, $patient['id']));

        if ($s->slot('pay_remaining')) {
            $amount = $case['remaining'];
        }

        if ($amount > $case['remaining']) {
            $s->forget('amount', 'amount_text', 'amount_llm');

            throw new Halt($this->ask($s, 'amount_text', $this->t($s, 'overpay', [
                'amount' => $this->money($s, $amount),
                'remaining' => $this->money($s, $case['remaining']),
            ]), [$this->option($this->money($s, $case['remaining']), ['amount' => $case['remaining']])]));
        }

        return $this->confirm($s, 'record_payment',
            ['patient_id' => $patient['id'], 'billable_id' => $case['id'], 'price' => $amount],
            $this->t($s, 'confirm_payment_title', ['name' => $patient['name']]),
            'confirm_payment_question', 'confirm_payment_btn',
            [
                ['f.patient', $this->patientLine($patient)],
                ['f.amount', $this->money($s, $amount, true)],
                ['f.invoice', $case['label']],
                ['f.remaining_before', $this->money($s, $case['remaining'])],
                ['f.remaining_after', $this->money($s, $case['remaining'] - $amount)],
            ],
            [
                'patient_id' => $patient['id'],
                'patient_name' => $patient['name'],
                'patient_phone' => $patient['phone'],
                'case_id' => $case['id'],
                'case_label' => $case['label'],
                'remaining_before' => $case['remaining'],
                'amount' => $amount,
            ],
            [$this->t($s, 'payment_note')],
        );
    }

    public function execute(AgentSession $s, AiAgentAction $action): AgentReply
    {
        $snapshot = $action->snapshot;
        $s->cache = [];

        $case = $this->findCase($this->cases($s, $snapshot['patient_id']), $snapshot['case_id']);

        if (!$case || $case['remaining'] !== (int) $snapshot['remaining_before']) {
            // Someone recorded or changed something in between: show fresh numbers
            $this->actions->finish($action, AiAgentAction::FAILED, 409, null, 'Balance changed before execution');
            $s->setSlots(['amount' => $snapshot['amount']]);

            try {
                $reply = $this->handle($s);
            } catch (Halt $halt) {
                $reply = $halt->reply;
            }

            return $reply->prepend($this->t($s, 'balance_changed'));
        }

        return $this->conclude($s, $action, $this->call($s, 'record_payment', $action->params, $action));
    }

    public function verify(AgentSession $s, AiAgentAction $action): ?array
    {
        $snapshot = $action->snapshot;
        $r = $this->tools->run($s, 'list_patient_cases', ['patient_id' => $snapshot['patient_id'], 'per_page' => 100]);
        if (!$r->ok()) {
            return null;
        }

        $since = ($action->confirmed_at ?? $action->created_at)->copy()->subMinutes(2);
        foreach ($r->data([]) as $case) {
            if ((int) $case['id'] !== (int) $snapshot['case_id']) {
                continue;
            }
            foreach ($case['bills'] ?? [] as $bill) {
                if ((int) $bill['price'] === (int) $snapshot['amount']
                    && !empty($bill['created_at']) && CarbonImmutable::parse($bill['created_at'])->gte($since)) {
                    return $bill;
                }
            }
        }

        return [];
    }

    public function successReply(AgentSession $s, AiAgentAction $action, array $data): AgentReply
    {
        $snapshot = $action->snapshot;
        $amount = (int) ($data['price'] ?? $snapshot['amount']);

        // The balance as the API reports it after the payment
        $remaining = (int) $snapshot['remaining_before'] - $amount;
        $s->cache = [];
        $r = $this->tools->run($s, 'list_patient_cases', ['patient_id' => $snapshot['patient_id'], 'per_page' => 100]);
        if ($r->ok() && ($case = $this->findCase($this->toCases($s, $r->data([])), $snapshot['case_id']))) {
            $remaining = $case['remaining'];
        }

        $s->setFocusPatient((int) $snapshot['patient_id'], $snapshot['patient_name'], $snapshot['patient_phone'] ?? null);

        return AgentReply::make(AgentReply::SUCCESS, $this->t($s, 'payment_recorded'), $s->language)
            ->field('f.patient', $snapshot['patient_name'])
            ->field('f.amount_recorded', $this->money($s, $amount))
            ->field('f.remaining', $this->money($s, $remaining))
            ->field('f.invoice', $snapshot['case_label'])
            ->field('f.payment_ref', isset($data['id']) ? '#' . $data['id'] : null)
            ->note($this->t($s, 'payment_note'));
    }

    /** The amount from the user's words; null when they asked to pay the remaining balance. */
    private function resolveAmount(AgentSession $s, array $patient): ?int
    {
        if ($s->slot('pay_remaining')) {
            return null;
        }
        if ($amount = $s->slot('amount')) {
            return (int) $amount;
        }

        $text = $s->slot('amount_text');
        if (!$text) {
            throw new Halt($this->ask($s, 'amount_text', $this->t($s, 'ask_amount', ['name' => $patient['name']])));
        }

        $parsed = $this->amounts->parse($text);
        $s->forget('amount_text');
        $currency = $this->clinicCurrency($s);
        $stated = $parsed['currency'] ?? $s->slot('currency_stated');

        if ($stated && $currency && $stated !== $currency) {
            throw new Halt($this->ask($s, 'amount_text', $this->t($s, 'currency_mismatch', ['clinic' => $currency])));
        }

        if (!$parsed) {
            $llm = (int) $s->slot('amount_llm');
            if ($llm > 0) {
                $s->setSlots(['amount' => $llm]);

                return $llm;
            }

            throw new Halt($this->ask($s, 'amount_text', $this->t($s, 'amount_unclear', ['text' => $text])));
        }

        // "50" on its own usually means 50 thousand dinars, but not always: ask
        if ($parsed['bare'] && $parsed['amount'] < 1000 && $currency !== 'USD') {
            $short = $parsed['amount'];
            $options = [
                $this->option($this->money($s, $short * 1000), ['amount' => $short * 1000]),
                $this->option($this->money($s, $short), ['amount' => $short]),
            ];

            throw new Halt($this->ask($s, 'amount_text', $this->t($s, 'ask_amount_unit', [
                'a' => $this->money($s, $short * 1000),
                'b' => $this->money($s, $short),
            ]), $options));
        }

        $s->setSlots(['amount' => $parsed['amount']]);

        return $parsed['amount'];
    }

    private function pickCase(AgentSession $s, array $patient, array $cases): array
    {
        $unpaid = array_values(array_filter($cases, fn ($c) => $c['remaining'] > 0));
        $choices = fn () => array_map(fn ($c) => $this->option(
            $c['label'] . ' — ' . $this->t($s, 'f.remaining') . ': ' . $this->money($s, $c['remaining']),
            ['case_id' => $c['id']],
        ), array_slice($unpaid, 0, 8));

        if ($id = $s->slot('case_id')) {
            $case = $this->findCase($cases, (int) $id);
            if ($case && $case['remaining'] > 0) {
                return $case;
            }
            $s->forget('case_id');
        }

        if ($ref = $s->slot('invoice_ref')) {
            $s->forget('invoice_ref');
            $case = $this->findCase($cases, (int) preg_replace('/\D+/', '', (string) $ref));
            if ($case && $case['remaining'] > 0) {
                $s->setSlots(['case_id' => $case['id']]);

                return $case;
            }
            if ($unpaid !== []) {
                throw new Halt($this->ask($s, 'choice', $this->t($s, 'invoice_not_found', ['ref' => $ref]), $choices()));
            }
        }

        if ($unpaid === []) {
            throw new Halt(AgentReply::make(AgentReply::INFO, $this->t($s, 'no_unpaid_cases', ['name' => $patient['name']]), $s->language));
        }

        if (count($unpaid) === 1) {
            $s->setSlots(['case_id' => $unpaid[0]['id']]);

            return $unpaid[0];
        }

        throw new Halt($this->ask($s, 'choice', $this->t($s, 'choose_case', ['name' => $patient['name']]), $choices()));
    }
}
