<?php

namespace App\Services;

use App\Http\Helpers\ClinicSwitch;
use App\Models\Bill;
use App\Models\CaseModel;
use App\Repositories\ClinicSettingRepository;

/**
 * For clinics with the strict_payments setting: refuses a payment on a case without a
 * price, payments over the price, and a price below what was already paid. Cases that
 * are like that already can stay so, and their payments can always be lowered.
 *
 * Every method returns why the change is refused, or null when it is fine.
 */
class CasePaymentGuard
{
    private bool $arabic;

    public function __construct(?string $language)
    {
        // Arabic for Arabic and Kurdish screens, English for the others
        $this->arabic = in_array(strtolower(substr((string) $language, 0, 2)), ['ar', 'ku'], true);
    }

    public static function isOn(): bool
    {
        return ClinicSwitch::isOn(ClinicSettingRepository::STRICT_PAYMENTS_KEY);
    }

    public function newPayment(int $caseId, int $amount): ?string
    {
        $case = CaseModel::find($caseId);

        return $case ? $this->payment($case, $amount, (int) $case->bills()->sum('price')) : null;
    }

    public function changedPayment(Bill $bill, int $amount, ?int $newCaseId): ?string
    {
        $moved = $newCaseId !== null && $newCaseId !== (int) $bill->billable_id;
        $case = CaseModel::find($newCaseId ?? $bill->billable_id);

        if (!$case || (!$moved && $amount <= (int) $bill->price)) {
            return null;
        }

        return $this->payment($case, $amount, (int) $case->bills()->whereKeyNot($bill->id)->sum('price'));
    }

    public function price(int $caseId, int $price): ?string
    {
        $case = CaseModel::find($caseId);

        // The price is sent with every edit of the case: only lowering it matters
        if (!$case || $price >= (int) $case->price) {
            return null;
        }

        $paid = (int) $case->bills()->sum('price');

        return $price < $paid ? $this->say(
            'سعر الحالة (:a) لا يمكن أن يكون أقل من المدفوع عليها (:b). تُعدّل الدفعات أولاً.',
            'The price (:a) cannot be less than what was paid on this case (:b). Change the payments first.',
            $price, $paid
        ) : null;
    }

    private function payment(CaseModel $case, int $amount, int $alreadyPaid): ?string
    {
        if ($amount <= 0) {
            return null;
        }

        $price = (int) $case->price;

        if ($price <= 0) {
            return $this->say(
                'هذه الحالة بدون سعر. يُكتب سعرها أولاً، أو تُسجّل الدفعة على حالة العلاج نفسها.',
                "This case has no price. Enter its price first, or record the payment on the treatment's own case."
            );
        }

        $remaining = max(0, $price - $alreadyPaid);

        return $amount > $remaining ? $this->say(
            'الدفعة (:a) أكثر من المتبقي على هذه الحالة (:b).',
            'The payment (:a) is more than what remains on this case (:b).',
            $amount, $remaining
        ) : null;
    }

    private function say(string $arabic, string $english, int $a = 0, int $b = 0): string
    {
        return strtr($this->arabic ? $arabic : $english, [':a' => number_format($a), ':b' => number_format($b)]);
    }
}
