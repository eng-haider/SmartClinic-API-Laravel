<?php

namespace Tests\Feature;

use App\Services\Messaging\ChannelManager;
use App\Services\Messaging\Channels\GupshupChannel;
use App\Services\Messaging\Channels\WhatsAppChannel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GupshupWhatsAppTest extends TestCase
{
    private const WEBHOOK = '/api/webhooks/gupshup/clinic_1/secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        // Without bootstrappers, $tenant->run() stays on this same database.
        config([
            'database.default' => 'gupshup_test',
            'database.connections.gupshup_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'tenancy.database.central_connection' => 'gupshup_test',
            'tenancy.bootstrappers' => [],
        ]);
        DB::purge('gupshup_test');
        $this->createSchema();
        Http::preventStrayRequests();
    }

    public function test_text_message_goes_through_the_clinics_gupshup_app(): void
    {
        Http::fake(['api.gupshup.io/wa/api/v1/msg' => Http::response(['status' => 'submitted', 'messageId' => 'gs-1'])]);

        $driver = app(ChannelManager::class)->driver('whatsapp');
        $result = $driver->send('0770 123 4567', 'مرحبا');

        $this->assertInstanceOf(GupshupChannel::class, $driver);
        $this->assertSame(['success' => true, 'external_id' => 'gs-1', 'error' => null], $result);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.gupshup.io/wa/api/v1/msg'
            && $request->header('apikey') === ['key-123']
            && $request['channel'] === 'whatsapp'
            && $request['source'] === '9647700281899'
            && $request['destination'] === '9647701234567'
            && str_contains($request->body(), 'src.name=SmartClinicpluse')
            && json_decode($request['message'], true) === ['type' => 'text', 'text' => 'مرحبا']);
    }

    public function test_template_is_sent_by_its_gupshup_id_in_the_clinics_language(): void
    {
        Http::fake([
            'api.gupshup.io/wa/app/app-1/template*' => Http::response(['status' => 'success', 'templates' => [
                ['elementName' => 'appointment_reminder', 'languageCode' => 'en', 'id' => 'tpl-en'],
                ['elementName' => 'appointment_reminder', 'languageCode' => 'ar', 'id' => 'tpl-ar'],
            ]]),
            'api.gupshup.io/wa/api/v1/template/msg' => Http::response(['status' => 'submitted', 'messageId' => 'gs-2']),
        ]);

        $result = app(ChannelManager::class)->driver('whatsapp')->send('+964 770 123 4567', 'body', [
            'template_name' => 'appointment_reminder',
            'template_params' => ['patient_name' => 'أحمد', 'days' => 5],
        ]);

        $this->assertSame('gs-2', $result['external_id']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'template?templateStatus=APPROVED')
            && $request->header('apikey') === ['key-123']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.gupshup.io/wa/api/v1/template/msg'
            && $request['destination'] === '9647701234567'
            && json_decode($request['template'], true) === ['id' => 'tpl-ar', 'params' => ['أحمد', '5']]);
    }

    public function test_unknown_template_fails_without_sending_anything(): void
    {
        Http::fake(['api.gupshup.io/wa/app/app-1/template*' => Http::response(['status' => 'success', 'templates' => []])]);

        $result = app(ChannelManager::class)->driver('whatsapp')->send('07701234567', 'body', ['template_name' => 'missing']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString("'missing'", $result['error']);
        // The cached list is refreshed once in case the template was just approved
        Http::assertSentCount(2);
    }

    public function test_gupshup_errors_are_returned(): void
    {
        Http::fake(['api.gupshup.io/wa/api/v1/msg' => Http::response(['status' => 'error', 'message' => 'Invalid App Details'], 400)]);

        $result = app(ChannelManager::class)->driver('whatsapp')->send('07701234567', 'Hi');

        $this->assertSame(['success' => false, 'external_id' => null, 'error' => 'Invalid App Details'], $result);
    }

    public function test_meta_stays_the_driver_unless_gupshup_is_picked(): void
    {
        DB::table('messaging_settings')->update(['meta' => json_encode(['driver' => 'meta'])]);

        $this->assertInstanceOf(WhatsAppChannel::class, app(ChannelManager::class)->driver('whatsapp'));
    }

    public function test_inbound_message_is_recorded_in_the_patients_conversation(): void
    {
        $patientId = DB::table('patients')->insertGetId(['name' => 'Ahmed', 'phone' => '0770 123 4567']);

        $this->postJson(self::WEBHOOK, [
            'app' => 'SmartClinicpluse', 'timestamp' => 1718007189549, 'version' => 2, 'type' => 'message',
            'payload' => [
                'id' => 'wamid-in', 'source' => '9647701234567', 'type' => 'text',
                'payload' => ['text' => 'متى موعدي؟'],
                'sender' => ['phone' => '9647701234567', 'name' => 'Ahmed', 'country_code' => '964', 'dial_code' => '7701234567'],
            ],
        ])->assertOk();

        $conversationId = DB::table('conversations')->where('patient_id', $patientId)->value('id');
        $this->assertNotNull($conversationId);
        $this->assertDatabaseHas('messages', [
            'direction' => 'inbound', 'conversation_id' => $conversationId, 'from_number' => '9647701234567',
            'body' => 'متى موعدي؟', 'external_id' => 'wamid-in',
        ]);
        $this->assertDatabaseHas('webhook_logs', ['source' => 'gupshup', 'event_type' => 'message', 'status' => 'processed']);
    }

    public function test_status_events_update_the_sent_message_and_never_go_backwards(): void
    {
        $sent = DB::table('messages')->insertGetId(['direction' => 'outbound', 'external_id' => 'gs-1', 'status' => 'sent']);
        $other = DB::table('messages')->insertGetId(['direction' => 'outbound', 'external_id' => 'gs-2', 'status' => 'sent']);

        $this->statusEvent(['id' => 'wamid-1', 'gsId' => 'gs-1', 'type' => 'delivered']);
        $this->assertSame('delivered', DB::table('messages')->where('id', $sent)->value('status'));

        $this->statusEvent(['id' => 'wamid-1', 'gsId' => 'gs-1', 'type' => 'read']);
        $this->statusEvent(['id' => 'wamid-1', 'gsId' => 'gs-1', 'type' => 'delivered']);
        $this->assertSame('read', DB::table('messages')->where('id', $sent)->value('status'));

        // Failed events carry the Gupshup id in id
        $this->statusEvent(['id' => 'gs-2', 'type' => 'failed', 'payload' => ['code' => 1002, 'reason' => 'Number Does Not Exists On WhatsApp']]);
        $this->assertDatabaseHas('messages', ['id' => $other, 'status' => 'failed', 'error_message' => 'Number Does Not Exists On WhatsApp']);
    }

    public function test_webhook_rejects_a_wrong_clinic_or_token(): void
    {
        $event = ['type' => 'message-event', 'payload' => ['id' => 'gs-1', 'type' => 'enqueued']];

        $this->postJson('/api/webhooks/gupshup/clinic_1/wrong-token', $event)->assertForbidden();
        $this->postJson('/api/webhooks/gupshup/no_such_clinic/secret-token', $event)->assertForbidden();
        $this->assertDatabaseCount('webhook_logs', 0);

        // A clinic that switched back to Meta no longer accepts Gupshup events
        DB::table('messaging_settings')->update(['meta' => json_encode(['driver' => 'meta'])]);
        $this->postJson(self::WEBHOOK, $event)->assertForbidden();

        $this->get(self::WEBHOOK)->assertOk();
    }

    private function statusEvent(array $payload): void
    {
        $this->postJson(self::WEBHOOK, [
            'app' => 'SmartClinicpluse', 'timestamp' => 1585344476683, 'version' => 2,
            'type' => 'message-event', 'payload' => $payload + ['destination' => '9647701234567'],
        ])->assertOk();
    }

    private function createSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('whatsapp');
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->string('whatsapp_webhook_verify_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone2')->nullable();
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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('whatsapp');
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->string('status')->default('received');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        DB::table('tenants')->insert(['id' => 'clinic_1', 'data' => '{}']);
        DB::table('messaging_settings')->insert([
            'provider' => 'whatsapp',
            'whatsapp_access_token' => encrypt('key-123', false),
            'whatsapp_webhook_verify_token' => 'secret-token',
            'is_active' => true,
            'meta' => json_encode([
                'driver' => 'gupshup',
                'gupshup_app_name' => 'SmartClinicpluse',
                'gupshup_app_id' => 'app-1',
                'gupshup_source_number' => '+964 770 028 1899',
            ]),
        ]);
    }
}
