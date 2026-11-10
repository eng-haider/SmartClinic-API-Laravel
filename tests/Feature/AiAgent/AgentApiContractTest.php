<?php

namespace Tests\Feature\AiAgent;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\AiAgentAction;
use App\Models\AiAgentConversation;
use App\Models\User;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Api\ApiContext;
use App\Services\AI\Agent\Api\KernelApiClient;
use App\Services\AI\Agent\Tools\ToolExecutor;
use App\Services\AI\Agent\Tools\ToolRegistry;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * The agent's tools against the REAL routes, controllers, form requests and
 * permission middleware, through KernelApiClient (the production transport).
 * Isolated in-memory database: never touches a clinic database.
 */
class AgentApiContractTest extends TestCase
{
    private AgentSession $session;
    private ToolExecutor $tools;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'agent_contract',
            'database.connections.agent_contract' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'ai_agent.api_transport' => 'kernel',
        ]);
        DB::purge('agent_contract');
        $this->schema();
        (require base_path('database/migrations/tenant/2026_11_10_000001_create_ai_agent_tables.php'))->up();

        Carbon::setTestNow(Carbon::create(2026, 11, 10, 10, 0, 0, 'Asia/Baghdad'));
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 11, 10, 10, 0, 0, 'Asia/Baghdad'));

        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
        $this->actingAsStaff(['create-reservation', 'edit-reservation', 'view-clinic-reservations', 'create-bill', 'view-clinic-cases', 'view-all-bills']);

        $this->tools = new ToolExecutor($this->app->make(KernelApiClient::class), new ToolRegistry());
        $conversation = AiAgentConversation::create(['tenant_id' => 'clinic-a', 'user_id' => 1]);
        $this->session = new AgentSession(1, 'clinic-a', $conversation, new ApiContext(null, 'clinic-a'));
        $this->session->permissions = ['create-reservation', 'edit-reservation', 'view-clinic-reservations', 'create-bill', 'view-clinic-cases', 'view-all-bills'];
        // The test client's request is the "outer" request the kernel client restores
        $this->app->instance('request', Request::create('/api/tenant/ai/chat', 'POST'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_every_tool_maps_to_a_registered_route(): void
    {
        foreach ((new ToolRegistry())->all() as $tool) {
            $path = preg_replace('/\{\w+\}/', '1', $tool->path);
            if ($path === 'clinic-settings/1') {
                $path = 'clinic-settings/currency';
            }
            $request = Request::create('/api/tenant/' . $path, $tool->method);
            $route = Route::getRoutes()->match($request);

            $this->assertSame('api/tenant/' . preg_replace('/\{\w+\}/', '{x}', $tool->path),
                preg_replace('/\{\w+\}/', '{x}', $route->uri()), "Tool [{$tool->name}] resolves to the wrong route");
        }
    }

    public function test_available_slots_endpoint(): void
    {
        $this->setting('working_hours', json_encode(['wednesday' => '16:00 - 20:00', 'tuesday' => '09:00 - 17:00', 'friday' => 'Closed']), 'json');
        $this->setting('appointment_duration', '30', 'integer');
        $this->reservation(1, '2026-11-11', '17:00:00');

        $day = $this->tools->run($this->session, 'get_available_slots', ['date' => '2026-11-11']);
        $this->assertTrue($day->ok(), json_encode($day->body));
        $this->assertTrue($day->data()['is_open']);
        $this->assertSame(['from' => '16:00', 'to' => '20:00'], $day->data()['opening_hours']);
        $this->assertSame(30, $day->data()['duration']);
        $this->assertSame(['16:00', '16:30', '17:30', '18:00', '18:30', '19:00', '19:30'], $day->data()['free_times']);

        $friday = $this->tools->run($this->session, 'get_available_slots', ['date' => '2026-11-13']);
        $this->assertFalse($friday->data()['is_open']);

        $days = $this->tools->run($this->session, 'get_available_slots', ['days' => 2]);
        $this->assertSame('2026-11-10', $days->data()['days'][0]['date']);
        $this->assertSame('2026-11-11', $days->data()['days'][1]['date']);
    }

    public function test_available_slots_requires_a_reservation_permission(): void
    {
        $this->actingAsStaff(['view-clinic-patients']);

        $this->getJson('/api/tenant/reservations/available-slots?date=2026-11-11')->assertForbidden();
    }

    public function test_booking_rescheduling_and_cancelling_through_the_real_reservation_endpoints(): void
    {
        $action = $this->claimed('create_appointment');
        $created = $this->tools->run($this->session, 'create_appointment', [
            'patient_id' => 1, 'doctor_id' => 1, 'reservation_start_date' => '2026-11-11', 'reservation_end_date' => '2026-11-11',
            'reservation_from_time' => '17:00:00', 'reservation_to_time' => '17:30:00',
        ], $action);

        $this->assertSame(201, $created->status, json_encode($created->body));
        $id = $created->data()['id'];
        $this->assertSame('2026-11-11', $created->data()['reservation_start_date']);
        $this->assertSame(1, $created->data()['status_id']);
        $this->assertSame('أحمد علي', $created->data()['patient']['name']);

        // SQLite keeps the date cast as "2026-11-11 00:00:00" (MySQL's DATE column does not), so the range ends a day later here
        $list = $this->tools->run($this->session, 'list_appointments', ['patient_id' => 1, 'from_date' => '2026-11-11', 'to_date' => '2026-11-12', 'per_page' => 100]);
        $this->assertSame($id, $list->data()[0]['id']);
        $this->assertSame('17:00:00', $list->data()[0]['reservation_from_time']);
        $this->assertSame('محمد حسن', $list->data()[0]['doctor']['name']);

        DB::table('reservations')->where('id', $id)->update(['status_id' => 2]);
        $moved = $this->tools->run($this->session, 'reschedule_appointment', [
            'id' => $id, 'patient_id' => 1, 'status_id' => 2, 'doctor_id' => 1,
            'reservation_start_date' => '2026-11-12', 'reservation_end_date' => '2026-11-12',
            'reservation_from_time' => '18:00:00', 'reservation_to_time' => '18:30:00',
        ], $this->claimed('reschedule_appointment'));
        $this->assertTrue($moved->ok(), json_encode($moved->body));
        $row = DB::table('reservations')->find($id);
        $this->assertSame(2, (int) $row->status_id, 'Status kept');
        $this->assertSame('18:00:00', $row->reservation_from_time);

        $cancelled = $this->tools->run($this->session, 'cancel_appointment', ['id' => $id], $this->claimed('cancel_appointment'));
        $this->assertTrue($cancelled->ok(), json_encode($cancelled->body));
        $this->assertSame(4, (int) DB::table('reservations')->find($id)->status_id);
    }

    public function test_invalid_booking_gets_the_real_validation_error(): void
    {
        $response = $this->tools->run($this->session, 'create_appointment', [
            'patient_id' => 999, 'reservation_start_date' => '2026-11-11', 'reservation_from_time' => '17:00:00',
        ], $this->claimed('create_appointment'));

        $this->assertSame(422, $response->status);
        $this->assertSame('The selected patient does not exist', $response->message());
    }

    public function test_recording_a_payment_through_the_real_bills_endpoint_settles_the_case(): void
    {
        $case = DB::table('cases')->insertGetId(['patient_id' => 1, 'doctor_id' => 1, 'case_categores_id' => 1, 'status_id' => 1,
            'price' => 150000, 'is_paid' => false, 'created_at' => now(), 'updated_at' => now()]);

        $before = $this->tools->run($this->session, 'list_patient_cases', ['patient_id' => 1, 'per_page' => 100]);
        $this->assertTrue($before->ok(), json_encode($before->body));
        $this->assertSame(150000, $before->data()[0]['price']);
        $this->assertSame([], $before->data()[0]['bills']);

        foreach ([50000, 100000] as $amount) {
            $paid = $this->tools->run($this->session, 'record_payment', ['patient_id' => 1, 'billable_id' => $case, 'price' => $amount], $this->claimed('record_payment'));
            $this->assertSame(201, $paid->status, json_encode($paid->body));
        }

        $after = $this->tools->run($this->session, 'list_patient_cases', ['patient_id' => 1, 'per_page' => 100]);
        $this->assertCount(2, $after->data()[0]['bills']);
        $this->assertTrue((bool) DB::table('cases')->find($case)->is_paid, 'The case is marked paid once its bills cover the price');

        $balance = $this->tools->run($this->session, 'get_patient_balance', ['patient_id' => 1, 'per_page' => 1]);
        $this->assertSame(0, $balance->get('patient_balance.unpaid_amount'));
        $this->assertSame(150000, $balance->get('patient_balance.paid_amount'));
    }

    public function test_the_real_permission_middleware_refuses_payments(): void
    {
        $this->actingAsStaff(['view-clinic-reservations']);

        $refused = $this->tools->run($this->session, 'record_payment', ['patient_id' => 1, 'billable_id' => 1, 'price' => 1000], $this->claimed('record_payment'));

        $this->assertSame(403, $refused->status);
        $this->assertSame(0, DB::table('bills')->count());
    }

    // -------------------------------------------------------------------------

    private function claimed(string $tool): AiAgentAction
    {
        return AiAgentAction::create([
            'tenant_id' => 'clinic-a', 'user_id' => 1, 'conversation_id' => $this->session->conversation->id,
            'tool' => $tool, 'params' => [], 'summary' => [], 'idempotency_key' => (string) Str::uuid(),
            'status' => AiAgentAction::EXECUTING, 'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function actingAsStaff(array $permissions): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'محمد حسن']);
        $user->shouldReceive('hasPermissionTo')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($r) => $r === 'clinic_super_doctor');
        $this->actingAs($user);
    }

    private function setting(string $key, string $value, string $type): void
    {
        DB::table('clinic_settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'setting_type' => $type]);
    }

    private function reservation(int $patient, string $date, string $time): void
    {
        DB::table('reservations')->insert(['patient_id' => $patient, 'doctor_id' => 1, 'status_id' => 1,
            'reservation_start_date' => $date, 'reservation_end_date' => $date, 'reservation_from_time' => $time]);
    }

    private function schema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->integer('age')->nullable();
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('statuses', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('name_ar')->nullable();
            $t->string('name_en')->nullable();
            $t->string('color')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('reservation_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('patient_id');
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->unsignedBigInteger('status_id');
            $t->unsignedBigInteger('reservation_type_id')->nullable();
            $t->string('reservation_type_note')->nullable();
            $t->text('notes')->nullable();
            $t->date('reservation_start_date');
            $t->date('reservation_end_date');
            $t->time('reservation_from_time');
            $t->time('reservation_to_time')->nullable();
            $t->boolean('is_waiting')->default(false);
            $t->timestamp('reminder_sent_at')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->unsignedBigInteger('updator_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->string('noteable_type');
            $t->unsignedBigInteger('noteable_id');
            $t->text('content')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('booking_requests', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->date('preferred_date');
            $t->time('preferred_time')->nullable();
            $t->string('status')->default('pending');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('clinic_settings', function (Blueprint $t) {
            $t->id();
            $t->string('setting_key');
            $t->text('setting_value')->nullable();
            $t->string('setting_type')->default('string');
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('case_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('order')->nullable();
            $t->integer('item_cost')->default(0);
            $t->boolean('is_orthodontic')->default(false);
            $t->unsignedBigInteger('department_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('cases', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('patient_id');
            $t->unsignedBigInteger('doctor_id');
            $t->unsignedBigInteger('case_categores_id')->nullable();
            $t->unsignedBigInteger('status_id')->nullable();
            $t->bigInteger('price')->nullable();
            $t->integer('item_cost')->nullable();
            $t->boolean('is_paid')->default(false);
            $t->text('notes')->nullable();
            $t->string('tooth_num')->nullable();
            $t->string('root_stuffing')->nullable();
            $t->timestamp('case_date')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('bills', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('patient_id')->nullable();
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->unsignedBigInteger('updator_id')->nullable();
            $t->unsignedBigInteger('billable_id')->nullable();
            $t->string('billable_type')->nullable();
            $t->bigInteger('price');
            $t->boolean('is_paid')->default(true);
            $t->boolean('use_credit')->default(false);
            $t->timestamp('bill_date')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'محمد حسن']);
        DB::table('patients')->insert(['id' => 1, 'name' => 'أحمد علي', 'phone' => '07701111111']);
        DB::table('statuses')->insert([
            ['id' => 1, 'name' => 'New', 'name_en' => 'New', 'name_ar' => 'جديد'],
            ['id' => 2, 'name' => 'In Progress', 'name_en' => 'In Progress', 'name_ar' => 'قيد التقدم'],
            ['id' => 3, 'name' => 'Completed', 'name_en' => 'Completed', 'name_ar' => 'مكتمل'],
            ['id' => 4, 'name' => 'Cancelled', 'name_en' => 'Cancelled', 'name_ar' => 'ملغي'],
        ]);
        DB::table('case_categories')->insert(['id' => 1, 'name' => 'تقويم']);
    }
}
