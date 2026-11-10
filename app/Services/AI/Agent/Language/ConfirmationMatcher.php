<?php

namespace App\Services\AI\Agent\Language;

/**
 * Decides, without the LLM, whether a reply is a clear "yes" or "no" to the
 * action waiting for confirmation.
 *
 * A reply is a confirmation only when every word in it is a confirming word
 * (or harmless filler like "الحجز" / "please"). "اي بس خليه الساعة 6" or
 * "yes and also delete the others" are not confirmations: they change or add to
 * the request and go through understanding again. A reply mixing yes and no
 * words is ambiguous.
 */
class ConfirmationMatcher
{
    public const CONFIRM = 'confirm';
    public const DENY = 'deny';
    public const AMBIGUOUS = 'ambiguous';

    private const CONFIRM_WORDS = [
        'نعم', 'اي', 'ايه', 'ايي', 'إي', 'ايوه', 'ايوا', 'اكيد', 'موافق', 'موافقه', 'اكد', 'اكدها', 'اكده',
        'تاكيد', 'تاكد', 'نفذ', 'نفذها', 'نفذه', 'سوي', 'سويها', 'تمام', 'زين', 'صح', 'اوكي', 'اوك', 'يلا',
        'yes', 'y', 'yeah', 'yep', 'yup', 'ok', 'okay', 'confirm', 'confirmed', 'proceed', 'sure', 'go',
        'ahead', 'execute', 'approve', 'approved', 'do',
    ];

    private const DENY_WORDS = [
        'لا', 'لاء', 'لالا', 'كلا', 'الغي', 'الغيها', 'الغيه', 'الغاء', 'بطل', 'بطلها', 'لاتنفذ', 'لاتسوي',
        'no', 'nope', 'nah', 'cancel', 'stop', 'abort', 'dont', "don't", 'never',
    ];

    // Words that may sit next to a yes/no without changing it
    private const FILLER = [
        'الحجز', 'التسديد', 'الدفعه', 'العمليه', 'الموعد', 'الطلب', 'التعديل', 'الالغاء', 'بالله', 'رجاء',
        'رجاءا', 'لو', 'سمحت', 'من', 'فضلك', 'شكرا', 'حبيبي', 'عيني', 'خلاص', 'هسه', 'تنفذ', 'تسوي',
        'please', 'pls', 'it', 'thanks', 'thank', 'you', 'the', 'booking', 'payment', 'action', 'now', 'it.',
    ];

    public function match(string $text): ?string
    {
        $words = TextNormalizer::words(str_replace(['✅', '❌', '👍', '👎'], [' confirm ', ' cancel ', ' yes ', ' no '], $text));
        if ($words === []) {
            return null;
        }

        $confirm = $this->normalizedList(self::CONFIRM_WORDS);
        $deny = $this->normalizedList(self::DENY_WORDS);
        $filler = $this->normalizedList(self::FILLER);

        // "لا تنفذ" / "don't" spelled as two words
        $joined = implode(' ', $words);
        $joined = str_replace(['لا تنفذ', 'لا تسوي', 'do not', 'don t'], ['لاتنفذ', 'لاتسوي', 'dont', 'dont'], $joined);
        $words = explode(' ', $joined);

        $hasConfirm = false;
        $hasDeny = false;

        foreach ($words as $word) {
            if (in_array($word, $deny, true)) {
                $hasDeny = true;
            } elseif (in_array($word, $confirm, true)) {
                $hasConfirm = true;
            } elseif (!in_array($word, $filler, true)) {
                // Any other word: not a plain yes/no
                return null;
            }
        }

        if ($hasConfirm && $hasDeny) {
            return self::AMBIGUOUS;
        }
        if ($hasDeny) {
            return self::DENY;
        }

        return $hasConfirm ? self::CONFIRM : null;
    }

    /**
     * True when the text holds a yes/no word among other words, so it may be a
     * confirmation in disguise and must not be acted on as one.
     */
    public function looksLikeConfirmation(string $text): bool
    {
        $words = TextNormalizer::words($text);

        return $this->hasAny($words, $this->normalizedList(self::CONFIRM_WORDS));
    }

    private function hasAny(array $words, array $list): bool
    {
        return array_intersect($words, $list) !== [];
    }

    private function normalizedList(array $words): array
    {
        static $cache = [];
        $key = md5(implode('|', $words));

        return $cache[$key] ??= array_map(fn ($w) => TextNormalizer::normalize($w), $words);
    }
}
