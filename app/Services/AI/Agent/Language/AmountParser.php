<?php

namespace App\Services\AI\Agent\Language;

/**
 * Reads a money amount from Arabic, Iraqi or English text:
 * "50 ألف", "خمسين ألف", "50k", "50,000", "٥٠ ألف", "fifty thousand",
 * "مية وخمسين ألف", "ربع مليون", "مليون ونص".
 *
 * The result says whether the amount had a unit (ألف, k...) or a currency, so the
 * caller can ask about shorthand like a bare "50" instead of guessing.
 */
class AmountParser
{
    private const UNITS = [
        'صفر' => 0, 'zero' => 0,
        'واحد' => 1, 'وحده' => 1, 'one' => 1,
        'اثنين' => 2, 'اثنان' => 2, 'ثنين' => 2, 'two' => 2,
        'ثلاث' => 3, 'ثلاثه' => 3, 'ثلث' => 3, 'three' => 3,
        'اربع' => 4, 'اربعه' => 4, 'four' => 4,
        'خمس' => 5, 'خمسه' => 5, 'five' => 5,
        'ست' => 6, 'سته' => 6, 'six' => 6,
        'سبع' => 7, 'سبعه' => 7, 'seven' => 7,
        'ثمان' => 8, 'ثمانيه' => 8, 'ثمن' => 8, 'ثمنيه' => 8, 'eight' => 8,
        'تسع' => 9, 'تسعه' => 9, 'nine' => 9,
        'عشر' => 10, 'عشره' => 10, 'ten' => 10,
        'احدعش' => 11, 'احدعشر' => 11, 'eleven' => 11,
        'اثنعش' => 12, 'اثناعشر' => 12, 'twelve' => 12,
        'ثلطعش' => 13, 'ثلاثطعش' => 13, 'thirteen' => 13,
        'اربعطعش' => 14, 'fourteen' => 14,
        'خمسطعش' => 15, 'fifteen' => 15,
        'ستطعش' => 16, 'sixteen' => 16,
        'سبعطعش' => 17, 'seventeen' => 17,
        'ثمنطعش' => 18, 'eighteen' => 18,
        'تسعطعش' => 19, 'nineteen' => 19,
        'عشرين' => 20, 'عشرون' => 20, 'twenty' => 20,
        'ثلاثين' => 30, 'ثلثين' => 30, 'ثلاثون' => 30, 'thirty' => 30,
        'اربعين' => 40, 'اربعون' => 40, 'forty' => 40,
        'خمسين' => 50, 'خمسون' => 50, 'fifty' => 50,
        'ستين' => 60, 'ستون' => 60, 'sixty' => 60,
        'سبعين' => 70, 'سبعون' => 70, 'seventy' => 70,
        'ثمانين' => 80, 'ثمنين' => 80, 'ثمانون' => 80, 'eighty' => 80,
        'تسعين' => 90, 'تسعون' => 90, 'ninety' => 90,
        'ميه' => 100, 'مئه' => 100, 'مائه' => 100, 'مية' => 100,
        'ميتين' => 200, 'مئتين' => 200, 'مائتين' => 200, 'مئتان' => 200,
        'ثلاثميه' => 300, 'ثلثميه' => 300, 'ثلاثمائه' => 300, 'اربعميه' => 400, 'اربعمائه' => 400,
        'خمسميه' => 500, 'خمسمائه' => 500, 'ستميه' => 600, 'ستمائه' => 600,
        'سبعميه' => 700, 'سبعمائه' => 700, 'ثمنميه' => 800, 'ثمانميه' => 800, 'ثمانمائه' => 800,
        'تسعميه' => 900, 'تسعمائه' => 900,
    ];

    private const THOUSAND = ['الف', 'الاف', 'آلاف', 'k', 'thousand', 'thousands', 'تلاف'];
    private const MILLION = ['مليون', 'ملايين', 'million', 'millions', 'm'];
    private const HUNDRED = ['hundred'];

    private const IQD = ['دينار', 'دنانير', 'د.ع', 'iqd', 'dinar', 'dinars', 'عراقي'];
    private const USD = ['دولار', 'دولارات', 'usd', 'dollar', 'dollars', '$'];

