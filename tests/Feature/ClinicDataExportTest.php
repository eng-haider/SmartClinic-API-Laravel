<?php

namespace Tests\Feature;

use App\Http\Controllers\ClinicDataExportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Settings → Export data: the clinic's patients, cases and bills as flat rows.
 */
class ClinicDataExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'export_test',
            'database.connections.export_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('export_test');
        $this->createSchema();
    }

    public function test_it_lists_patients_cases_and_patient_bills_with_their_names(): void
    {
        $data = (new ClinicDataExportController())->index()->getData(true)['data'];

        // Deleted records are left out
        $this->assertSame([1], array_column($data['patients'], 'id'));
        $this->assertSame('Dr. Ali', $data['patients'][0]['doctor_name']);
        $this->assertSame('انستغرام', $data['patients'][0]['source_name_ar']);

        $this->assertSame([10], array_column($data['cases'], 'id'));
        $this->assertSame('Ahmed', $data['cases'][0]['patient_name']);
        $this->assertSame('حشوة', $data['cases'][0]['category_name']);
        $this->assertSame('مكتمل', $data['cases'][0]['status_name_ar']);

        // Clinic expense instalments (no patient) are not patient bills
        $this->assertSame([100], array_column($data['bills'], 'id'));
        $this->assertSame('حشوة', $data['bills'][0]['category_name']);
        $this->assertSame(10, (int) $data['bills'][0]['case_id']);
        $this->assertSame('Sara', $data['bills'][0]['creator_name']);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('from_where_comes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
        });
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
        });
        Schema::create('case_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('phone2')->nullable();
            $table->integer('sex')->nullable();
            $table->integer('age')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address')->nullable();
            $table->text('systemic_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('credit_balance')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('from_where_come_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('case_categores_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->string('tooth_num')->nullable();
            $table->bigInteger('price')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->date('case_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->morphs('billable');
            $table->bigInteger('price');
            $table->boolean('use_credit')->default(false);
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->dateTime('bill_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('users')->insert([['id' => 1, 'name' => 'Dr. Ali'], ['id' => 2, 'name' => 'Sara']]);
        DB::table('from_where_comes')->insert(['id' => 1, 'name' => 'Instagram', 'name_ar' => 'انستغرام']);
        DB::table('statuses')->insert(['id' => 3, 'name_ar' => 'مكتمل', 'name_en' => 'Completed']);
        DB::table('case_categories')->insert(['id' => 5, 'name' => 'حشوة']);
        DB::table('patients')->insert([
            ['id' => 1, 'name' => 'Ahmed', 'phone' => '07701234567', 'doctor_id' => 1, 'from_where_come_id' => 1, 'deleted_at' => null],
            ['id' => 2, 'name' => 'Deleted', 'phone' => null, 'doctor_id' => null, 'from_where_come_id' => null, 'deleted_at' => now()],
        ]);
        DB::table('cases')->insert([
            ['id' => 10, 'patient_id' => 1, 'doctor_id' => 1, 'case_categores_id' => 5, 'status_id' => 3, 'price' => 50000, 'deleted_at' => null],
            ['id' => 11, 'patient_id' => 1, 'doctor_id' => 1, 'case_categores_id' => 5, 'status_id' => 3, 'price' => 1000, 'deleted_at' => now()],
        ]);
        DB::table('bills')->insert([
            ['id' => 100, 'patient_id' => 1, 'billable_id' => 10, 'billable_type' => 'App\\Models\\Case', 'price' => 25000, 'doctor_id' => 1, 'creator_id' => 2, 'deleted_at' => null],
            ['id' => 101, 'patient_id' => 1, 'billable_id' => 10, 'billable_type' => 'App\\Models\\Case', 'price' => 5000, 'doctor_id' => 1, 'creator_id' => 2, 'deleted_at' => now()],
            ['id' => 102, 'patient_id' => null, 'billable_id' => 1, 'billable_type' => 'App\\Models\\ClinicExpense', 'price' => 9000, 'doctor_id' => null, 'creator_id' => 2, 'deleted_at' => null],
        ]);
    }
}
