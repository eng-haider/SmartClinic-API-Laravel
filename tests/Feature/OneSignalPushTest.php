<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\BookingRequestRepository;
use App\Repositories\PatientRepository;
use App\Services\Messaging\BookingRequestMessenger;
use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OneSignalPushTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated memory database, like PatientPhotoTest: never touch a clinic.
        config([
            'database.default' => 'onesignal_push_test',
            'database.connections.onesignal_push_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'onesignal.app_id' => 'app-1',
            'onesignal.rest_api_key' => 'os_v2_app_test',
            'onesignal.rest_api_url' => 'https://api.onesignal.com',
        ]);
        DB::purge('onesignal_push_test');
        $this->createSchema();
        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
    }

    public function test_the_app_links_a_phone_and_takes_it_from_the_previous_account(): void
    {
        $previous = $this->user(playerId: 'phone-1');
        $this->actingAs(User::find($this->user()));

        $this->postJson('/api/tenant/notifications/onesignal/subscribe', ['player_id' => 'phone-1'])
            ->assertOk();

        $this->assertSame('phone-1', auth()->user()->fresh()->onesignal_player_id);
        $this->assertNull(User::find($previous)->onesignal_player_id);
    }

    public function test_an_installed_legacy_app_can_register_its_phone_at_the_original_endpoint(): void
    {
        $this->actingAs(User::find($this->user()));

        $this->postJson('/api/notifications/player-id', ['player_id' => 'existing-phone'])
            ->assertOk();

        $this->assertSame('existing-phone', auth()->user()->fresh()->onesignal_player_id);
    }

    public function test_the_app_can_get_a_stable_external_id_without_saving_a_phone_subscription(): void
    {
        $user = User::find($this->user(playerId: 'legacy-phone'));
        $this->actingAs($user);

        $this->getJson('/api/tenant/notifications/onesignal/external-id')
            ->assertOk()
            ->assertJsonPath('external_id', 'central:' . $user->id);

        $this->assertNull($user->fresh()->onesignal_player_id);
    }

    public function test_sign_out_unlinks_only_the_phone_it_came_from(): void
    {
        $this->actingAs(User::find($this->user(playerId: 'newer-phone')));

        $this->postJson('/api/tenant/notifications/onesignal/unsubscribe', ['player_id' => 'older-phone'])
            ->assertOk();
        $this->assertSame('newer-phone', auth()->user()->fresh()->onesignal_player_id);

        $this->postJson('/api/tenant/notifications/onesignal/unsubscribe', ['player_id' => 'newer-phone'])
            ->assertOk();
        $this->assertNull(auth()->user()->fresh()->onesignal_player_id);
    }

    public function test_push_targets_the_user_external_id_in_both_languages_without_a_device_link(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'os-1'])]);

        $notification = $this->sendBookingRequest();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onesignal.com/notifications'
            && $request->hasHeader('Authorization', 'Key os_v2_app_test')
            && $request['app_id'] === 'app-1'
            && $request['include_aliases'] === ['external_id' => ['central:1']]
            && $request['headings'] === ['en' => 'New booking request', 'ar' => 'طلب حجز جديد']
            && $request['contents'] === ['en' => 'Ali requested a visit.', 'ar' => 'طلب علي موعداً.']
            && $request['data']['action_url'] === '/booking-requests/5'
            && $request['priority'] === 10
            && !isset($request['url'])
            && !isset($request['include_subscription_ids'])
            && !isset($request['android_channel_id']));
        $this->assertSame('os-1', $notification->fresh()->onesignal_notification_id);
        $this->assertSame(Notification::ONESIGNAL_SENT, $notification->fresh()->onesignal_status);
    }

    public function test_push_prefers_the_existing_phone_subscription_for_an_installed_app(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'os-legacy'])]);
        $user = User::find($this->user(playerId: 'existing-phone'));

        app(NotificationService::class)->send($user, 'Legacy', 'Works without an app update');

        Http::assertSent(fn (Request $request) => $request['include_subscription_ids'] === ['existing-phone']
            && !isset($request['include_aliases']));
    }

    public function test_a_public_booking_request_pushes_every_active_clinic_user(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'os-booking'])]);

        $first = $this->user();
        $second = $this->user();
        $this->user(active: false);

        $patients = \Mockery::mock(PatientRepository::class);
        $patients->shouldNotReceive('findByPhoneNumber');
        $messenger = \Mockery::mock(BookingRequestMessenger::class);
        $messenger->shouldNotReceive('approved');
        $messenger->shouldNotReceive('rejected');

        $booking = (new BookingRequestRepository(
            app(NotificationService::class),
            $patients,
            $messenger,
        ))->create([
            'name' => 'Ali',
            'phone' => '07701234567',
            'preferred_date' => '2026-11-16',
            'preferred_time' => '10:30:00',
            'source' => 'website',
        ]);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(Notification::ONESIGNAL_SENT, Notification::where('notifiable_id', $first)->value('onesignal_status'));
        $this->assertSame(Notification::ONESIGNAL_SENT, Notification::where('notifiable_id', $second)->value('onesignal_status'));

        foreach ([$first, $second] as $userId) {
            Http::assertSent(fn (Request $request) => $request['include_aliases'] === [
                'external_id' => ['central:' . $userId],
            ]
                && $request['data']['booking_request_id'] === $booking->id
                && $request['data']['action_url'] === '/booking-requests/' . $booking->id
                && $request['priority'] === 10);
        }
    }

    public function test_a_rejected_push_is_recorded_and_the_notification_kept(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['errors' => ['Access denied']], 403)]);

        $notification = $this->sendBookingRequest();

        $this->assertSame(Notification::ONESIGNAL_FAILED, $notification->fresh()->onesignal_status);
        $this->assertStringContainsString('Access denied', $notification->fresh()->onesignal_error);
    }

    private function sendBookingRequest(): Notification
    {
        $user = User::find($this->user());

        return app(NotificationService::class)->send($user, 'New booking request', 'Ali requested a visit.', [
            'type' => Notification::TYPE_APPOINTMENT,
            'priority' => Notification::PRIORITY_HIGH,
            'action_url' => '/booking-requests/5',
            'data' => ['title_ar' => 'طلب حجز جديد', 'body_ar' => 'طلب علي موعداً.'],
        ]);
    }

    private function user(?string $playerId = null, bool $active = true): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'User',
            'phone' => '0770' . random_int(1000000, 9999999),
            'password' => 'x',
            'is_active' => $active,
            'onesignal_player_id' => $playerId,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('onesignal_player_id')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general');
            $table->json('data')->nullable();
            $table->string('onesignal_notification_id')->nullable();
            $table->string('onesignal_status')->default('pending');
            $table->text('onesignal_error')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('priority')->default('medium');
            $table->string('action_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
