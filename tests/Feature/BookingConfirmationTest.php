<?php

namespace Tests\Feature;

use App\Models\BookingRequest;
use App\Models\Reservation;
use App\Services\Messaging\BookingRequestMessenger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * With WhatsApp booking switched on (step 3 on the WhatsApp settings page),
 * every new booking sends the patient the booking confirmation on WhatsApp.
 */
class BookingConfirmationTest extends TestCase
{
    private const TEXT = 'مرحباً {{patient_name}}، موعدك {{date}} الساعة {{time}} مع {{doctor_name}}';

    private const TEMPLATE_URL = 'https://api.gupshup.io/wa/api/v1/template/msg';

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'confirmation_test',
            'database.connections.confirmation_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('confirmation_test');
        $this->createSchema();
        Queue::fake();
        Carbon::setTestNow('2026-11-12 10:00:00');
        Http::preventStrayRequests();
        Http::fake([
            'api.gupshup.io/wa/app/app-1/template*' => Http::response(['status' => 'success', 'templates' => [
                ['elementName' => 'booking_approved', 'languageCode' => 'ar', 'id' => 'tpl-ok'],
            ]]),
            'api.gupshup.io/wa/api/v1/template/msg' => Http::response(['status' => 'submitted', 'messageId' => 'gs-1']),
        ]);
    }

    public function test_a_new_booking_sends_the_confirmation_template(): void
    {
        app(BookingRequestMessenger::class)->reservationBooked($this->booking());

        Http::assertSent(fn (Request $request) => $request->url() === self::TEMPLATE_URL
            && $request['destination'] === '9647701234567'
            && json_decode($request['template'], true)['id'] === 'tpl-ok'
            && json_decode($request['template'], true)['params'][0] === 'Ahmed'
            && json_decode($request['template'], true)['params'][3] === 'Dr. Ali');

        $message = DB::table('messages')->first();
        $this->assertSame('sent', $message->status);
        $this->assertStringStartsWith('مرحباً Ahmed، موعدك ', $message->body);
        $this->assertStringContainsString('مع Dr. Ali', $message->body);
        $this->assertSame('booking_confirmation', json_decode($message->meta, true)['type']);
    }

    public function test_a_waiting_list_booking_says_the_time_follows_the_queue(): void
    {
        app(BookingRequestMessenger::class)->reservationBooked($this->booking(['is_waiting' => true]));

        Http::assertSent(fn (Request $request) => $request->url() === self::TEMPLATE_URL
            && json_decode($request['template'], true)['params'][2] === 'حسب الدور');
    }

    public function test_nothing_is_sent_while_whatsapp_booking_is_off_or_in_device_mode(): void
    {
        $this->setMeta(['booking_bot_enabled' => false]);
        app(BookingRequestMessenger::class)->reservationBooked($this->booking());

        $this->setMeta(['booking_bot_enabled' => true, 'send_mode' => 'device']);
        app(BookingRequestMessenger::class)->reservationBooked($this->booking());

        Http::assertNothingSent();
        $this->assertSame(0, DB::table('messages')->count());
    }

    public function test_past_cancelled_completed_and_phoneless_bookings_are_not_confirmed(): void
    {
        $messenger = app(BookingRequestMessenger::class);
        $messenger->reservationBooked($this->booking(['reservation_start_date' => '2026-11-11']));
        $messenger->reservationBooked($this->booking(['status_id' => 4]));
        $messenger->reservationBooked($this->booking(['status_id' => 3]));

        DB::table('patients')->update(['phone' => null]);
        $messenger->reservationBooked($this->booking());

        Http::assertNothingSent();
    }

    public function test_a_failed_send_never_breaks_the_booking(): void
    {
        DB::table('messaging_settings')->update(['whatsapp_access_token' => 'not-encrypted']);

        app(BookingRequestMessenger::class)->reservationBooked($this->booking());

        $this->assertTrue(true); // reaching here means nothing was thrown
    }

    public function test_an_approved_website_request_is_confirmed_only_while_whatsapp_booking_is_on(): void
    {
        $request = $this->approvedRequest(BookingRequest::SOURCE_WEBSITE);

        $this->setMeta(['booking_bot_enabled' => false]);
        app(BookingRequestMessenger::class)->approved($request);
        Http::assertNothingSent();

        $this->setMeta(['booking_bot_enabled' => true]);
        app(BookingRequestMessenger::class)->approved($request);
        Http::assertSentCount(2); // the template lookup and the message
        Http::assertSent(fn (Request $request) => $request->url() === self::TEMPLATE_URL);
    }

    private function booking(array $overrides = []): Reservation
    {
        $id = DB::table('reservations')->insertGetId(array_merge([
            'patient_id' => 1, 'doctor_id' => 1, 'status_id' => 1, 'is_waiting' => false,
            'reservation_start_date' => '2026-11-13', 'reservation_end_date' => '2026-11-13',
            'reservation_from_time' => '17:30:00',
        ], $overrides));

        return Reservation::find($id);
    }

    private function approvedRequest(string $source): BookingRequest
    {
        $reservation = $this->booking();
        $id = DB::table('booking_requests')->insertGetId([
            'name' => 'Ahmed', 'phone' => '07701234567', 'preferred_date' => '2026-11-13',
            'source' => $source, 'status' => BookingRequest::STATUS_APPROVED,
            'patient_id' => 1, 'reservation_id' => $reservation->id,
        ]);

        return BookingRequest::find($id);
    }

    private function setMeta(array $changes): void
    {
        $meta = json_decode(DB::table('messaging_settings')->value('meta'), true);
        DB::table('messaging_settings')->update(['meta' => json_encode(array_merge($meta, $changes))]);
    }

    private function createSchema(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('whatsapp');
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_webhook_verify_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->text('notes')->nullable();
            $table->date('reservation_start_date');
            $table->date('reservation_end_date')->nullable();
            $table->time('reservation_from_time');
            $table->time('reservation_to_time')->nullable();
            $table->boolean('is_waiting')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->text('note')->nullable();
            $table->string('source')->default('website');
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('channel')->default('whatsapp');
            $table->string('phone_number');
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
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
            $table->string('template_key')->nullable();
            $table->json('template_params')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'Dr. Ali']);
        DB::table('patients')->insert(['id' => 1, 'name' => 'Ahmed', 'phone' => '0770 123 4567']);
        DB::table('messaging_settings')->insert([
            'provider' => 'whatsapp',
            'whatsapp_access_token' => encrypt('key-123', false),
            'is_active' => true,
            'meta' => json_encode([
                'driver' => 'gupshup',
                'gupshup_app_name' => 'SmartClinicpluse',
                'gupshup_app_id' => 'app-1',
                'gupshup_source_number' => '9647700281899',
                'send_mode' => 'api',
                'booking_bot_enabled' => true,
                'booking_approved_template' => 'booking_approved',
                'booking_approved_text' => self::TEXT,
            ]),
        ]);
    }
}
