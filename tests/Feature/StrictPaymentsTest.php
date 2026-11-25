<?php

namespace Tests\Feature;

use App\Http\Requests\BillRequest;
use App\Http\Requests\CaseRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StrictPaymentsTest extends TestCase
{
    private const ADMIN = 1;
    private const DOCTOR = 2;
    private const OTHER_DOCTOR = 3;

    // Cases of DOCTOR: priced 400 with 250 paid, without a price, and overpaid (price 100, paid 150)
    private const PRICED = 1;
    private const FREE = 2;
    private const OVERPAID = 3;

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'strict_payments_test',
            'database.connections.strict_payments_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('strict_payments_test');
        $this->createSchema();

        // Exercise the requests alone, without the controllers' permission middleware.
        Route::post('/_test/bills', fn (BillRequest $request) => $request->validated());
        Route::put('/_test/bills/{bill}', fn (BillRequest $request) => $request->validated());
        Route::put('/_test/cases/{case}', fn (CaseRequest $request) => $request->validated());

        $this->actingAs(User::find(self::ADMIN));
        $this->withHeader('Accept-Language', 'ar');
    }

    public function test_everything_is_accepted_while_the_setting_is_off(): void
    {
        $this->pay(self::FREE, 50)->assertOk();
        $this->pay(self::PRICED, 9999)->assertOk();
        $this->putJson('/_test/bills/11', ['price' => 9999])->assertOk();
        $this->putJson('/_test/cases/1', ['patient_id' => 1, 'price' => 0])->assertOk();
    }

    public function test_a_payment_needs_a_case_price_with_room_for_it(): void
    {
        $this->switchOn('strict_payments');

        $this->pay(self::FREE, 50)->assertUnprocessable()->assertJsonPath('message', 'هذه الحالة بدون سعر. يُكتب سعرها أولاً، أو تُسجّل الدفعة على حالة العلاج نفسها.');
        $this->pay(self::PRICED, 151)->assertUnprocessable()->assertJsonPath('message', 'الدفعة (151) أكثر من المتبقي على هذه الحالة (150).');
        $this->pay(self::OVERPAID, 1)->assertUnprocessable();
        $this->pay(self::PRICED, 150)->assertOk();
        $this->pay(self::FREE, 0)->assertOk();

        // Payments migrated from the old system count too, and other screens' type names
        $this->postJson('/_test/bills', ['patient_id' => 1, 'billable_id' => self::PRICED, 'billable_type' => 'Case', 'price' => 151])->assertUnprocessable();
        // Expense instalments are not case payments
        $this->postJson('/_test/bills', ['billable_id' => self::FREE, 'billable_type' => 'App\\Models\\ClinicExpense', 'price' => 50])->assertOk();

        $this->withHeader('Accept-Language', 'en');
        $this->pay(self::PRICED, 1200)->assertUnprocessable()->assertJsonPath('message', 'The payment (1,200) is more than what remains on this case (150).');
    }

    public function test_a_payment_can_be_lowered_but_not_raised_over_the_price(): void
    {
        $this->switchOn('strict_payments');

        // Bill 11: 50 of the 250 paid on the case priced 400
        $this->putJson('/_test/bills/11', ['price' => 200])->assertOk();
        $this->putJson('/_test/bills/11', ['price' => 201])->assertUnprocessable();
        $this->putJson('/_test/bills/11', ['bill_date' => '2026-11-01'])->assertOk();

        // Bill 12 is the 150 on the overpaid case: it can stay or go down
        $this->putJson('/_test/bills/12', ['price' => 150])->assertOk();
        $this->putJson('/_test/bills/12', ['price' => 120])->assertOk();
        $this->putJson('/_test/bills/12', ['price' => 160])->assertUnprocessable();

        // Moving a payment to a case without room for it
        $this->putJson('/_test/bills/12', ['billable_id' => self::FREE])->assertUnprocessable();
        $this->putJson('/_test/bills/12', ['billable_id' => self::PRICED])->assertOk();
        $this->putJson('/_test/bills/999', ['price' => 5])->assertOk(); // the controller answers 404
    }

    public function test_a_price_cannot_go_below_what_was_paid(): void
    {
        $this->switchOn('strict_payments');
        $price = fn (int $case, ?int $price) => $this->putJson('/_test/cases/'.$case, ['patient_id' => 1, 'price' => $price]);

        $price(self::PRICED, 249)->assertUnprocessable()->assertJsonPath('message', 'سعر الحالة (249) لا يمكن أن يكون أقل من المدفوع عليها (250). تُعدّل الدفعات أولاً.');
        $price(self::PRICED, null)->assertUnprocessable();
        $price(self::PRICED, 250)->assertOk();
        $price(self::PRICED, 900)->assertOk();

        // Editing an overpaid case sends its price along: unchanged or raised is fine
        $price(self::OVERPAID, 100)->assertOk();
        $price(self::OVERPAID, 120)->assertOk();
        $price(self::OVERPAID, 90)->assertUnprocessable();
        $price(self::FREE, 0)->assertOk();
        $this->putJson('/_test/cases/1', ['patient_id' => 1, 'notes' => 'x'])->assertOk();
    }

    public function test_a_doctor_who_is_not_shown_the_price_cannot_wipe_it(): void
    {
        $edit = ['patient_id' => 1, 'notes' => 'x', 'price' => 0, 'is_paid' => 0];

        // Without isolation the price is shown, so it can be changed
        $this->actingAs(User::find(self::OTHER_DOCTOR))->putJson('/_test/cases/1', $edit)->assertOk()->assertJsonPath('price', 0);

        $this->switchOn('doctor_bills_isolation');
        $this->actingAs(User::find(self::OTHER_DOCTOR))->putJson('/_test/cases/1', $edit)->assertOk()
            ->assertJsonPath('notes', 'x')->assertJsonMissingPath('price')->assertJsonMissingPath('is_paid');
        $this->actingAs(User::find(self::DOCTOR))->putJson('/_test/cases/1', $edit)->assertOk()->assertJsonPath('price', 0);
        $this->actingAs(User::find(self::ADMIN))->putJson('/_test/cases/1', $edit)->assertOk()->assertJsonPath('price', 0);
    }

    private function pay(int $caseId, int $amount)
    {
        return $this->postJson('/_test/bills', ['patient_id' => 1, 'billable_id' => $caseId, 'billable_type' => 'App\\Models\\Case', 'price' => $amount]);
    }

    private function switchOn(string $key): void
    {
        DB::table('clinic_settings')->insert(['setting_key' => $key, 'setting_value' => '1', 'setting_type' => 'boolean', 'is_active' => true]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('case_categories', fn (Blueprint $table) => $table->id());
        Schema::create('statuses', fn (Blueprint $table) => $table->id());
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('case_categores_id')->nullable();
            $table->bigInteger('price')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billable_id');
            $table->string('billable_type');
            $table->bigInteger('price');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key');
            $table->string('setting_value')->nullable();
            $table->string('setting_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('users')->insert([['id' => self::ADMIN, 'name' => 'Admin'], ['id' => self::DOCTOR, 'name' => 'Doctor'], ['id' => self::OTHER_DOCTOR, 'name' => 'Other']]);
        $guard = config('auth.defaults.guard');
        DB::table('roles')->insert([['id' => 1, 'name' => 'clinic_super_doctor', 'guard_name' => $guard], ['id' => 2, 'name' => 'doctor', 'guard_name' => $guard]]);
        $type = (new User)->getMorphClass();
        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => $type, 'model_id' => self::ADMIN],
            ['role_id' => 2, 'model_type' => $type, 'model_id' => self::DOCTOR],
            ['role_id' => 2, 'model_type' => $type, 'model_id' => self::OTHER_DOCTOR],
        ]);
        DB::table('patients')->insert(['id' => 1, 'name' => 'Patient']);
        DB::table('statuses')->insert(['id' => 2]);
        DB::table('cases')->insert([
            ['id' => self::PRICED, 'patient_id' => 1, 'doctor_id' => self::DOCTOR, 'price' => 400],
            ['id' => self::FREE, 'patient_id' => 1, 'doctor_id' => self::DOCTOR, 'price' => null],
            ['id' => self::OVERPAID, 'patient_id' => 1, 'doctor_id' => self::DOCTOR, 'price' => 100],
        ]);
        DB::table('bills')->insert([
            ['id' => 10, 'billable_id' => self::PRICED, 'billable_type' => 'App\\Models\\CaseModel', 'price' => 200, 'deleted_at' => null],
            ['id' => 11, 'billable_id' => self::PRICED, 'billable_type' => 'App\\Models\\Case', 'price' => 50, 'deleted_at' => null],
            ['id' => 12, 'billable_id' => self::OVERPAID, 'billable_type' => 'App\\Models\\Case', 'price' => 150, 'deleted_at' => null],
            ['id' => 13, 'billable_id' => self::PRICED, 'billable_type' => 'App\\Models\\Case', 'price' => 300, 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
    }
}
