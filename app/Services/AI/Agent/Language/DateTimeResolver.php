<?php

namespace App\Services\AI\Agent\Language;

use Carbon\CarbonImmutable;

/**
 * Turns Iraqi, Arabic and English date and time expressions into exact values
 * in the clinic's timezone: باچر, عقب باچر, الخميس الجاي, الأسبوع الجاي, 11/11,
 * 11 تشرين الثاني, tomorrow, next Friday, الساعة 5, 5 ونص, العصر, 5:30 PM.
 *
 * It never picks between readings: "the 5 o'clock" without AM/PM comes back
 * with both candidates, and "next week" without a day is reported as ambiguous.
 */
class DateTimeResolver
{
    // Iraqi week runs Saturday to Friday
    private const WEEK_START = CarbonImmutable::SATURDAY;

    // Bare forms like "احد" (someone) or "اثنين" (two) are left out on purpose
    private const WEEKDAYS = [
        'الاحد' => 0, 'sunday' => 0, 'sun' => 0,
        'الاثنين' => 1, 'الاتنين' => 1, 'الثنين' => 1, 'monday' => 1, 'mon' => 1,
        'الثلاثاء' => 2, 'الثلاثا' => 2, 'ثلاثاء' => 2, 'tuesday' => 2, 'tue' => 2,
        'الاربعاء' => 3, 'الاربعا' => 3, 'اربعاء' => 3, 'wednesday' => 3, 'wed' => 3,
        'الخميس' => 4, 'خميس' => 4, 'thursday' => 4, 'thu' => 4,
        'الجمعه' => 5, 'جمعه' => 5, 'friday' => 5, 'fri' => 5,
        'السبت' => 6, 'سبت' => 6, 'saturday' => 6, 'sat' => 6,
    ];

    private const MONTHS = [
        'كانون الثاني' => 1, 'شباط' => 2, 'اذار' => 3, 'نيسان' => 4, 'ايار' => 5, 'حزيران' => 6,
        'تموز' => 7, 'اب' => 8, 'ايلول' => 9, 'تشرين الاول' => 10, 'تشرين الثاني' => 11, 'كانون الاول' => 12,
        'يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'ابريل' => 4, 'مايو' => 5, 'يونيو' => 6,
        'يوليو' => 7, 'اغسطس' => 8, 'سبتمبر' => 9, 'اكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12,
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
        'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const NEXT = ['الجاي', 'الجايه', 'الجايي', 'القادم', 'القادمه', 'الياي', 'next', 'coming'];

    private const PERIODS = [
        'morning' => ['الصبح', 'الصباح', 'صباحا', 'صباح', 'morning', 'الضحى'],
        'noon' => ['الظهر', 'ظهرا', 'noon', 'midday'],
        'afternoon' => ['العصر', 'عصرا', 'عصر', 'afternoon', 'بعد الظهر'],
        'evening' => ['بالليل', 'الليل', 'ليلا', 'المغرب', 'المسا', 'المساء', 'مساء', 'مساءا', 'evening', 'night', 'tonight'],
    ];

    // Hours each period covers, for listing free times in it
    public const PERIOD_RANGES = [
        'morning' => ['06:00', '12:00'],
        'noon' => ['11:30', '14:30'],
        'afternoon' => ['12:00', '18:00'],
        'evening' => ['17:00', '23:59'],
    ];

    public function __construct(private ?string $timezone = null)
    {
        $this->timezone = $timezone ?: (config('ai_agent.timezone') ?: config('app.timezone', 'Asia/Baghdad'));
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /**
     * @return array{status: 'ok'|'ambiguous'|'past'|'none', date?: string, options?: list<string>, reason?: string}
     */
    public function resolveDate(?string $text, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? $this->now())->startOfDay();
        if ($text === null || trim($text) === '') {
            return ['status' => 'none'];
        }

        $t = ' ' . TextNormalizer::normalize($text) . ' ';
        $t = preg_replace('/[?!,.;]+/u', ' ', $t) ?? $t;

        // ISO date 2026-11-11
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $t, $m)) {
            return $this->dated((int) $m[1], (int) $m[2], (int) $m[3], $now);
        }

        // 11/11 or 11/11/2026 (day first, as written in Iraq)
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/', $t, $m)) {
            $year = isset($m[3]) ? (int) $m[3] : null;
            if ($year !== null && $year < 100) {
                $year += 2000;
            }

