<?php

namespace App\Services\Messaging;

use App\Models\BookingRequest;
use App\Models\Conversation;
use App\Models\MessagingSetting;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Tells a customer who booked through the WhatsApp bot (WhatsAppBookingBot) that
 * staff approved or rejected their request, on the same WhatsApp chat.
 *
 * While WhatsApp booking is switched on (meta.booking_bot_enabled), every other
 * new booking gets the same confirmation too: one made on any page or by the
 * in-app AI assistant (reservationBooked), and an approved website request.
 *
 * Plain text only reaches them within 24 hours of their last message. For later
 * approvals, and for patients who never messaged the clinic, the clinic sets
 * meta.booking_approved_template, an approved template of
 * meta.booking_approved_text (same {{1}}, {{2}}… order as the booking reminder).
 */
class BookingRequestMessenger
{
    private const STATUS_COMPLETED = 3;
    private const STATUS_CANCELLED = 4;

    public const DEFAULT_APPROVED_TEXT = 'مرحباً {{patient_name}}، تم تأكيد موعدك في {{clinic_name}} يوم {{date}} الساعة {{time}}.';

    private const REJECTED_TEXT = 'نعتذر {{patient_name}}، لم نتمكن من تأكيد موعدك في {{clinic_name}} يوم {{date}} الساعة {{time}}.';

    public function __construct(private MessageService $messageService) {}

    public function approved(BookingRequest $request): void
    {
        $this->notify($request, fn (array $meta) => $this->confirmation(
            $meta,
            $request->reservation()->with('doctor')->first(),
            $request->patient?->name ?? $request->name,
            $request->preferred_date,
        ), everyBooking: true);
    }

    /**
     * A booking staff (or the AI assistant) just made. With WhatsApp booking on,
     * the patient gets the booking confirmation on WhatsApp.
     */
    public function reservationBooked(Reservation $reservation): void
    {
        $meta = $this->apiMeta();

        if ($meta === null || empty($meta['booking_bot_enabled'])) {
            return;
        }

        $reservation->loadMissing(['patient', 'doctor']);
        $patient = $reservation->patient;

        // Nothing to confirm for a visit already over, or entered after the fact
        if (!$patient?->phone
            || in_array((int) $reservation->status_id, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)
            || ($reservation->reservation_start_date && $reservation->reservation_start_date->lt(today()))) {
            return;
        }

        // Booking must not fail because the message did; its result is kept on the Message
        try {
            [$body, $options] = $this->confirmation($meta, $reservation, $patient->name);

            $this->messageService->sendDirect(
                $patient->phone,
                $body,
                'whatsapp',
                Conversation::findOrCreateForPatient($patient, 'whatsapp')->id,
                $options + ['meta' => ['type' => 'booking_confirmation', 'reservation_id' => $reservation->id]],
            );
        } catch (\Throwable $e) {
            Log::error('Booking confirmation WhatsApp message failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function rejected(BookingRequest $request): void
    {
        $this->notify($request, function () use ($request) {
            [$body] = BookingReminderService::render(self::REJECTED_TEXT, [
                'patient_name' => $request->name,
                'date' => $this->dateLabel($request->preferred_date),
                'time' => $request->preferred_time ? $this->timeLabel($request->preferred_time) : '',
                'clinic_name' => tenant('name') ?? '',
            ]);

            if ($request->rejection_reason) {
                $body .= "\nالسبب: {$request->rejection_reason}";
            }

            return [$body . "\nيمكنك مراسلتنا لحجز موعد آخر.", []];
        });
    }

    /**
     * @param callable(array): array{0: string, 1: array} $message the text and the send options, from the settings' meta
     * @param bool $everyBooking also tell website requests, while WhatsApp booking is on
     */
    private function notify(BookingRequest $request, callable $message, bool $everyBooking = false): void
    {
        $meta = $this->apiMeta();

        if ($meta === null) {
            return;
        }

        $fromBot = $request->source === BookingRequest::SOURCE_WHATSAPP;

        if (!$fromBot && !($everyBooking && !empty($meta['booking_bot_enabled']))) {
            return;
        }

        // Approving or rejecting must not fail because the message did; its result is kept on the Message
        try {
            [$body, $options] = $message($meta);

            $this->messageService->sendDirect(
                $request->phone,
                $body,
                'whatsapp',
                $request->patient ? Conversation::findOrCreateForPatient($request->patient, 'whatsapp')->id : null,
                $options + ['meta' => ['type' => 'booking_request_' . $request->status, 'booking_request_id' => $request->id]],
            );
        } catch (\Throwable $e) {
            Log::error('Booking request WhatsApp message failed', [
                'booking_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The WhatsApp settings' meta when the clinic sends by API, else null.
     */
    private function apiMeta(): ?array
    {
        $settings = MessagingSetting::active()->forProvider('whatsapp')->first();
        $meta = $settings?->meta ?? [];

        return $settings && ($meta['send_mode'] ?? 'api') === 'api' ? $meta : null;
    }

    /**
     * The booking confirmation (meta.booking_approved_text) and its template, if any.
     *
     * @return array{0: string, 1: array}
     */
    private function confirmation(array $meta, ?Reservation $reservation, ?string $patientName, ?\DateTimeInterface $fallbackDate = null): array
    {
        $date = $reservation?->reservation_start_date ?? $fallbackDate;
        $time = $reservation && !$reservation->is_waiting ? $reservation->reservation_from_time : null;

        [$body, $params] = BookingReminderService::render(($meta['booking_approved_text'] ?? '') ?: self::DEFAULT_APPROVED_TEXT, [
            'patient_name' => $patientName ?? '',
            'date' => $date ? $this->dateLabel($date) : '',
            'time' => $time ? $this->timeLabel($time) : 'حسب الدور',
            'doctor_name' => $reservation?->doctor?->name ?? '',
            'clinic_name' => tenant('name') ?? '',
        ]);

        return [$body, empty($meta['booking_approved_template']) ? [] : [
            'template_name' => $meta['booking_approved_template'],
            'template_params' => $params,
        ]];
    }

    private function dateLabel(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->locale('ar')->translatedFormat('l j/n/Y');
    }

    private function timeLabel(string $time): string
    {
        return Carbon::parse($time)->locale('ar')->translatedFormat('h:i A');
    }
}
