<?php

namespace App\Services\Messaging;

use App\Models\BookingRequest;
use App\Models\Conversation;
use App\Models\MessagingSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Tells a customer who booked through the WhatsApp bot (WhatsAppBookingBot) that
 * staff approved or rejected their request, on the same WhatsApp chat.
 *
 * Plain text only reaches them within 24 hours of their last message. For later
 * approvals the clinic sets meta.booking_approved_template, an approved template
 * of meta.booking_approved_text (same {{1}}, {{2}}… order as the booking reminder).
 */
class BookingRequestMessenger
{
    public const DEFAULT_APPROVED_TEXT = 'مرحباً {{patient_name}}، تم تأكيد موعدك في {{clinic_name}} يوم {{date}} الساعة {{time}}.';

    private const REJECTED_TEXT = 'نعتذر {{patient_name}}، لم نتمكن من تأكيد موعدك في {{clinic_name}} يوم {{date}} الساعة {{time}}.';

    public function __construct(private MessageService $messageService) {}

    public function approved(BookingRequest $request): void
    {
        $this->notify($request, function (array $meta) use ($request) {
            $reservation = $request->reservation()->with('doctor')->first();
            $date = $reservation?->reservation_start_date ?? $request->preferred_date;
            $time = $reservation && !$reservation->is_waiting ? $reservation->reservation_from_time : null;

            [$body, $params] = BookingReminderService::render($meta['booking_approved_text'] ?? self::DEFAULT_APPROVED_TEXT, [
                'patient_name' => $request->patient?->name ?? $request->name,
                'date' => $this->dateLabel($date),
                'time' => $time ? $this->timeLabel($time) : 'حسب الدور',
                'doctor_name' => $reservation?->doctor?->name ?? '',
                'clinic_name' => tenant('name') ?? '',
            ]);

            return [$body, empty($meta['booking_approved_template']) ? [] : [
                'template_name' => $meta['booking_approved_template'],
                'template_params' => $params,
            ]];
        });
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
     */
    private function notify(BookingRequest $request, callable $message): void
    {
        if ($request->source !== BookingRequest::SOURCE_WHATSAPP) {
            return;
        }

        $settings = MessagingSetting::active()->forProvider('whatsapp')->first();
        $meta = $settings?->meta ?? [];

        if (!$settings || ($meta['send_mode'] ?? 'api') !== 'api') {
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

    private function dateLabel(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->locale('ar')->translatedFormat('l j/n/Y');
    }

    private function timeLabel(string $time): string
    {
        return Carbon::parse($time)->locale('ar')->translatedFormat('h:i A');
    }
}
