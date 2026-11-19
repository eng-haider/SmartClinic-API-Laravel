<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\WhatsAppBookingSession;
use App\Repositories\BookingRequestRepository;
use App\Services\BookingSlotService;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\WhatsAppBookingBot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Outside a booking, the WhatsApp booking bot greets only the first message of a
 * conversation (or one asking to book), not every message the customer sends.
 */
class WhatsAppBookingBotStartTest extends TestCase
{
    private const PHONE = '9647701234567';

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'bot_start_test',
            'database.connections.bot_start_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('bot_start_test');
        $this->createSchema();
        Carbon::setTestNow('2026-11-12 10:00:00');

        $messages = $this->createMock(MessageService::class);
        $messages->method('sendDirect')->willReturnCallback(function (string $phone, string $body) {
            $this->sent[] = $body;

            return new Message();
        });

        $slots = $this->createMock(BookingSlotService::class);
        $slots->method('availableDays')->willReturn([Carbon::parse('2026-11-13'), Carbon::parse('2026-11-14')]);
        $slots->method('freeTimes')->willReturn(['17:00', '17:30']);

        $this->app->instance(MessageService::class, $messages);
        $this->app->instance(BookingSlotService::class, $slots);
        $this->app->instance(BookingRequestRepository::class, $this->createMock(BookingRequestRepository::class));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_first_message_of_a_conversation_is_greeted(): void
    {
        $this->receive('السلام عليكم');

        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('لحجز موعد اختر اليوم المناسب', $this->sent[0]);
    }

    public function test_later_messages_after_a_cancelled_booking_are_left_to_staff(): void
    {
        $this->receive('السلام عليكم');
        $this->receive('0');
        $this->receive('عندي سؤال عن العلاج');
        $this->receive('شكراً');

        $this->assertCount(2, $this->sent, implode("\n---\n", $this->sent));
        $this->assertStringContainsString('تم إلغاء الحجز', $this->sent[1]);
    }

    public function test_asking_to_book_starts_the_bot_again(): void
    {
        $this->receive('السلام عليكم');
        $this->receive('0');
        $this->receive('اريد حجز');

        $this->assertCount(3, $this->sent);
        $this->assertStringContainsString('لحجز موعد اختر اليوم المناسب', $this->sent[2]);
    }

    public function test_a_customer_back_after_a_day_of_silence_is_greeted_again(): void
    {
        $this->receive('السلام عليكم');
        $this->receive('0');

        Carbon::setTestNow(now()->addHours(25));
        $this->receive('مرحبا');

        $this->assertCount(3, $this->sent);
        $this->assertStringContainsString('لحجز موعد اختر اليوم المناسب', $this->sent[2]);
    }

    public function test_answers_during_a_booking_still_go_to_the_bot(): void
    {
        $this->receive('السلام عليكم');
        $this->receive('1');

        $this->assertCount(2, $this->sent);
        $this->assertStringContainsString('اختر الوقت المناسب', $this->sent[1]);
        $this->assertSame(WhatsAppBookingSession::STEP_TIME, WhatsAppBookingSession::firstWhere('phone', self::PHONE)->step);
    }

    /**
     * What the webhook does: record the message, then pass it to the bot.
     */
    private function receive(string $text): void
    {
        Message::create([
            'direction' => Message::DIRECTION_INBOUND,
            'channel' => 'whatsapp',
            'from_number' => self::PHONE,
            'body' => $text,
            'status' => Message::STATUS_DELIVERED,
        ]);

        $this->app->make(WhatsAppBookingBot::class)->handle(self::PHONE, $text, null, null);
    }

    private function createSchema(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->string('whatsapp_webhook_verify_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_booking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 33)->unique();
            $table->string('step', 20);
            $table->json('data')->nullable();
            $table->timestamps();
        });
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->string('source')->default('website');
            $table->string('status')->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('direction');
            $table->string('channel')->default('whatsapp');
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->text('body')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('queued');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('messaging_settings')->insert([
            'provider' => 'whatsapp',
            'is_active' => true,
            'meta' => json_encode(['driver' => 'gupshup', 'send_mode' => 'api', 'booking_bot_enabled' => true]),
        ]);
    }
}
