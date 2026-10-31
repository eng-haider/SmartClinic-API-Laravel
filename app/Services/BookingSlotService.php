<?php

namespace App\Services;

use App\Models\BookingRequest;
use App\Models\Reservation;
use App\Repositories\ClinicSettingRepository;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Free appointment times, from the clinic's settings (working_hours,
 * appointment_duration, max_appointments_per_day) and what is already booked.
 * A time counts as taken when a reservation or a pending booking request
 * already starts at it.
 */
class BookingSlotService
{
    private const STATUS_CANCELLED = 4;

    // Seeded default, for a clinic that never saved its working hours
    private const DEFAULT_HOURS = [
        'sunday' => '09:00 - 17:00',
        'monday' => '09:00 - 17:00',
        'tuesday' => '09:00 - 17:00',
        'wednesday' => '09:00 - 17:00',
        'thursday' => '09:00 - 17:00',
        'friday' => 'Closed',
        'saturday' => 'Closed',
    ];

    public function __construct(private ClinicSettingRepository $settings) {}

    /**
     * The next days (up to $limit, within $lookahead days from today) that still
     * have a free time.
     *
     * @return list<Carbon>
     */
    public function availableDays(int $limit = 7, int $lookahead = 14): array
    {
        $days = [];

        for ($offset = 0; $offset < $lookahead && count($days) < $limit; $offset++) {
            $day = today()->addDays($offset);

            if ($this->freeTimes($day) !== []) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Free start times ("H:i") on a day, earliest first. Today's past times are left out.
     *
     * @return list<string>
     */
    public function freeTimes(CarbonInterface $day): array
    {
        $hours = $this->hoursFor($day);

        if ($hours === null) {
            return [];
        }

        $date = $day->toDateString();
        $reservations = Reservation::query()
            ->whereDate('reservation_start_date', $date)
            ->where(fn ($q) => $q->whereNull('status_id')->orWhere('status_id', '!=', self::STATUS_CANCELLED))
            ->get(['reservation_from_time', 'is_waiting']);
        $requests = BookingRequest::pending()
            ->whereDate('preferred_date', $date)
            ->pluck('preferred_time');

        $maxPerDay = (int) $this->setting('max_appointments_per_day');
        if ($maxPerDay > 0 && $reservations->count() + $requests->count() >= $maxPerDay) {
            return [];
        }

        $taken = $reservations->where('is_waiting', false)->pluck('reservation_from_time')
            ->merge($requests)
            ->filter()
            ->map(fn ($time) => substr($time, 0, 5))
            ->all();

        $duration = max(5, (int) $this->setting('appointment_duration') ?: 30);
        [$start, $end] = $hours;
        $times = [];

        for ($slot = $start->copy(); $slot->copy()->addMinutes($duration)->lte($end); $slot->addMinutes($duration)) {
            if ($slot->gt(now()) && !in_array($slot->format('H:i'), $taken, true)) {
                $times[] = $slot->format('H:i');
            }
        }

        return $times;
    }

    /**
     * Opening and closing time on the day, or null when the clinic is closed.
     * Settings saves "09:00 - 17:00" (older clinics have "9:00 AM - 5:00 PM") or "Closed".
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function hoursFor(CarbonInterface $day): ?array
    {
        $week = $this->setting('working_hours');
        $week = is_string($week) ? json_decode($week, true) : $week;
        $hours = (is_array($week) ? $week : self::DEFAULT_HOURS)[strtolower($day->englishDayOfWeek)] ?? null;

        // An unsaved form may still hold the editor's { enabled, from, to } shape
        if (is_array($hours)) {
            $hours = !empty($hours['enabled']) ? ($hours['from'] ?? '') . ' - ' . ($hours['to'] ?? '') : null;
        }

        if (!is_string($hours) || !str_contains($hours, '-')) {
            return null;
        }

        try {
            [$from, $to] = array_map(fn ($time) => Carbon::parse(trim($time))->format('H:i'), explode('-', $hours, 2));
        } catch (\Throwable) {
            return null;
        }

        $start = Carbon::parse($day->toDateString() . ' ' . $from);
        $end = Carbon::parse($day->toDateString() . ' ' . $to);

        // Closing at or after midnight ("16:00 - 00:00")
        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function setting(string $key): mixed
    {
        return $this->settings->getByKey($key)?->getValue();
    }
}
