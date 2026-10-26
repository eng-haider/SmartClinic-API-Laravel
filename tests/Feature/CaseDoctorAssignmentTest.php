<?php

namespace Tests\Feature;

use App\Http\Requests\CaseRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaseDoctorAssignmentTest extends TestCase
{
    private const ADMIN = 1;
    private const DOCTOR = 2;
    private const OTHER_DOCTOR = 3;
    private const SECRETARY = 4;

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'case_doctor_test',
            'database.connections.case_doctor_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('case_doctor_test');
        $this->createSchema();

        // Exercise CaseRequest alone, without the controller's permission middleware.
        Route::post('/_test/cases', fn (CaseRequest $request) => $request->validated());
        Route::put('/_test/cases/{case}', fn (CaseRequest $request) => $request->validated());
    }

    public function test_clinic_admin_can_reassign_a_case_to_another_doctor(): void
    {
        $this->actingAs(User::find(self::ADMIN))
            ->putJson('/_test/cases/1', ['patient_id' => 1, 'doctor_id' => self::OTHER_DOCTOR])
            ->assertOk()->assertJsonPath('doctor_id', self::OTHER_DOCTOR);
    }

    public function test_clinic_admin_can_create_a_case_for_another_doctor(): void
    {
        $this->actingAs(User::find(self::ADMIN))
            ->postJson('/_test/cases', ['patient_id' => 1, 'doctor_id' => self::DOCTOR])
            ->assertOk()->assertJsonPath('doctor_id', self::DOCTOR);
    }

    public function test_clinic_admin_cannot_assign_a_case_to_a_non_doctor(): void
    {
        foreach ([self::SECRETARY, 999] as $userId) {
            $this->actingAs(User::find(self::ADMIN))
                ->putJson('/_test/cases/1', ['patient_id' => 1, 'doctor_id' => $userId])
                ->assertUnprocessable()->assertJsonValidationErrors('doctor_id');
        }
    }

    public function test_editing_without_doctor_id_keeps_the_current_doctor(): void
    {
        foreach ([self::ADMIN, self::OTHER_DOCTOR] as $editor) {
            $this->actingAs(User::find($editor))
                ->putJson('/_test/cases/1', ['patient_id' => 1])
                ->assertOk()->assertJsonPath('doctor_id', self::DOCTOR);
        }
    }

    public function test_doctor_cannot_change_the_doctor_of_a_case(): void
    {
        $this->actingAs(User::find(self::OTHER_DOCTOR))
            ->putJson('/_test/cases/1', ['patient_id' => 1, 'doctor_id' => self::OTHER_DOCTOR])
            ->assertOk()->assertJsonPath('doctor_id', self::DOCTOR);

        $this->actingAs(User::find(self::OTHER_DOCTOR))
            ->postJson('/_test/cases', ['patient_id' => 1, 'doctor_id' => self::DOCTOR])
            ->assertOk()->assertJsonPath('doctor_id', self::OTHER_DOCTOR);
    }

    public function test_cases_created_by_non_doctors_still_belong_to_their_creator(): void
    {
        $this->actingAs(User::find(self::SECRETARY))
            ->postJson('/_test/cases', ['patient_id' => 1])
            ->assertOk()->assertJsonPath('doctor_id', self::SECRETARY);
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
        });
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('case_categores_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $users = [
            self::ADMIN => 'clinic_super_doctor',
            self::DOCTOR => 'doctor',
            self::OTHER_DOCTOR => 'doctor',
            self::SECRETARY => 'secretary',
        ];
        foreach (array_values(array_unique($users)) as $index => $role) {
            DB::table('roles')->insert(['id' => $index + 1, 'name' => $role, 'guard_name' => 'api']);
        }
        foreach ($users as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'name' => $role.' '.$id]);
            DB::table('model_has_roles')->insert([
                'role_id' => DB::table('roles')->where('name', $role)->value('id'),
                'model_type' => (new User)->getMorphClass(), 'model_id' => $id,
            ]);
        }
        DB::table('patients')->insert(['id' => 1]);
        DB::table('statuses')->insert(['id' => 2]);
        DB::table('cases')->insert(['id' => 1, 'doctor_id' => self::DOCTOR]);
    }
}
