<?php

namespace App\Services\Messaging;

use App\Models\BookingRequest;
use App\Models\MessagingSetting;
use App\Models\Patient;
use App\Models\WhatsAppBookingSession;
use App\Repositories\BookingRequestRepository;
use App\Services\BookingSlotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Lets a customer book from WhatsApp: any message to the clinic's number starts
 * it, they pick a day, then a time, then give their name (skipped when the
 * number belongs to a known patient), and a pending booking request is made for
 * staff to approve on the Booking Requests page. BookingRequestMessenger tells
 * them the outcome.
 *
 * Choices go out as WhatsApp lists; each message also carries a numbered menu,
 * so typing the number works too (and is all that's left if a list is refused).
 * Switched on by meta.booking_bot_enabled on the clinic's WhatsApp settings,
 * while it sends by API.
 */
class WhatsAppBookingBot
{
    // A customer who goes quiet this long starts over with their next message
    private const SESSION_MINUTES = 30;

    // WhatsApp shows at most 10 rows in a list; a longer list of times is paged
    private const LIST_ROWS = 10;

    private const CANCEL_WORDS = ['0', 'الغاء', 'إلغاء', 'cancel', 'stop'];

    private const MORE = 'more';

    private string $phone;

    private ?int $conversationId;

    public function __construct(
        private MessageService $messages,
        private BookingSlotService $slots,
        private BookingRequestRepository $bookingRequests,
    ) {}

    public static function enabled(): bool
    {
        $settings = MessagingSetting::active()->forProvider('whatsapp')->first();

        return ($settings?->meta['send_mode'] ?? 'api') === 'api' && !empty($settings?->meta['booking_bot_enabled']);
    }

    /**
     * Answer one message from a customer: what they typed, or the row they tapped.
     * Never throws, so a failure can't lose the message itself.
     */
    public function handle(string $phone, string $reply, ?Patient $patient, ?int $conversationId): void
    {
        if (!self::enabled()) {
            return;
        }

        $this->phone = $phone;
        $this->conversationId = $conversationId;

        try {
            $session = WhatsAppBookingSession::firstWhere('phone', $phone);

            if ($session && $session->updated_at->lt(now()->subMinutes(self::SESSION_MINUTES))) {
                $session->delete();
                $session = null;
            }

            if (!$session) {
                $this->askDay(null, $patient?->name, true);

                return;
            }

            if (in_array(mb_strtolower(trim($this->digits($reply))), self::CANCEL_WORDS, true)) {
                $session->delete();
                $this->send('تم إلغاء الحجز. أرسل أي رسالة متى أردت حجز موعد.');

                return;
            }

            match ($session->step) {
                WhatsAppBookingSession::STEP_DAY => $this->onDay($session, $reply),
                WhatsAppBookingSession::STEP_TIME => $this->onTime($session, $reply),
                WhatsAppBookingSession::STEP_NAME => $this->onName($session, $reply),
            };
        } catch (\Throwable $e) {
            Log::error('WhatsApp booking bot failed', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }

    private function onDay(WhatsAppBookingSession $session, string $reply): void
    {
        $days = $session->data['days'] ?? [];
        $index = $this->choice($reply, count($days));

        if ($index === null) {
            $this->askDay($session, $session->data['name'] ?? null, false, '⚠️ اختر رقم اليوم من القائمة.');

            return;
        }

        $this->askTime($session, Carbon::parse($days[$index]));
    }

    private function onTime(WhatsAppBookingSession $session, string $reply): void
    {
        $day = Carbon::parse($session->data['date']);
        $times = $session->data['times'] ?? [];

        if ($reply === self::MORE || str_contains($reply, 'المزيد')) {
            $pages = (int) ceil(count($times) / (self::LIST_ROWS - 1));
            $this->askTime($session, $day, null, (($session->data['page'] ?? 0) + 1) % max($pages, 1));

            return;
        }

        $index = $this->choice($reply, count($times));

        if ($index === null) {
            $this->askTime($session, $day, '⚠️ اختر رقم الوقت من القائمة.', $session->data['page'] ?? 0);

            return;
        }

        // Someone else may have taken it while the customer was choosing
        if (!in_array($times[$index], $this->slots->freeTimes($day), true)) {
            $this->askTime($session, $day, 'عذراً، هذا الوقت حُجز للتو. اختر وقتاً آخر.');

            return;
        }

        $data = ['time' => $times[$index]] + $session->data;

        if (!empty($data['name'])) {
            $this->submit($session, $data);

            return;
        }

        $session->update(['step' => WhatsAppBookingSession::STEP_NAME, 'data' => $data]);
        $this->send('ما الاسم الكامل للمريض؟');
    }

    private function onName(WhatsAppBookingSession $session, string $reply): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $reply));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !preg_match('/\p{L}/u', $name)) {
            $this->send('⚠️ اكتب الاسم الكامل للمريض.');

            return;
        }

        $this->submit($session, ['name' => $name] + $session->data);
    }

    /**
     * Greet the customer (first message) or ask again, with the days that still have free times.
     */
    private function askDay(?WhatsAppBookingSession $session, ?string $name, bool $greet, ?string $notice = null): void
    {
        $days = $this->slots->availableDays();

        if ($days === []) {
            $session?->delete();
            $this->send('عذراً، لا توجد مواعيد متاحة حالياً. يرجى التواصل مع العيادة مباشرة.');

            return;
        }

        $data = ['days' => array_map(fn (Carbon $day) => $day->toDateString(), $days), 'name' => $name];

        if ($session) {
            $session->update(['step' => WhatsAppBookingSession::STEP_DAY, 'data' => $data]);
        } else {
            WhatsAppBookingSession::updateOrCreate(
                ['phone' => $this->phone],
                ['step' => WhatsAppBookingSession::STEP_DAY, 'data' => $data],
            );
        }

        $lines = [];
        if ($greet) {
            $lines[] = 'أهلاً بك في ' . (tenant('name') ?: 'العيادة') . ' 👋';
            if ($pending = $this->pendingRequest()) {
                $lines[] = 'طلب حجزك ليوم ' . $this->dayLabel($pending->preferred_date)
                    . ($pending->preferred_time ? ' الساعة ' . $this->timeLabel($pending->preferred_time) : '')
                    . ' قيد المراجعة، وسنراسلك عند تأكيده.';
            }
        }
        if ($notice) {
            $lines[] = $notice;
        }
        $lines[] = 'لحجز موعد اختر اليوم المناسب:';

        $rows = [];
        foreach ($days as $i => $day) {
            $rows[] = [
                'id' => (string) ($i + 1),
                'title' => ($i + 1) . '. ' . $this->dayLabel($day),
                'description' => $day->copy()->locale('ar')->translatedFormat('j F Y'),
            ];
        }

        $this->sendMenu(implode("\n", $lines), 'اختر اليوم', $rows);
    }

    /**
     * Ask for a time on the chosen day. Every time is numbered in the text; the
     * list shows a page of them with a row for the next page.
     */
    private function askTime(WhatsAppBookingSession $session, Carbon $day, ?string $notice = null, int $page = 0): void
    {
        $times = $this->slots->freeTimes($day);

        if ($times === []) {
            $this->askDay($session, $session->data['name'] ?? null, false, 'عذراً، لم يعد في هذا اليوم وقت متاح.');

            return;
        }

        $session->update([
            'step' => WhatsAppBookingSession::STEP_TIME,
            'data' => ['date' => $day->toDateString(), 'times' => $times, 'page' => $page] + $session->data,
        ]);

        $rows = [];
        foreach ($times as $i => $time) {
            $rows[] = [
                'id' => (string) ($i + 1),
                'title' => ($i + 1) . '. ' . $this->timeLabel($time),
                'description' => $this->dayLabel($day),
            ];
        }

        $listRows = $rows;
        if (count($rows) > self::LIST_ROWS) {
            $perPage = self::LIST_ROWS - 1;
            $listRows = array_slice($rows, ($page * $perPage) % count($rows), $perPage);
            $listRows[] = ['id' => self::MORE, 'title' => 'المزيد من الأوقات ⏩', 'description' => 'أوقات أخرى في نفس اليوم'];
        }

        $prompt = ($notice ? $notice . "\n" : '') . 'اختر الوقت المناسب ليوم ' . $this->dayLabel($day) . ':';

        $this->sendMenu($prompt, 'اختر الوقت', $rows, $listRows);
    }

    private function submit(WhatsAppBookingSession $session, array $data): void
    {
        $this->bookingRequests->create([
            'name' => $data['name'],
            'phone' => $this->localPhone($this->phone),
            'preferred_date' => $data['date'],
            'preferred_time' => $data['time'],
            'source' => BookingRequest::SOURCE_WHATSAPP,
        ]);

        $session->delete();

        $day = Carbon::parse($data['date']);
        $this->send(implode("\n", [
            '✅ تم استلام طلب الحجز',
            '👤 ' . $data['name'],
            '📅 ' . $this->dayLabel($day) . ' ' . $day->format('Y'),
            '🕐 ' . $this->timeLabel($data['time']),
            'سنرسل لك رسالة هنا عند تأكيد الموعد.',
        ]));
    }

    private function pendingRequest(): ?BookingRequest
    {
        return BookingRequest::pending()
            ->where('source', BookingRequest::SOURCE_WHATSAPP)
            ->where('phone', $this->localPhone($this->phone))
            ->whereDate('preferred_date', '>=', today())
            ->latest()
            ->first();
    }

    /**
     * Send choices as a WhatsApp list, with the same choices numbered in the text.
     *
     * @param list<array{id: string, title: string, description: string}> $rows every choice
     * @param list<array{id: string, title: string, description: string}>|null $listRows the ones the list shows, when not all
     */
    private function sendMenu(string $prompt, string $button, array $rows, ?array $listRows = null): void
    {
        $hint = 'أرسل الرقم، أو 0 لإلغاء الحجز.';
        $menu = implode("\n", array_column($rows, 'title'));

        $this->send("{$prompt}\n\n{$menu}\n\n{$hint}", [
            'list' => [
                'title' => 'حجز موعد',
                'body' => "{$prompt}\n\n{$hint}",
                'button' => $button,
                'rows' => $listRows ?? $rows,
            ],
        ]);
    }

    private function send(string $body, array $options = []): void
    {
        $this->messages->sendDirect($this->phone, $body, 'whatsapp', $this->conversationId, $options + [
            'meta' => ['type' => 'booking_bot'],
        ]);
    }

    /**
     * The 0-based position of the choice the customer typed or tapped. A tapped row
     * comes back as its id ("3") or its title ("3. 05:30 م"), so the leading number
     * is what counts.
     */
    private function choice(string $reply, int $count): ?int
    {
        if (!preg_match('/^\s*(\d{1,3})(?!\d)/', $this->digits($reply), $match)) {
            return null;
        }

        $number = (int) $match[1];

        return $number >= 1 && $number <= $count ? $number - 1 : null;
    }

    /**
     * Arabic-Indic and Persian digits (٣ ۳) as 0-9.
     */
    private function digits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /**
     * WhatsApp gives the number as 9647701234567; staff are used to 07701234567.
     */
    private function localPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $countryCode = (string) (MessagingSetting::active()->forProvider('whatsapp')->first()?->meta['country_code'] ?? '964');

        return str_starts_with($digits, $countryCode) ? '0' . substr($digits, strlen($countryCode)) : '+' . $digits;
    }

    private function dayLabel(\DateTimeInterface $day): string
    {
        return Carbon::instance($day)->locale('ar')->translatedFormat('l j/n');
    }

    private function timeLabel(string $time): string
    {
        return Carbon::parse($time)->locale('ar')->translatedFormat('h:i A');
    }
}