            return $this->dated($year, (int) $m[2], (int) $m[1], $now, $year === null);
        }

        // 11 تشرين الثاني / November 11 / 11 Nov
        foreach (self::MONTHS as $name => $month) {
            if (preg_match('/(?<!\p{L})' . preg_quote($name, '/') . '(?!\p{L})/u', $t)) {
                $day = null;
                if (preg_match('/(\d{1,2})\s*(?:' . preg_quote($name, '/') . ')/u', $t, $m)
                    || preg_match('/(?:' . preg_quote($name, '/') . ')\s*(\d{1,2})(?!\d)/u', $t, $m)) {
                    $day = (int) $m[1];
                }
                if ($day) {
                    $year = preg_match('/(20\d{2})/', $t, $y) ? (int) $y[1] : null;

                    return $this->dated($year, $month, $day, $now, $year === null);
                }
            }
        }

        // Day after tomorrow (before "tomorrow")
        if (preg_match('/(عقب|بعد)\s*(باجر|باكر|بكره|بكرا|غدا|الغد|باجر)/u', $t) || str_contains($t, 'day after tomorrow')) {
            return ['status' => 'ok', 'date' => $now->addDays(2)->toDateString()];
        }
        if (preg_match('/(?<!\p{L})(باجر|باكر|بكره|بكرا|غدا|الغد|لباجر|لباكر|tomorrow|tmrw|tomorow|tommorow)(?!\p{L})/u', $t)) {
            return ['status' => 'ok', 'date' => $now->addDay()->toDateString()];
        }
        if (preg_match('/(?<!\p{L})(اليوم|هسه|هالحين|today|tonight)(?!\p{L})|this (morning|afternoon|evening)/u', $t)) {
            return ['status' => 'ok', 'date' => $now->toDateString()];
        }
        if (preg_match('/(?<!\p{L})(امس|البارحه|yesterday)(?!\p{L})/u', $t)) {
            return ['status' => 'past', 'date' => $now->subDay()->toDateString()];
        }

        // "بعد 3 ايام" / "in 3 days"
        if (preg_match('/(?:بعد|in)\s*(\d{1,2})\s*(?:ايام|يوم|days?)/u', $t, $m)) {
            return ['status' => 'ok', 'date' => $now->addDays((int) $m[1])->toDateString()];
        }

        $hasNext = $this->containsAny($t, self::NEXT);
        $nextWeek = (bool) preg_match('/(الاسبوع|السبوع)\s*(الجاي|القادم|الياي)|next week/u', $t);

        $weekday = $this->weekday($t);
        if ($weekday !== null) {
            $todayDow = $now->dayOfWeek;

            if ($nextWeek) {
                // That weekday inside next Saturday-to-Friday week
                $start = $now->startOfWeek(self::WEEK_START)->addWeek();
                $date = $start;
                while ($date->dayOfWeek !== $weekday) {
                    $date = $date->addDay();
                }

                return ['status' => 'ok', 'date' => $date->toDateString()];
            }

            $diff = ($weekday - $todayDow + 7) % 7;
            if ($diff === 0) {
                // "الخميس" said on a Thursday: today, or a week from today?
                return $hasNext
                    ? ['status' => 'ok', 'date' => $now->addWeek()->toDateString()]
                    : ['status' => 'ambiguous', 'reason' => 'same_weekday',
                        'options' => [$now->toDateString(), $now->addWeek()->toDateString()]];
            }

            return ['status' => 'ok', 'date' => $now->addDays($diff)->toDateString()];
        }

        if ($nextWeek || preg_match('/(الاسبوع|week)/u', $t)) {
            return ['status' => 'ambiguous', 'reason' => 'week_without_day'];
        }

        return ['status' => 'none'];
    }

    /**
     * @return array{status: 'ok'|'ambiguous_meridiem'|'period'|'none', time?: string, candidates?: list<string>, period?: string}
     */
    public function resolveTime(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return ['status' => 'none'];
        }

        $t = ' ' . TextNormalizer::normalize($text) . ' ';
        $t = $this->hourWords($t);
        $period = $this->period($t);
        $pm = (bool) preg_match('/(\d|\s)(pm|p\.m|م)(?!\p{L})/u', $t) || in_array($period, ['afternoon', 'evening'], true);
        $am = (bool) preg_match('/(\d|\s)(am|a\.m|ص)(?!\p{L})/u', $t) || $period === 'morning';

        // Strip date parts so 11/11 is not read as a time
        $t = preg_replace('/\d{4}-\d{1,2}-\d{1,2}|\d{1,2}\/\d{1,2}(\/\d{2,4})?/', ' ', $t) ?? $t;

        if (!preg_match('/(\d{1,2})(?:[:.](\d{2}))?/', $t, $m)) {
            if ($period === 'noon') {
                return ['status' => 'ok', 'time' => '12:00'];
            }

            return $period ? ['status' => 'period', 'period' => $period] : ['status' => 'none'];
        }

        $hour = (int) $m[1];
        $minute = isset($m[2]) ? (int) $m[2] : 0;

        if (!isset($m[2])) {
            if (preg_match('/(ونص|و نص|ونصف|half)/u', $t)) {
                $minute = 30;
            } elseif (preg_match('/(الا|إلا)\s*ربع/u', $t)) {
                $hour = $hour - 1;
                $minute = 45;
            } elseif (preg_match('/(وربع|و ربع|quarter past)/u', $t)) {
                $minute = 15;
            } elseif (preg_match('/(الا|إلا)\s*(ثلث)/u', $t)) {
                $hour = $hour - 1;
                $minute = 40;
            } elseif (preg_match('/(وثلث|و ثلث)/u', $t)) {
                $minute = 20;
            }
        }

        if ($hour < 0 || $hour > 23 || $minute > 59) {
            return ['status' => 'none'];
        }

        if ($hour > 12 || $hour === 0) {
            return ['status' => 'ok', 'time' => sprintf('%02d:%02d', $hour, $minute)];
        }

        if ($pm && !$am) {
            $h = $hour === 12 ? 12 : $hour + 12;

            return ['status' => 'ok', 'time' => sprintf('%02d:%02d', $h, $minute)];
        }
        if ($am && !$pm) {
            $h = $hour === 12 ? 0 : $hour;

            return ['status' => 'ok', 'time' => sprintf('%02d:%02d', $h, $minute)];
        }

        // "الساعة 5": 05:00 or 17:00; the caller picks by opening hours or asks
        $morning = sprintf('%02d:%02d', $hour === 12 ? 12 : $hour, $minute);
        $evening = sprintf('%02d:%02d', $hour === 12 ? 0 : $hour + 12, $minute);

        return ['status' => 'ambiguous_meridiem', 'candidates' => $hour === 12 ? [$morning] : [$morning, $evening]];
    }

    private function dated(?int $year, int $month, int $day, CarbonImmutable $now, bool $rollYear = false): array
    {
        $year ??= $now->year;
        if (!checkdate($month, $day, $year)) {
            return ['status' => 'none'];
        }

        $date = CarbonImmutable::create($year, $month, $day, 0, 0, 0, $this->timezone);
        if ($date->lt($now) && $rollYear) {
            $date = $date->addYear();
        }

        return ['status' => $date->lt($now) ? 'past' : 'ok', 'date' => $date->toDateString()];
    }

    /** "الساعة الخامسة" / "الساعة خمسة" / "at five" -> digits (longest words first). */
    private function hourWords(string $t): string
    {
        $words = [
            'الحاديه عشر' => 11, 'الحاديه عشره' => 11, 'الثانيه عشر' => 12, 'الثانيه عشره' => 12,
            'احدعش' => 11, 'اثنعش' => 12, 'الواحده' => 1, 'واحده' => 1, 'الثانيه' => 2, 'ثنتين' => 2,
            'الثالثه' => 3, 'ثلاثه' => 3, 'الرابعه' => 4, 'اربعه' => 4, 'الخامسه' => 5, 'خمسه' => 5,
            'السادسه' => 6, 'سته' => 6, 'السابعه' => 7, 'سبعه' => 7, 'الثامنه' => 8, 'ثمانيه' => 8, 'ثمنيه' => 8,
            'التاسعه' => 9, 'تسعه' => 9, 'العاشره' => 10, 'عشره' => 10,
            'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
            'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12,
        ];

        foreach ($words as $word => $hour) {
            $t = preg_replace('/(?<!\p{L})' . preg_quote($word, '/') . '(?!\p{L})/u', (string) $hour, $t) ?? $t;
        }

        return $t;
    }

    private function weekday(string $t): ?int
    {
        foreach (self::WEEKDAYS as $word => $dow) {
            if (preg_match('/(?<!\p{L})(ل|يوم\s|ب)?' . preg_quote($word, '/') . '(?!\p{L})/u', $t)) {
                return $dow;
            }
        }

        return null;
    }

    private function period(string $t): ?string
    {
        foreach (self::PERIODS as $period => $words) {
            if ($this->containsAny($t, $words)) {
                return $period;
            }
        }

        return null;
    }

    private function containsAny(string $t, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/(?<!\p{L})(ب|ل|و)?' . preg_quote($word, '/') . '(?!\p{L})/u', $t)) {
                return true;
            }
        }

        return false;
    }
}
