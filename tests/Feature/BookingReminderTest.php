<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Services\Messaging\BookingReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingReminderTest extends TestCase
{
    private const TEXT = 'مرحباً {{patient_name}}، موعدك الساعة {{time}} مع {{doctor_name}}';

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'reminder_test',
            'database.connections.reminder_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('reminder_test');
        $this->createSchema();
        Queue::fake();
        Carbon::setTestNow('2026-10-27 10:00:00');
        Http::preventStrayRequests();
        Http::fake([
            'api.gupshup.io/wa/app/app-1/template*' => Http::response(['status' => 'success', 'templates' => [
                ['elementName' => 'booking_reminder', 'languageCode' => 'ar', 'id' => 'tpl-1'],
            ]]),
            'api.gupshup.io/wa/api/v1/template/msg' => Http::response(['status' => 'submitted', 'messageId' => 'gs-1']),
        ]);
    }

    public function test_only_bookings_starting_within_the_window_are_reminded_once(): void
    {
        $due = $this->booking('10:45:00');
        $later = $this->booking('11:30:00');
        $started = $this->booking('09:30:00');
        $cancelled = $this->booking('10:30:00', ['status_id' => 4]);
        $waiting = $this->booking('10:30:00', ['is_waiting' => true]);
        $alreadySent = $this->booking('10:30:00', ['reminder_sent_at' => '2026-10-27 09:00:00']);

        $this->assertSame(1, app(BookingReminderService::class)->sendDue());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.gupshup.io/wa/api/v1/template/msg'
            && $request['destination'] === '9647701234567'
            && json_decode($request['template'], true) === ['id' => 'tpl-1', 'params' => ['Ahmed', '10:45 AM', 'Dr. Ali']]);
        $this->assertNotNull(DB::table('reservations')->where('id', $due)->value('reminder_sent_at'));
        foreach ([$later, $started, $cancelled, $waiting] as $id) {
            $this->assertNull(DB::table('reservations')->where('id', $id)->value('reminder_sent_at'));
        }
        $this->assertSame('2026-10-27 09:00:00', DB::table('reservations')->where('id', $alreadySent)->value('reminder_sent_at'));
        $this->assertDatabaseHas('messages', [
            'direction' => 'outbound', 'external_id' => 'gs-1', 'status' => 'sent',
            'body' => 'مرحباً Ahmed، موعدك الساعة 10:45 AM مع Dr. Ali',
        ]);

        // The next minute's run does not remind it again
        $this->assertSame(0, app(BookingReminderService::class)->sendDue());
    }

    public function test_bookings_on_the_next_day_are_reminded_inside_a_long_window(): void
    {
        $this->setMeta(['booking_reminder_minutes' => 1440]);
        $tomorrow = $this->booking('09:00:00', ['reservation_start_date' => '2026-10-28']);
        $this->booking('11:00:00', ['reservation_start_date' => '2026-10-28']);

        $this->assertSame(1, app(BookingReminderService::class)->sendDue());
        $this->assertNotNull(DB::table('reservations')->where('id', $tomorrow)->value('reminder_sent_at'));
    }

    public function test_nothing_is_sent_by_device_mode_or_when_switched_off(): void
    {
        $this->booking('10:45:00');

        $this->setMeta(['send_mode' => 'device']);
        $this->assertSame(0, app(BookingReminderService::class)->sendDue());

        $this->setMeta(['send_mode' => 'api', 'booking_reminder_enabled' => false]);
        $this->assertSame(0, app(BookingReminderService::class)->sendDue());

        Http::assertNothingSent();
    }

    public function test_moving_a_booking_to_a_new_time_reminds_it_again(): void
    {
        $id = $this->booking('10:45:00', ['reminder_sent_at' => '2026-10-27 09:50:00']);

        $reservation = Reservation::find($id);
        $reservation->update(['notes' => 'bring x-ray']);
        $this->assertNotNull($reservation->fresh()->reminder_sent_at);

        $reservation->update(['reservation_from_time' => '10:50:00']);
        $this->assertNull($reservation->fresh()->reminder_sent_at);
    }

    public function test_template_values_follow_the_order_of_the_placeholders(): void
    {
        [$body, $params] = BookingReminderService::render(
            '{{time}} - {{patient_name}} - {{time}} {{unknown}}',
            ['patient_name' => 'Sara', 'time' => '09:00 AM'],
        );

        $this->assertSame('09:00 AM - Sara - 09:00 AM ', $body);
        $this->assertSame(['09:00 AM', 'Sara', '09:00 AM', ''], $params);
    }

    private function booking(string $time, array $overrides = []): int
    {
        return DB::table('reservations')->insertGetId(array_merge([
            'patient_id' => 1, 'doctor_id' => 1, 'status_id' => 1, 'is_waiting' => false,
            'reservation_start_date' => '2026-10-27', 'reservation_end_date' => '2026-10-27',
            'reservation_from_time' => $time,
        ], $overrides));
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
                'booking_reminder_enabled' => true,
                'booking_reminder_minutes' => 60,
                'booking_reminder_template' => 'booking_reminder',
                'booking_reminder_text' => self::TEXT,
            ]),
        ]);
    }
}