    /**
     * @return array{amount: int, currency: ?string, has_unit: bool, bare: bool}|null
     *   amount   whole currency units
     *   currency 'IQD' / 'USD' when the text names one
     *   has_unit the text had ألف / k / مليون...
     *   bare     a plain number with no unit, separators or currency (e.g. "50")
     */
    public function parse(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $normalized = TextNormalizer::normalize($text);
        $currency = $this->currency($normalized);

        // Unit words glued to digits: "50k", "50الف", "1.5m"
        $normalized = preg_replace('/(\d)(k|m|الف|مليون)(?!\p{L})/u', '$1 $2', $normalized) ?? $normalized;
        $normalized = str_replace('$', ' $ ', $normalized);
        // "و" glued to the next word ("وخمسين") separates words
        $normalized = preg_replace('/(^|\s)و(?=\p{L}|\d)/u', '$1و ', $normalized) ?? $normalized;
        // Unit words written together: الفين = 2 الف, مليونين = 2 مليون
        $normalized = preg_replace('/(?<!\p{L})الفين(?!\p{L})/u', '2 الف', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?<!\p{L})مليونين(?!\p{L})/u', '2 مليون', $normalized) ?? $normalized;

        $tokens = preg_split('/[\s\-]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $total = 0;      // finished millions/thousands groups
        $current = 0.0;  // number being built
        $seenNumber = false;
        $hasUnit = false;
        $hasSeparator = false;
        $fraction = null; // ونص / وربع after a unit

        foreach ($tokens as $i => $token) {
            if (in_array($token, ['و', 'and', 'ب', 'بس', 'فقط', 'only', 'مبلغ', 'amount'], true)) {
                continue;
            }

            if (preg_match('/^\d[\d,]*(\.\d+)?$/', $token)) {
                $hasSeparator = $hasSeparator || str_contains($token, ',');
                $current += (float) str_replace(',', '', $token);
                $seenNumber = true;
                continue;
            }

            if (in_array($token, ['نص', 'نصف', 'half'], true)) {
                if ($seenNumber || $total > 0) {
                    $fraction = 0.5;
                } else {
                    $current = 0.5;
                    $seenNumber = true;
                }
                continue;
            }
            if (in_array($token, ['ربع', 'quarter'], true)) {
                if ($seenNumber || $total > 0) {
                    $fraction = 0.25;
                } else {
                    $current = 0.25;
                    $seenNumber = true;
                }
                continue;
            }

            if (isset(self::UNITS[$token])) {
                $current += self::UNITS[$token];
                $seenNumber = true;
                continue;
            }

            if (in_array($token, self::HUNDRED, true)) {
                $current = ($current ?: 1) * 100;
                $seenNumber = true;
                continue;
            }

            $multiplier = in_array($token, self::THOUSAND, true) ? 1_000
                : (in_array($token, self::MILLION, true) ? 1_000_000 : null);

            if ($multiplier !== null) {
                // "m" alone is too vague to be a million unless it follows a number
                if ($token === 'm' && !$seenNumber) {
                    continue;
                }
                $total += ($current ?: 1) * $multiplier;
                $current = 0;
                $hasUnit = true;
                $seenNumber = true;
                if ($fraction !== null) {
                    $total += $fraction * $multiplier;
                    $fraction = null;
                }
                // A fraction after the unit ("مليون ونص") applies to that unit
                $next = $tokens[$i + 1] ?? null;
                $after = $tokens[$i + 2] ?? null;
                if ($next === 'و' && in_array($after, ['نص', 'نصف', 'ربع'], true)) {
                    $total += ($after === 'ربع' ? 0.25 : 0.5) * $multiplier;
                    unset($tokens[$i + 2]);
                }
                continue;
            }
        }

        if (!$seenNumber) {
            return null;
        }

        $amount = (int) round($total + $current);
        if ($amount <= 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'currency' => $currency,
            'has_unit' => $hasUnit,
            'bare' => !$hasUnit && !$hasSeparator && $currency === null,
        ];
    }

    private function currency(string $normalized): ?string
    {
        foreach (self::IQD as $word) {
            if (str_contains($normalized, $word)) {
                return 'IQD';
            }
        }
        foreach (self::USD as $word) {
            if (preg_match('/(^|[\s\d])' . preg_quote($word, '/') . '($|\s)/u', $normalized) || ($word === '$' && str_contains($normalized, '$'))) {
                return 'USD';
            }
        }

        return null;
    }
}
