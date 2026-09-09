<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BillingOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'billing_test',
            'database.connections.billing_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('billing_test');
        $this->createSchema();
        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
        $this->authorizeBilling(true);
    }

    public function test_period_matches_payment_dates_but_preserves_lifetime_case_balances(): void
    {
        $patient = $this->patient('Old patient', '0770011');
        DB::table('patients')->where('id', $patient)->update(['created_at' => '2020-01-01 00:00:00']);
        $case = $this->caseFor($patient, 300, 1);
        $this->caseFor($patient, 100, 1);
        $this->billFor($case, 50, true, '2026-01-01 10:00:00');
        $payment = $this->billFor($case, 50, true, '2026-09-09 23:59:59');
        $this->billFor($case, 40, true, '2026-10-01 00:00:00');
        $otherDoctor = $this->caseFor($patient, 100, 2);
        $this->billFor($otherDoctor, 100, true, '2026-09-09 11:00:00');
        $noPeriodPayment = $this->patient('Only old payments', '0770022');
        $otherCase = $this->caseFor($noPeriodPayment, 200, 1);
        $this->billFor($otherCase, 20, true, '2026-01-01 00:00:00');
        $this->billFor($otherCase, 180, false, '2026-09-09 12:00:00');
        $unbilled = $this->patient('Never paid', '0770033');
        $this->caseFor($unbilled, 100, 1);

        $filters = '?doctor_id=1&date_from=2026-09-09&date_to=2026-09-09';
        $this->getJson('/api/bills/patient-balances'.$filters)->assertOk()
            ->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $patient)
            ->assertJsonPath('data.0.case_count', 2)->assertJsonPath('data.0.total_price', 400)
            ->assertJsonPath('data.0.paid_amount', 140)->assertJsonPath('data.0.period_paid_amount', 50)
            ->assertJsonPath('data.0.unpaid_amount', 260)->assertJsonPath('data.0.last_payment_at', '2026-09-09 23:59:59')
            ->assertJsonPath('summary.period_paid_amount', 50)->assertJsonPath('summary.unpaid_amount', 260);
        $this->getJson('/api/bills/payments'.$filters.'&patient_id='.$patient)->assertOk()
            ->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $payment);
        $this->getJson('/api/bills/patient-balances?doctor_id=1')->assertOk()
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_payment_export_obeys_patient_status_and_period_filters(): void
    {
        $paid = $this->patient('Paid', '0770001');
        $case = $this->caseFor($paid, 100);
        $this->billFor($case, 40, true, '2026-08-01 10:00:00');
        $paidBill = $this->billFor($case, 60, true, '2026-09-09 10:00:00');
        $partial = $this->patient('Partial', '0770002');
        $partialCase = $this->caseFor($partial, 200);
        $partialBill = $this->billFor($partialCase, 80, true, '2026-09-09 12:00:00');
        foreach (['paid' => [$paid, $paidBill, 60], 'unpaid' => [$partial, $partialBill, 80]] as $status => [$patientId, $billId, $amount]) {
            $filter = '?date_from=2026-09-01&date_to=2026-09-30&payment_status='.$status;
            $this->getJson('/api/bills/patient-balances'.$filter)->assertOk()
                ->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $patientId)
                ->assertJsonPath('summary.period_paid_amount', $amount);
            $this->getJson('/api/bills/payments'.$filter)->assertOk()
                ->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $billId)
                ->assertJsonPath('summary.paid_amount', $amount);
        }
    }

    public function test_period_validation_open_boundaries_and_amount_sorting(): void
    {
        $patient = $this->patient('Patient', '0770001');
        $case = $this->caseFor($patient, 100);
        $this->billFor($case, 20, true, '2026-09-01 00:00:00');
        $this->billFor($case, 30, true, '2026-09-09 23:59:59');
        $this->getJson('/api/bills/patient-balances?date_from=2026-09-10&date_to=2026-09-01')->assertUnprocessable();
        $this->getJson('/api/bills/patient-balances?date_from=invalid')->assertUnprocessable();
        $this->getJson('/api/bills/patient-balances?date_to=2026-09-01&sort=-period_paid_amount')->assertOk()
            ->assertJsonPath('data.0.period_paid_amount', 20)->assertJsonPath('data.0.unpaid_amount', 50);
        $this->getJson('/api/bills/patient-balances?date_from=2026-09-09')->assertOk()
            ->assertJsonPath('data.0.period_paid_amount', 30)->assertJsonPath('data.0.unpaid_amount', 50);
    }

    public function test_balances_count_cases_once_and_keep_unbilled_cases_and_per_case_debt(): void
    {
        $partial = $this->patient('Partial', '0770111');
        $firstCase = $this->caseFor($partial, 100);
        $this->caseFor($partial, 200);
        $this->billFor($firstCase, 40, true, '2026-09-01 08:00:00');
        $this->billFor($firstCase, 10, true, '2026-09-02 08:00:00', ['billable_type' => 'CaseModel']);
        $this->billFor($firstCase, 999, false, '2026-09-09 08:00:00');

        $overpaid = $this->patient('Overpaid one case', '0770222');
        $overpaidCase = $this->caseFor($overpaid, 100);
        $this->caseFor($overpaid, 40);
        $this->billFor($overpaidCase, 150, true, '2026-09-03 10:00:00');

        $unbilled = $this->patient('No payments', '0770333');
        $this->caseFor($unbilled, 80);
        $this->patient('No cases', '0770444');

        $response = $this->getJson('/api/bills/patient-balances')->assertOk()
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('summary', [
                'total_price' => 520, 'paid_amount' => 200, 'unpaid_amount' => 370, 'patient_count' => 3,
            ])
            ->assertJsonPath('data.0.id', $overpaid)
            ->assertJsonPath('data.0.unpaid_amount', 40)
            ->assertJsonPath('data.0.payment_status', 'unpaid')
            ->assertJsonPath('data.1.id', $partial)
            ->assertJsonPath('data.1.case_count', 2)
            ->assertJsonPath('data.1.total_price', 300)
            ->assertJsonPath('data.1.paid_amount', 50)
            ->assertJsonPath('data.1.unpaid_amount', 250)
            ->assertJsonPath('data.1.last_payment_at', '2026-09-02 08:00:00')
            ->assertJsonPath('data.2.id', $unbilled)
            ->assertJsonPath('data.2.last_payment_at', null);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_balances_filter_name_phone_status_and_doctor_before_summary_and_pagination(): void
    {
        $paid = $this->patient('سارة أحمد', '07701234567');
        $paidCase = $this->caseFor($paid, 100, 1);
        $this->caseFor($paid, 300, 2);
        $this->billFor($paidCase, 100, true, '2026-09-01 10:00:00');
        $unpaid = $this->patient('ليلى', '07807654321');
        $this->caseFor($unpaid, 50, 1);

        $this->getJson('/api/bills/patient-balances?doctor_id=1&payment_status=paid&search='.urlencode('سارة'))
            ->assertOk()->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $paid)
            ->assertJsonPath('data.0.payment_status', 'paid')
            ->assertJsonPath('summary.total_price', 100)
            ->assertJsonPath('summary.unpaid_amount', 0);
        $this->getJson('/api/bills/patient-balances?payment_status=unpaid&search=7654321')
            ->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $unpaid);

        $this->getJson('/api/bills/patient-balances?sort=-total_price&per_page=1&page=2')
            ->assertOk()->assertJsonPath('pagination.total', 2)->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.current_page', 2)->assertJsonPath('data.0.id', $unpaid)
            ->assertJsonPath('summary.total_price', 450);
    }

    public function test_oldest_payment_sort_also_keeps_patients_without_payments_last(): void
    {
        $unbilled = $this->patient('Unbilled', '0770000');
        $this->caseFor($unbilled, 50);
        $older = $this->patient('Older', '0770001');
        $olderCase = $this->caseFor($older, 100);
        $this->billFor($olderCase, 100, true, '2026-09-01 10:00:00');
        $newer = $this->patient('Newer', '0770002');
        $newerCase = $this->caseFor($newer, 100);
        $this->billFor($newerCase, 100, true, '2026-09-02 10:00:00');

        $this->getJson('/api/tenant/bills/patient-balances?sort=last_payment_at')->assertOk()
            ->assertJsonPath('data.0.id', $older)
            ->assertJsonPath('data.1.id', $newer)
            ->assertJsonPath('data.2.id', $unbilled);
    }

    public function test_deleted_bills_cases_and_patients_and_non_case_bills_are_excluded(): void
    {
        $patient = $this->patient('Active', '0770001');
        $case = $this->caseFor($patient, 100);
        $this->billFor($case, 30, true, '2026-09-01 10:00:00');
        $this->billFor($case, 70, true, '2026-09-02 10:00:00', ['deleted_at' => '2026-09-03 10:00:00']);
        $this->billFor($case, 500, true, '2026-09-04 10:00:00', ['billable_type' => 'Reservation']);
        $deletedCase = $this->caseFor($patient, 200);
        $this->billFor($deletedCase, 200, true, '2026-09-01 10:00:00');
        DB::table('cases')->where('id', $deletedCase)->update(['deleted_at' => '2026-09-03 10:00:00']);
        $deletedPatient = $this->patient('Deleted', '0770002');
        $otherCase = $this->caseFor($deletedPatient, 300);
        $this->billFor($otherCase, 300, true, '2026-09-01 10:00:00');
        DB::table('patients')->where('id', $deletedPatient)->update(['deleted_at' => '2026-09-03 10:00:00']);

        $this->getJson('/api/bills/patient-balances')->assertOk()
            ->assertJsonPath('summary', ['total_price' => 100, 'paid_amount' => 30, 'unpaid_amount' => 70, 'patient_count' => 1]);
        $this->getJson('/api/bills/payments')->assertOk()
            ->assertJsonPath('pagination.total', 1)->assertJsonPath('summary.paid_amount', 30);
    }

    public function test_payments_include_end_date_and_filter_case_doctor_with_complete_resource(): void
    {
        $patient = $this->patient('Payment patient', '07705555555');
        $case = $this->caseFor($patient, 100, 1);
        $this->billFor($case, 10, true, '2026-09-08 23:59:59');
        $start = $this->billFor($case, 20, true, '2026-09-09 00:00:00', ['billable_type' => 'Case']);
        $end = $this->billFor($case, 30, true, '2026-09-09 23:59:59', ['doctor_id' => 2, 'billable_type' => 'App\\Models\\CaseModel']);
        $this->billFor($case, 40, true, '2026-09-10 00:00:00');
        $this->billFor($case, 50, false, '2026-09-09 12:00:00');
        $otherCase = $this->caseFor($patient, 200, 2);
        $this->billFor($otherCase, 200, true, '2026-09-09 11:00:00');

        $this->getJson('/api/bills/payments?date_from=2026-09-09&date_to=2026-09-09&doctor_id=1&search=5555555&per_page=1')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('summary', ['paid_amount' => 50, 'payment_count' => 2])
            ->assertJsonPath('data.0.id', $end)
            ->assertJsonPath('data.0.patient.name', 'Payment patient')
            ->assertJsonPath('data.0.patient.phone', '07705555555')
            ->assertJsonPath('data.0.doctor.name', 'Doctor Two')
            ->assertJsonPath('data.0.billable.category.name', 'Treatment')
            ->assertJsonPath('data.0.is_paid', true);
        $this->getJson('/api/bills/payments?date_from=2026-09-09&date_to=2026-09-09&doctor_id=1&sort=price')
            ->assertOk()->assertJsonPath('data.0.id', $start)->assertJsonPath('data.1.id', $end);
    }

    public function test_patient_history_includes_paid_and_unpaid_and_uses_case_owner_for_legacy_bills(): void
    {
        $patient = $this->patient('Patient', '0770111');
        $case = $this->caseFor($patient, 100);
        $this->billFor($case, 40, true, '2026-09-01 10:00:00', ['patient_id' => null]);
        $this->billFor($case, 60, false, '2026-09-02 10:00:00');
        $otherPatient = $this->patient('Other', '0770222');
        $otherCase = $this->caseFor($otherPatient, 50);
        $this->billFor($otherCase, 50, true, '2026-09-03 10:00:00');

        $this->getJson('/api/bills/patient-balances/'.$patient.'/bills?per_page=1&page=2')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.patient.id', $patient)->assertJsonPath('data.0.patient.name', 'Patient')
            ->assertJsonPath('data.0.is_paid', true);
        $this->getJson('/api/bills/patient-balances/'.$patient.'/bills')
            ->assertOk()->assertJsonPath('data.0.is_paid', false);
        $this->getJson('/api/bills/payments?patient_id='.$patient)
            ->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('summary.paid_amount', 40);
        $this->getJson('/api/bills/patient-balances/999/bills')->assertNotFound();
    }

    public function test_deleted_category_does_not_break_payment_history(): void
    {
        $patient = $this->patient('Patient', '0770111');
        $case = $this->caseFor($patient, 100);
        $this->billFor($case, 100, true, '2026-09-01 10:00:00');
        DB::table('case_categories')->where('id', 1)->update(['deleted_at' => '2026-09-02 10:00:00']);

        $this->getJson('/api/bills/payments')->assertOk()
            ->assertJsonPath('data.0.billable.category.id', null)
            ->assertJsonPath('data.0.billable.category.created_at', null);
    }

    public function test_history_refreshes_lifetime_patient_balance_after_payment_deletion_with_doctor_scope(): void
    {
        $patient = $this->patient('Patient', '0770111');
        $case = $this->caseFor($patient, 100, 1);
        $this->caseFor($patient, 50, 1);
        $payment = $this->billFor($case, 80, true, '2026-09-01 10:00:00');
        $otherDoctorCase = $this->caseFor($patient, 300, 2);
        $this->billFor($otherDoctorCase, 300, true, '2026-09-02 10:00:00');
        $otherPatient = $this->patient('Other', '0770222');
        $this->caseFor($otherPatient, 900, 1);
        $url = '/api/bills/patient-balances/'.$patient.'/bills?doctor_id=1&date_from=2026-09-09&search=NoMatch';

        $this->getJson($url)->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('patient_balance', [
                'id' => $patient, 'name' => 'Patient', 'phone' => '0770111', 'case_count' => 2,
                'total_price' => 150, 'paid_amount' => 80, 'unpaid_amount' => 70,
                'last_payment_at' => '2026-09-01 10:00:00', 'payment_status' => 'unpaid',
            ]);

        DB::table('bills')->where('id', $payment)->update(['deleted_at' => '2026-09-09 10:00:00']);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('patient_balance.case_count', 2)
            ->assertJsonPath('patient_balance.total_price', 150)
            ->assertJsonPath('patient_balance.paid_amount', 0)
            ->assertJsonPath('patient_balance.unpaid_amount', 150)
            ->assertJsonPath('patient_balance.last_payment_at', null);
        $this->getJson('/api/bills/patient-balances/'.$patient.'/bills?doctor_id=2')->assertOk()
            ->assertJsonPath('patient_balance.case_count', 1)
            ->assertJsonPath('patient_balance.total_price', 300)
            ->assertJsonPath('patient_balance.paid_amount', 300)
            ->assertJsonPath('patient_balance.unpaid_amount', 0)
            ->assertJsonPath('patient_balance.payment_status', 'paid');
    }

    public function test_history_returns_zero_balance_when_patient_has_no_cases_in_doctor_scope(): void
    {
        $patient = $this->patient('Patient', '0770111');
        $this->caseFor($patient, 100, 1);

        $this->getJson('/api/tenant/bills/patient-balances/'.$patient.'/bills?doctor_id=2')->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('patient_balance', [
                'id' => $patient, 'name' => 'Patient', 'phone' => '0770111', 'case_count' => 0,
                'total_price' => 0, 'paid_amount' => 0, 'unpaid_amount' => 0,
                'last_payment_at' => null, 'payment_status' => 'paid',
            ]);
    }

    public function test_empty_results_have_zero_summaries(): void
    {
        $this->getJson('/api/bills/patient-balances')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('summary', ['total_price' => 0, 'paid_amount' => 0, 'unpaid_amount' => 0, 'patient_count' => 0]);
        $this->getJson('/api/bills/payments')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('summary', ['paid_amount' => 0, 'payment_count' => 0]);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/bills/patient-balances?sort=unknown&payment_status=partial&per_page=501&page=0&doctor_id=0')
            ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'payment_status', 'per_page', 'page', 'doctor_id']);
        $this->getJson('/api/bills/payments?date_from=2026-09-10&date_to=2026-09-09&sort=-unpaid_amount')
            ->assertUnprocessable()->assertJsonValidationErrors(['date_to', 'sort']);
        $this->getJson('/api/bills/payments?date_from=2026-02-30&patient_id=0')
            ->assertUnprocessable()->assertJsonValidationErrors(['date_from', 'patient_id']);
    }

    public function test_every_overview_endpoint_requires_billing_permission(): void
    {
        $this->authorizeBilling(false);
        foreach (['patient-balances', 'payments', 'patient-balances/1/bills'] as $endpoint) {
            $this->getJson('/api/bills/'.$endpoint)->assertForbidden();
            $this->getJson('/api/tenant/bills/'.$endpoint)->assertForbidden();
        }
    }

    public function test_new_routes_keep_jwt_and_tenant_header_middleware(): void
    {
        $this->withMiddleware(JwtMiddleware::class);
        foreach (['patient-balances', 'payments', 'patient-balances/1/bills'] as $endpoint) {
            $this->getJson('/api/bills/'.$endpoint)->assertUnauthorized();
        }
        $this->withMiddleware(InitializeTenancyByHeader::class);
        $this->getJson('/api/tenant/bills/patient-balances')->assertBadRequest()
            ->assertJsonPath('success', false);
    }

    private function authorizeBilling(bool $allowed): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Doctor One']);
        $user->shouldReceive('hasPermissionTo')->with('view-all-bills')->andReturn($allowed);
        $this->actingAs($user);
    }

    private function patient(string $name, string $phone): int
    {
        return DB::table('patients')->insertGetId(compact('name', 'phone'));
    }

    private function caseFor(int $patientId, int $price, int $doctorId = 1): int
    {
        return DB::table('cases')->insertGetId([
            'patient_id' => $patientId, 'price' => $price, 'doctor_id' => $doctorId,
            'case_categores_id' => 1, 'status_id' => 1,
        ]);
    }

    private function billFor(int $caseId, int $price, bool $paid, string $createdAt, array $overrides = []): int
    {
        $case = DB::table('cases')->find($caseId);

        return DB::table('bills')->insertGetId(array_merge([
            'patient_id' => $case->patient_id, 'doctor_id' => $case->doctor_id,
            'billable_id' => $caseId, 'billable_type' => 'App\\Models\\Case',
            'price' => $price, 'is_paid' => $paid, 'created_at' => $createdAt,
        ], $overrides));
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
        Schema::create('case_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('order')->nullable();
            $table->integer('item_cost')->default(0);
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
        DB::table('users')->insert([['id' => 1, 'name' => 'Doctor One'], ['id' => 2, 'name' => 'Doctor Two']]);
        DB::table('case_categories')->insert(['id' => 1, 'name' => 'Treatment']);
        DB::table('statuses')->insert(['id' => 1, 'name' => 'Open']);
    }
}
