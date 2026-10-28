<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\Notification;
use App\Models\User;
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

    public function test_push_reaches_the_phone_in_both_languages_without_a_browser_link(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'os-1'])]);

        $notification = $this->sendBookingRequest();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onesignal.com/notifications'
            && $request->hasHeader('Authorization', 'Key os_v2_app_test')
            && $request['app_id'] === 'app-1'
            && $request['include_subscription_ids'] === ['phone-1']
            && $request['headings'] === ['en' => 'New booking request', 'ar' => 'طلب حجز جديد']
            && $request['contents'] === ['en' => 'Ali requested a visit.', 'ar' => 'طلب علي موعداً.']
            && $request['data']['action_url'] === '/booking-requests/5'
            && $request['priority'] === 10
            && !isset($request['url'])
            && !isset($request['android_channel_id']));
        $this->assertSame('os-1', $notification->fresh()->onesignal_notification_id);
        $this->assertSame(Notification::ONESIGNAL_SENT, $notification->fresh()->onesignal_status);
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
        $user = User::find($this->user(playerId: 'phone-1'));

        return app(NotificationService::class)->send($user, 'New booking request', 'Ali requested a visit.', [
            'type' => Notification::TYPE_APPOINTMENT,
            'priority' => Notification::PRIORITY_HIGH,
            'action_url' => '/booking-requests/5',
            'data' => ['title_ar' => 'طلب حجز جديد', 'body_ar' => 'طلب علي موعداً.'],
        ]);
    }

    private function user(?string $playerId = null): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'User',
            'phone' => '0770' . random_int(1000000, 9999999),
            'password' => 'x',
            'is_active' => true,
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
    }
}
