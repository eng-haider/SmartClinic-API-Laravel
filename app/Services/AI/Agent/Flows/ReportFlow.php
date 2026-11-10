<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;

/** Today's numbers (GET reports/dashboard/today), honouring "hide dashboard numbers". */
class ReportFlow extends Flow
{
    private const LABELS = [
        'reservations_today' => ['مواعيد اليوم', "Today's appointments"],
        'new_patients' => ['مراجعين جدد', 'New patients'],
        'cases_today' => ['حالات اليوم', "Today's cases"],
        'revenue_today' => ['إيرادات اليوم', "Today's revenue"],
        'expenses_today' => ['مصاريف اليوم', "Today's expenses"],
    ];

    public function handle(AgentSession $s): AgentReply
    {
        $this->requirePermission($s, 'get_today_report');
        $r = $this->read($s, 'get_today_report');
        $data = $r->data([]);
        $hideMoney = (bool) $r->get('display.hide_numbers', false);

        $reply = AgentReply::make(AgentReply::INFO, $this->t($s, 'report_title'), $s->language);
        foreach (self::LABELS as $key => [$ar, $en]) {
            if (!array_key_exists($key, $data) || is_array($data[$key])) {
                continue;
            }
            $isMoney = in_array($key, ['revenue_today', 'expenses_today'], true);
            if ($isMoney && $hideMoney) {
                continue;
            }
            $reply->fields[] = [
                'label' => $s->language === 'en' ? $en : $ar,
                'value' => $isMoney ? $this->money($s, (float) $data[$key]) : (string) $data[$key],
            ];
        }

        return $reply;
    }
}
