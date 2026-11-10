<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;

/** Recorded payments (GET bills/payments), for a patient and/or a period. */
class PaymentHistoryFlow extends Flow
{
    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'list_payments');

        $patient = null;
        if ($s->slot('patient_id') || $s->slot('patient_name') || $s->slot('patient_phone') || $s->slot('patient_ref') === 'previous') {
            $patient = $this->resolvePatient($s, true);
        }

        $today = $this->clock->now();
        [$from, $to] = match ($s->slot('period')) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'month' => [$today->startOfMonth()->toDateString(), $today->toDateString()],
            default => [null, null],
        };

        $r = $this->read($s, 'list_payments', array_filter([
            'patient_id' => $patient['id'] ?? null,
            'date_from' => $from,
            'date_to' => $to,
            'per_page' => 10,
        ]));
        $rows = $r->data([]);

        if ($rows === []) {
            return AgentReply::make(AgentReply::INFO, $this->t($s, 'no_payments'), $s->language);
        }

        $title = $patient ? $this->t($s, 'payments_title_patient', ['name' => $patient['name']]) : $this->t($s, 'payments_title');
        $reply = AgentReply::make(AgentReply::INFO, $title, $s->language);
        foreach ($rows as $bill) {
            $reply->item(
                $this->money($s, $bill['price'] ?? 0),
                $bill['patient']['name'] ?? null,
                isset($bill['created_at']) ? substr((string) $bill['created_at'], 0, 10) : null,
            );
        }

        return $reply->field('f.paid', $this->money($s, $r->get('summary.paid_amount', 0)));
    }
}
