<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\MessagingSetting;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The simple booking reminder set up on the WhatsApp settings page: when the
 * clinic sends by API, each booking gets one WhatsApp message a set number of
 * minutes before it starts.
 *
 * Settings live in the WhatsApp MessagingSetting's meta: send_mode ('api' or
 * 'device'), booking_reminder_enabled, booking_reminder_minutes,
 * booking_reminder_template (the approved template's name at Gupshup/Meta) and
 * booking_reminder_text (the same text, with {{patient_name}}-style placeholders).
 */
class BookingReminderService
{
    public const DEFAULT_TEXT = 'مرحباً {{patient_name}}، نذكرك بموعدك في {{clinic_name}} يوم {{date}} الساعة {{time}}.';

    private const STATUS_COMPLETED = 3;
    private const STATUS_CANCELLED = 4;

    public function __construct(private MessageService $messageService) {}

    /**
     * Send the reminders that are due now. Returns how many were sent.
     */
    public function sendDue(): int
    {
        $settings = MessagingSetting::active()->forProvider('whatsapp')->first();
        $meta = $settings?->meta ?? [];

        if (($meta['send_mode'] ?? 'api') !== 'api'
            || empty($meta['booking_reminder_enabled'])
            || empty($meta['booking_reminder_template'])) {
            return 0;
        }

        $now = now();
        $until = $now->copy()->addMinutes((int) ($meta['booking_reminder_minutes'] ?? 60));

        // Bookings starting within the window; one created closer to its time than
        // the window still gets its reminder right away, one already started never does
        $due = Reservation::query()
            ->with(['patient', 'doctor'])
            ->whereNull('reminder_sent_at')
            ->where('is_waiting', false)
            ->whereNotIn('status_id', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->whereBetween('reservation_start_date', [$now->toDateString(), $until->toDateString()])
            ->get()
            ->filter(function (Reservation $reservation) use ($now, $until) {
                $startsAt = $this->startsAt($reservation);

                return $reservation->patient?->phone && $startsAt->gt($now) && $startsAt->lte($until);
            });

        $sent = 0;

        foreach ($due as $reservation) {
            // Claim the booking first so overlapping runs never remind it twice
            $claimed = Reservation::query()
                ->whereKey($reservation->id)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => now()]);

            if (!$claimed) {
                continue;
            }

            try {
                $this->remind($reservation, $meta);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Booking reminder failed', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private function remind(Reservation $reservation, array $meta): void
    {
        $patient = $reservation->patient;
        $startsAt = $this->startsAt($reservation);

        [$body, $params] = self::render($meta['booking_reminder_text'] ?? self::DEFAULT_TEXT, [
            'patient_name' => $patient->name ?? '',
            'date' => $startsAt->format('Y-m-d'),
            'time' => $startsAt->format('h:i A'),
            'doctor_name' => $reservation->doctor->name ?? '',
            'clinic_name' => tenant('name') ?? '',
        ]);

        // The result (sent or failed) is kept on the Message record
        $this->messageService->sendDirect(
            $patient->phone,
            $body,
            'whatsapp',
            Conversation::findOrCreateForPatient($patient, 'whatsapp')->id,
            [
                'template_name' => $meta['booking_reminder_template'],
                'template_params' => $params,
                'meta' => ['type' => 'booking_reminder', 'reservation_id' => $reservation->id],
            ],
        );
    }

    /**
     * Fill the clinic's text and list the values in the order their placeholders
     * appear. The approved WhatsApp template is the same text with {{1}}, {{2}}…
     * in those places, so that order is its parameter order.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function render(string $text, array $variables): array
    {
        $pattern = '/{{\s*(\w+)\s*}}/';

        preg_match_all($pattern, $text, $matches);
        $params = array_map(fn ($key) => (string) ($variables[$key] ?? ''), $matches[1]);
        $body = preg_replace_callback($pattern, fn ($match) => (string) ($variables[$match[1]] ?? ''), $text);

        return [$body, $params];
    }

    private function startsAt(Reservation $reservation): Carbon
    {
        return Carbon::parse($reservation->reservation_start_date->toDateString() . ' ' . $reservation->reservation_from_time);
    }
}
