<?php

namespace App\Services\AI\Agent\Language;

/**
 * Normalizes Arabic and English text so spelling variants compare equal:
 * Arabic-Indic digits, hamza forms of alef, taa marbuta, alef maqsura,
 * Iraqi letters (چ گ ڤ), diacritics and tatweel.
 */
class TextNormalizer
{
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه', 'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و',
        'چ' => 'ج', 'گ' => 'ك', 'ڤ' => 'ف', 'پ' => 'ب', 'ک' => 'ك', 'ی' => 'ي',
        '،' => ',', '٬' => ',', '٫' => '.', '؟' => '?', '؛' => ';',
    ];

    /** Digits only, keeping every other character. */
    public static function digits(string $text): string
    {
        return strtr($text, self::DIGITS);
    }

    /** Full normalization, for matching words. */
    public static function normalize(string $text): string
    {
        $text = self::digits($text);
        // Diacritics (harakat, shadda, sukun, superscript alef) and tatweel
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, self::LETTERS);
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Words of the normalized text, punctuation removed. */
    public static function words(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s:\/\-\.]/u', ' ', self::normalize($text)) ?? '';

        return array_values(array_filter(explode(' ', $clean), fn ($w) => $w !== ''));
    }

    /** True when the text has Arabic letters. */
    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * Spellings to search a name with: the API's LIKE search treats أحمد and
     * احمد as different words.
     *
     * @return list<string>
     */
    public static function nameVariants(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', self::digits($name)) ?? $name);
        if ($name === '') {
            return [];
        }

        $plain = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا']);
        $variants = [$name, $plain];

        // A leading bare alef is usually written with hamza (احمد -> أحمد, اسراء -> إسراء)
        $variants[] = preg_replace('/(^|\s)ا/u', '$1أ', $plain) ?? $plain;
        $variants[] = preg_replace('/(^|\s)ا/u', '$1إ', $plain) ?? $plain;
        // Final taa marbuta and haa are often swapped (فاطمه / فاطمة)
        $variants[] = preg_replace('/ه(\s|$)/u', 'ة$1', $name) ?? $name;
        $variants[] = preg_replace('/ة(\s|$)/u', 'ه$1', $name) ?? $name;

        return array_values(array_unique(array_filter($variants)));
    }
}
