<?php

namespace App\Services\AI\Agent\Language;

/**
 * Picks the reply language from the user's own words: Arabic when Arabic
 * letters are at least as many as Latin ones ("احجز موعد لأحمد tomorrow"),
 * English otherwise ("Book موعد for Ahmed tomorrow"). Messages with no letters
 * ("2", "17:00") keep the conversation's language.
 */
class LanguageDetector
{
    public function detect(string $text, string $fallback = 'ar'): string
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $latin = preg_match_all('/[a-zA-Z]/', $text);

        if ($arabic === 0 && $latin === 0) {
            return $fallback;
        }

        return $arabic >= $latin ? 'ar' : 'en';
    }
}
