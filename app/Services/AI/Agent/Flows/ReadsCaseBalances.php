<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentSession;

/**
 * A patient's cases with what is left to pay on each, from
 * GET /cases?filter[patient_id]&include=bills (paid bills count; a case's
 * price minus its paid bills is its balance, as in the billing overview).
 */
trait ReadsCaseBalances
{
    /** @return list<array{id: int, price: int, paid: int, remaining: int, label: string}> */
    protected function cases(AgentSession $s, int $patientId): array
    {
        return $this->toCases($s, $this->read($s, 'list_patient_cases', ['patient_id' => $patientId, 'per_page' => 100])->data([]));
    }

    protected function toCases(AgentSession $s, array $rows): array
    {
        $cases = [];
        foreach ($rows as $c) {
            // A doctor without access to another doctor's case sees no price
            if (!isset($c['price']) || $c['price'] === null) {
                continue;
            }
            $paid = (int) collect($c['bills'] ?? [])->filter(fn ($b) => !empty($b['is_paid']))->sum('price');
            $price = (int) $c['price'];
            $date = !empty($c['case_date']) ? substr($c['case_date'], 0, 10) : (!empty($c['created_at']) ? substr($c['created_at'], 0, 10) : null);

            $cases[] = [
                'id' => (int) $c['id'],
                'price' => $price,
                'paid' => $paid,
                'remaining' => max($price - $paid, 0),
                'label' => '#' . $c['id'] . (!empty($c['category']['name']) && $c['category']['name'] !== 'N/A' ? ' — ' . $c['category']['name'] : '')
                    . ($date ? ' (' . $date . ')' : ''),
            ];
        }

        return $cases;
    }

    protected function findCase(array $cases, int $id): ?array
    {
        foreach ($cases as $case) {
            if ($case['id'] === (int) $id) {
                return $case;
            }
        }

        return null;
    }
}
