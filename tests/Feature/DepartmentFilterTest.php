<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use App\Repositories\BillRepository;
use App\Repositories\CaseRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DepartmentFilterTest extends TestCase
{
    private const ORTHO = 1;
    private const COSMETIC = 2;

    private array $cases = [];
    private array $bills = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'departments_test',
            'database.connections.departments_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('departments_test');
        $this->createSchema();
        $this->seedClinic();
        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Admin']);
        $user->shouldReceive('hasPermissionTo')->andReturn(true);
        $user->shouldReceive('hasRole')->andReturn(true);
        $this->actingAs($user);
    }

    public function test_bills_list_only_returns_case_bills_of_the_department(): void
    {
        $this->getJson('/api/bills?filter[department_id]='.self::ORTHO)->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $this->bills['braces']);

        $this->getJson('/api/bills?filter[billable_type]=App\\Models\\Case')->assertOk()
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_statistics_are_scoped_to_the_department(): void
    {
        $stats = app(BillRepository::class)->getStatisticsWithFilters(['department_id' => self::COSMETIC], 15, null);
        $this->assertSame(500, (int) $stats['total_price']);
        $this->assertSame(200, (int) $stats['total_paid_price']);
        $this->assertSame(300, $stats['total_unpaid_price']);

        $this->getJson('/api/bills/statistics/summary?department_id='.self::ORTHO)->assertOk()
            ->assertJsonPath('data.total_price', 300)
            ->assertJsonPath('data.total_paid_price', 100);
    }

    public function test_patient_balances_and_payments_are_scoped_to_the_department(): void
    {
        $this->getJson('/api/bills/patient-balances?department_id='.self::ORTHO)->assertOk()
            ->assertJsonPath('summary.total_price', 300)
            ->assertJsonPath('summary.paid_amount', 100)
            ->assertJsonPath('summary.unpaid_amount', 200);

        $this->getJson('/api/bills/payments?department_id='.self::COSMETIC)->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $this->bills['veneers']);

        $this->getJson('/api/bills/patient-balances?department_id=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_cases_filter_by_department_through_their_category(): void
    {
        $this->app->instance('request', Request::create('/api/cases', 'GET', [
            'filter' => ['department_id' => self::COSMETIC],
        ]));

        $cases = app(CaseRepository::class)->getAllWithFilters([]);

        $this->assertSame([$this->cases['veneers']], $cases->pluck('id')->all());
    }

    public function test_category_can_be_assigned_to_a_department_but_not_a_deleted_one(): void
    {
        $payload = ['name' => 'Filling', 'category_type' => 'dental'];

        $this->putJson('/api/case-categories/3', $payload + ['department_id' => self::COSMETIC])->assertOk()
            ->assertJsonPath('data.department_id', self::COSMETIC);

        DB::table('departments')->where('id', self::ORTHO)->update(['deleted_at' => now()]);
        $this->putJson('/api/case-categories/3', $payload + ['department_id' => self::ORTHO])
            ->assertUnprocessable()->assertJsonValidationErrors(['department_id']);
    }

    public function test_deleting_a_department_keeps_its_categories_without_a_department(): void
    {
        $this->postJson('/api/departments', ['name' => 'Surgery'])->assertCreated()
            ->assertJsonPath('data.name', 'Surgery')
            ->assertJsonPath('data.is_active', true);

        $this->getJson('/api/departments')->assertOk()
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('data.0.case_categories_count', 1);

        $this->deleteJson('/api/departments/'.self::ORTHO)->assertOk();

        $this->assertNull(DB::table('case_categories')->where('id', 1)->value('department_id'));
        $this->assertNotNull(DB::table('departments')->where('id', self::ORTHO)->value('deleted_at'));
        $this->getJson('/api/departments')->assertOk()->assertJsonPath('pagination.total', 2);
    }

    private function seedClinic(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Doctor One']);
        DB::table('statuses')->insert(['id' => 1, 'name' => 'Open']);
        DB::table('patients')->insert(['id' => 1, 'name' => 'Patient', 'phone' => '0770001']);
        DB::table('departments')->insert([
            ['id' => self::ORTHO, 'name' => 'Orthodontics'],
            ['id' => self::COSMETIC, 'name' => 'Cosmetic'],
        ]);
        DB::table('case_categories')->insert([
            ['id' => 1, 'name' => 'Braces', 'category_type' => 'dental', 'department_id' => self::ORTHO],
            ['id' => 2, 'name' => 'Veneers', 'category_type' => 'dental', 'department_id' => self::COSMETIC],
            ['id' => 3, 'name' => 'Filling', 'category_type' => 'dental', 'department_id' => null],
        ]);

        foreach (['braces' => [1, 300, 100], 'veneers' => [2, 500, 200], 'filling' => [3, 100, 50]] as $key => [$category, $price, $paid]) {
            $this->cases[$key] = DB::table('cases')->insertGetId([
                'patient_id' => 1, 'doctor_id' => 1, 'case_categores_id' => $category,
                'status_id' => 1, 'price' => $price, 'created_at' => '2026-10-01 10:00:00',
            ]);
            $this->bills[$key] = DB::table('bills')->insertGetId([
                'patient_id' => 1, 'doctor_id' => 1, 'billable_id' => $this->cases[$key],
                'billable_type' => 'App\\Models\\Case', 'price' => $paid, 'is_paid' => true,
                'created_at' => '2026-10-02 10:00:00',
            ]);
        }

        // A non-case bill must never show up under a department.
        DB::table('bills')->insert([
            'patient_id' => 1, 'doctor_id' => 1, 'billable_id' => $this->cases['braces'],
            'billable_type' => 'Reservation', 'price' => 999, 'is_paid' => true,
            'created_at' => '2026-10-03 10:00:00',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('case_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category_type')->nullable();
            $table->boolean('is_orthodontic')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->integer('order')->nullable();
            $table->integer('item_cost')->default(0);
            $table->boolean('without_detect_tooth')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('case_categores_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->bigInteger('price')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->text('notes')->nullable();
            $table->string('tooth_num')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->unsignedBigInteger('billable_id');
            $table->string('billable_type');
            $table->bigInteger('price');
            $table->boolean('is_paid')->default(true);
            $table->boolean('use_credit')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('clinic_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->date('date')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
