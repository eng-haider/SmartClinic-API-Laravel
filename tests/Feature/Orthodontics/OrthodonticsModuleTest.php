<?php

namespace Tests\Feature\Orthodontics;

use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\Orthodontics\DicomFixture;
use Tests\TestCase;

/**
 * Acceptance tests for the orthodontics module against the real routes,
 * middleware, controllers and services.
 *
 * Each clinic (tenant) has its own database in production; here clinic A and
 * clinic B are two separate in-memory databases, and "switching clinic"
 * switches the default connection - exactly what tenancy does. No real
 * clinic database is ever touched.
 */
class OrthodonticsModuleTest extends TestCase
{
    private const ORTHO_CASE = 1;
    private const FILLING_CASE = 2;
    private const OTHER_DOCTOR_CASE = 3;
    private const SECOND_ORTHO_CASE = 4;

    private const ALL = ['view-orthodontics', 'manage-orthodontics', 'view-clinic-cases'];

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['clinic_a', 'clinic_b'] as $clinic) {
            config(["database.connections.$clinic" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            DB::purge($clinic);
            $this->useClinic($clinic);
            $this->createClinicSchema();
            (require base_path('database/migrations/tenant/2026_11_14_000001_add_enable_orthodontics_setting.php'))->up();
            (require base_path('database/migrations/tenant/2026_11_14_000003_create_orthodontics_tables.php'))->up();
        }

        $this->useClinic('clinic_a');
        $this->seedClinicA();
        $this->useClinic('clinic_b');
        $this->seedClinicB();
        $this->useClinic('clinic_a');

        Storage::fake('local');
        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
        $this->actingAsStaff(self::ALL);

        $this->fixture = json_decode(file_get_contents(base_path('tests/Fixtures/orthodontics/ceph_reference.json')), true);
    }

    protected function tearDown(): void
    {
        tenancy()->initialized = false;
        tenancy()->tenant = null;
        Mockery::close();
        parent::tearDown();
    }

    // ---- Test 1: module disabled ------------------------------------------

    public function test_module_disabled_rejects_every_endpoint_and_keeps_data(): void
    {
        $this->enable(true);
        $id = $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'data' => $this->fixture['input']])
            ->assertCreated()->json('data.id');

        $this->enable(false);
        foreach ([
            ['getJson', ''], ['getJson', 'analyses'], ['getJson', "analyses/$id"], ['getJson', 'files'],
            ['getJson', 'cbct'], ['getJson', 'treatment-plan'], ['getJson', 'report'],
            ['postJson', 'analyses'], ['putJson', 'treatment-plan'],
        ] as [$method, $path]) {
            $this->$method($this->url(self::ORTHO_CASE, $path), [])
                ->assertForbidden()->assertJsonPath('code', 'orthodontics_disabled');
        }

        // Disabling never deletes anything ...
        $this->assertDatabaseHas('orthodontic_analyses', ['id' => $id, 'deleted_at' => null]);
        // ... and switching back on shows the same record again.
        $this->enable(true);
        $this->getJson($this->url(self::ORTHO_CASE, "analyses/$id"))->assertOk()->assertJsonPath('data.id', $id);
    }

    public function test_missing_setting_counts_as_disabled(): void
    {
        DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->delete();
        $this->getJson($this->url(self::ORTHO_CASE))->assertForbidden()->assertJsonPath('code', 'orthodontics_disabled');
    }

    // ---- Test 2: enabled, non-orthodontic case ------------------------------

    public function test_non_orthodontic_case_is_refused(): void
    {
        $this->enable(true);
        $this->getJson($this->url(self::FILLING_CASE))->assertForbidden()->assertJsonPath('code', 'orthodontics_not_eligible');
        $this->postJson($this->url(self::FILLING_CASE, 'analyses'), ['type' => 'ceph', 'data' => []])
            ->assertForbidden()->assertJsonPath('code', 'orthodontics_not_eligible');
    }

    // ---- Test 3: enabled, orthodontic case ----------------------------------

    public function test_orthodontic_case_gets_the_workspace(): void
    {
        $this->enable(true);
        $this->getJson($this->url(self::ORTHO_CASE))->assertOk()
            ->assertJsonPath('data.case.id', self::ORTHO_CASE)
            ->assertJsonPath('data.case.category.is_orthodontic', true)
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.capabilities.landmark_detection', false);
    }

    public function test_measurements_are_computed_by_the_server_not_taken_from_the_client(): void
    {
        $this->enable(true);
        $res = $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), [
            'type' => 'ceph',
            'data' => $this->fixture['input'],
            'results' => ['measurements' => ['SNA' => ['value' => 999]]], // ignored
        ])->assertCreated();

        $this->assertEqualsWithDelta(82, $res->json('data.results.measurements.SNA.value'), 0.01);
        $this->assertEqualsWithDelta(1.452, $res->json('data.results.measurements.wits.value'), 0.01);
        $this->assertSame('draft', $res->json('data.status'));
        $this->assertSame(1, $res->json('data.revision'));
    }

    // ---- Test 4: tenant isolation of settings and data -----------------------

    public function test_clinics_have_independent_settings_and_data(): void
    {
        $this->useClinic('clinic_a');
        $this->enable(true);
        $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'data' => $this->fixture['input']])->assertCreated();

        // Clinic B keeps the module off ...
        $this->useClinic('clinic_b');
        $this->assertSame('0', DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->value('setting_value'));
        $this->getJson($this->url(self::ORTHO_CASE))->assertForbidden()->assertJsonPath('code', 'orthodontics_disabled');

        // ... and even with it on, clinic A's case and analyses do not exist there.
        $this->enable(true);
        $this->getJson($this->url(self::ORTHO_CASE))->assertNotFound();
        $this->assertSame(0, DB::table('orthodontic_analyses')->count());

        $this->useClinic('clinic_a');
        $this->assertSame(1, DB::table('orthodontic_analyses')->count());
        $this->assertSame('1', DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->value('setting_value'));
    }

    // ---- Test 5: category change ----------------------------------------------

    public function test_changing_the_category_hides_the_module_without_deleting_data(): void
    {
        $this->enable(true);
        $id = $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'data' => $this->fixture['input']])->json('data.id');

        DB::table('cases')->where('id', self::ORTHO_CASE)->update(['case_categores_id' => 2]); // -> Filling
        $this->getJson($this->url(self::ORTHO_CASE, "analyses/$id"))->assertForbidden()->assertJsonPath('code', 'orthodontics_not_eligible');
        $this->putJson($this->url(self::ORTHO_CASE, "analyses/$id"), ['data' => []])->assertForbidden();
        $this->assertDatabaseHas('orthodontic_analyses', ['id' => $id, 'deleted_at' => null]);

        DB::table('cases')->where('id', self::ORTHO_CASE)->update(['case_categores_id' => 1]); // back to Ortho
        $this->getJson($this->url(self::ORTHO_CASE, "analyses/$id"))->assertOk();
    }

    public function test_eligibility_follows_the_category_flag_not_the_name_or_id(): void
    {
        $this->enable(true);
        // Rename the ortho category and flag a differently named one instead.
        DB::table('case_categories')->where('id', 1)->update(['name' => 'Something else', 'is_orthodontic' => false]);
        DB::table('case_categories')->where('id', 2)->update(['is_orthodontic' => true]);

        $this->getJson($this->url(self::ORTHO_CASE))->assertForbidden();
        $this->getJson($this->url(self::FILLING_CASE))->assertOk();
    }

    // ---- Test 6: medical data isolation ----------------------------------------

    public function test_a_token_issued_for_another_clinic_is_refused(): void
    {
        $this->enable(true);
        $this->app->bind(EnsureTokenMatchesTenant::class, fn () => new class extends EnsureTokenMatchesTenant {
            protected function tokenTenant(Request $request): ?string
            {
                return 'clinic-b';
            }
        });
        tenancy()->tenant = new \App\Models\Tenant(['id' => 'clinic-a']);
        tenancy()->initialized = true;

        $this->getJson($this->url(self::ORTHO_CASE))->assertForbidden()->assertJsonPath('message', 'This token belongs to another clinic.');
        $this->getJson($this->url(self::ORTHO_CASE, 'cbct'))->assertForbidden()->assertJsonPath('message', 'This token belongs to another clinic.');
    }

    public function test_files_and_studies_are_only_reachable_through_their_own_case(): void
    {
        $this->enable(true);
        $fileId = $this->postJson($this->url(self::ORTHO_CASE, 'files'), [
            'kind' => 'ceph_xray', 'file' => UploadedFile::fake()->image('ceph.png', 40, 30),
        ])->assertCreated()->json('data.id');
        $studyId = $this->postJson($this->url(self::ORTHO_CASE, 'cbct'), [])->assertCreated()->json('data.id');

        // Another orthodontic case of the same clinic cannot reach them by id ...
        $this->get($this->url(self::SECOND_ORTHO_CASE, "files/$fileId/content"))->assertNotFound();
        $this->getJson($this->url(self::SECOND_ORTHO_CASE, "cbct/$studyId"))->assertNotFound();
        $this->postJson($this->url(self::SECOND_ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'file_id' => $fileId, 'data' => []])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_file');

        // ... nor can another clinic.
        $this->useClinic('clinic_b');
        $this->enable(true);
        $this->get($this->url(self::ORTHO_CASE, "files/$fileId/content"))->assertNotFound();

        // The owner can, through the authenticated API only - no public URL.
        $this->useClinic('clinic_a');
        $file = $this->getJson($this->url(self::ORTHO_CASE, 'files'))->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('path', $file);
        $this->assertArrayNotHasKey('url', $file);
        $this->get($this->url(self::ORTHO_CASE, "files/$fileId/content"))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_doctors_without_clinic_case_access_only_reach_their_own_cases(): void
    {
        $this->enable(true);
        $this->actingAsStaff(['view-orthodontics', 'manage-orthodontics'], ['doctor']);

        $this->getJson($this->url(self::OTHER_DOCTOR_CASE))->assertNotFound();
        $this->getJson($this->url(self::ORTHO_CASE))->assertOk();
    }

    public function test_permissions_are_enforced_server_side(): void
    {
        $this->enable(true);
        $this->actingAsStaff(['view-clinic-cases']);
        $this->getJson($this->url(self::ORTHO_CASE))->assertForbidden()->assertJsonPath('code', 'orthodontics_forbidden');

        $this->actingAsStaff(['view-orthodontics', 'view-clinic-cases']);
        $this->getJson($this->url(self::ORTHO_CASE))->assertOk()->assertJsonPath('data.can_manage', false);
        $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'data' => []])->assertForbidden();
        $this->putJson($this->url(self::ORTHO_CASE, 'treatment-plan'), ['diagnosis' => 'x'])->assertForbidden();
    }

    // ---- Revisions & review ----------------------------------------------------

    public function test_saves_never_overwrite_silently(): void
    {
        $this->enable(true);
        $id = $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'data' => $this->fixture['input']])->json('data.id');

        $moved = $this->fixture['input'];
        $moved['landmarks']['A']['x'] += 5;
        $this->putJson($this->url(self::ORTHO_CASE, "analyses/$id"), ['data' => $moved, 'expected_revision' => 1])
            ->assertOk()->assertJsonPath('data.revision', 2);

        // A stale client is refused instead of overwriting revision 2.
        $this->putJson($this->url(self::ORTHO_CASE, "analyses/$id"), ['data' => $this->fixture['input'], 'expected_revision' => 1])
            ->assertStatus(409)->assertJsonPath('current_revision', 2);

        $this->getJson($this->url(self::ORTHO_CASE, "analyses/$id/revisions"))->assertOk()->assertJsonCount(2, 'data');
        $first = $this->getJson($this->url(self::ORTHO_CASE, "analyses/$id/revisions/1"))->assertOk();
        $this->assertEqualsWithDelta(82, $first->json('data.results.measurements.SNA.value'), 0.01);
    }

    public function test_review_is_required_for_final_reports_and_reset_by_edits(): void
    {
        $this->enable(true);
        $id = $this->postJson($this->url(self::ORTHO_CASE, 'analyses'), ['type' => 'ceph', 'data' => $this->fixture['input']])->json('data.id');

        $this->getJson($this->url(self::ORTHO_CASE, 'report?final=1'))->assertOk()->assertJsonCount(0, 'data.analyses')->assertJsonPath('data.unreviewed_count', 1);
        $this->getJson($this->url(self::ORTHO_CASE, 'report'))->assertOk()->assertJsonCount(1, 'data.analyses');

        $this->postJson($this->url(self::ORTHO_CASE, "analyses/$id/review"), ['reviewed' => true])->assertOk()->assertJsonPath('data.status', 'reviewed');
        $this->getJson($this->url(self::ORTHO_CASE, 'report?final=1'))->assertOk()->assertJsonCount(1, 'data.analyses');

        $this->putJson($this->url(self::ORTHO_CASE, "analyses/$id"), ['data' => $this->fixture['input']])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->getJson($this->url(self::ORTHO_CASE, 'report?final=1'))->assertOk()->assertJsonCount(0, 'data.analyses');
    }

    // ---- Files ---------------------------------------------------------------

    public function test_model_units_must_be_confirmed(): void
    {
        $this->enable(true);
        $stl = $this->binaryStl(2);
        $file = $this->postJson($this->url(self::ORTHO_CASE, 'files'), [
            'kind' => 'model_upper', 'file' => UploadedFile::fake()->createWithContent('upper.stl', $stl),
        ])->assertCreated();
        $this->assertFalse($file->json('data.meta.units_confirmed'));
        $this->assertSame(2, $file->json('data.meta.triangles'));
        $this->assertSame('binary', $file->json('data.meta.encoding'));

        $id = $file->json('data.id');
        $this->patchJson($this->url(self::ORTHO_CASE, "files/$id"), ['units' => 'cm'])->assertOk()
            ->assertJsonPath('data.meta.units_confirmed', true)
            ->assertJsonPath('data.meta.scale_to_mm', 10);

        $this->postJson($this->url(self::ORTHO_CASE, 'files'), [
            'kind' => 'model_upper', 'file' => UploadedFile::fake()->createWithContent('fake.stl', 'not a mesh at all'),
        ])->assertUnprocessable()->assertJsonPath('code', 'unsupported_file');
    }

    // ---- CBCT ------------------------------------------------------------------

    public function test_cbct_upload_validates_orders_and_streams_instances(): void
    {
        $this->enable(true);
        $studyId = $this->postJson($this->url(self::ORTHO_CASE, 'cbct'), ['description' => 'CBCT'])->assertCreated()->json('data.id');

        $files = [
            UploadedFile::fake()->createWithContent('b.dcm', DicomFixture::make(['instance' => 2, 'z' => 5.0])),
            UploadedFile::fake()->createWithContent('a.dcm', DicomFixture::make(['instance' => 1, 'z' => -5.0])),
            UploadedFile::fake()->createWithContent('notes.txt', 'hello'),
            UploadedFile::fake()->createWithContent('other.dcm', DicomFixture::make(['instance' => 1, 'study' => '9.9.9'])),
        ];
        $res = $this->post($this->url(self::ORTHO_CASE, "cbct/$studyId/instances"), ['files' => $files], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(2, $res->json('data.accepted'));
        $this->assertCount(2, $res->json('data.skipped'));

        // Same file again is a duplicate, not a second slice.
        $again = $this->post($this->url(self::ORTHO_CASE, "cbct/$studyId/instances"), ['files' => [
            UploadedFile::fake()->createWithContent('a2.dcm', DicomFixture::make(['instance' => 1, 'z' => -5.0])),
        ]], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(0, $again->json('data.accepted'));

        // Not viewable until complete.
        $instanceId = DB::table('orthodontic_cbct_instances')->value('id');
        $this->get($this->url(self::ORTHO_CASE, "cbct/$studyId/instances/$instanceId"))->assertStatus(409);

        $this->postJson($this->url(self::ORTHO_CASE, "cbct/$studyId/complete"))->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.instances_count', 2)
            ->assertJsonPath('data.study_instance_uid', '1.2.3.4.100');

        $show = $this->getJson($this->url(self::ORTHO_CASE, "cbct/$studyId"))->assertOk();
        $this->assertSame([1, 2], array_column($show->json('data.instances'), 'instance_number'));
        $this->assertArrayNotHasKey('path', $show->json('data.instances.0'));

        $first = $show->json('data.instances.0.id');
        $stream = $this->get($this->url(self::ORTHO_CASE, "cbct/$studyId/instances/$first"))->assertOk()->assertHeader('Content-Type', 'application/dicom');
        $this->assertSame('DICM', substr($stream->streamedContent(), 128, 4));

        $this->assertDatabaseHas('orthodontic_audit_logs', ['action' => 'cbct.opened', 'case_id' => self::ORTHO_CASE]);
    }

    // ---- Treatment plan ---------------------------------------------------------

    public function test_treatment_plan_upsert_keeps_history(): void
    {
        $this->enable(true);
        $this->getJson($this->url(self::ORTHO_CASE, 'treatment-plan'))->assertOk()->assertJsonPath('data', null);

        $this->putJson($this->url(self::ORTHO_CASE, 'treatment-plan'), [
            'diagnosis' => 'Class II div 1', 'skeletal_class' => 'II', 'overjet_mm' => 7.5,
            'stages' => [['title' => 'Levelling', 'status' => 'in_progress']], 'status' => 'active',
        ])->assertOk()->assertJsonPath('data.revision', 1)->assertJsonPath('data.overjet_mm', 7.5);

        $this->putJson($this->url(self::ORTHO_CASE, 'treatment-plan'), ['overjet_mm' => 5, 'expected_revision' => 1])
            ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.diagnosis', 'Class II div 1');
        $this->putJson($this->url(self::ORTHO_CASE, 'treatment-plan'), ['overjet_mm' => 4, 'expected_revision' => 1])->assertStatus(409);
        $this->putJson($this->url(self::ORTHO_CASE, 'treatment-plan'), ['skeletal_class' => 'IV'])->assertUnprocessable();

        $history = $this->getJson($this->url(self::ORTHO_CASE, 'treatment-plan/history'))->assertOk()->json('data');
        $this->assertSame('plan.updated', $history[0]['action']);
        $this->assertEquals(7.5, $history[0]['details']['previous']['overjet_mm']);
    }

    // ---- Settings & permissions migrations ----------------------------------------

    public function test_setting_is_a_display_setting_staff_can_read(): void
    {
        $display = $this->getJson('/api/tenant/clinic-settings/display')->assertOk()->json('data.display.settings');
        $row = collect($display)->firstWhere('setting_key', 'enable_orthodontics');
        $this->assertNotNull($row);
        $this->assertFalse($row['setting_value']);
    }

    public function test_only_settings_editors_can_switch_the_module(): void
    {
        $this->actingAsStaff(['view-clinic-settings']);
        $this->postJson('/api/tenant/clinic-settings/bulk-update', ['settings' => [['key' => 'enable_orthodontics', 'value' => true, 'type' => 'boolean']]])
            ->assertForbidden();

        $this->actingAsStaff(['edit-clinic-settings']);
        $this->postJson('/api/tenant/clinic-settings/bulk-update', ['settings' => [['key' => 'enable_orthodontics', 'value' => true, 'type' => 'boolean']]])
            ->assertOk();
        $this->assertSame('1', DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->value('setting_value'));
    }

    public function test_setting_migration_is_idempotent_and_never_resets_a_value(): void
    {
        $this->enable(true);
        (require base_path('database/migrations/tenant/2026_11_14_000001_add_enable_orthodontics_setting.php'))->up();
        $this->assertSame(1, DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->count());
        $this->assertSame('1', DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->value('setting_value'));
    }

    public function test_permission_migration_grants_doctors_but_not_secretaries(): void
    {
        (require base_path('database/migrations/tenant/2025_12_07_200612_create_permission_tables.php'))->up();
        foreach (['doctor', 'clinic_super_doctor', 'secretary'] as $role) {
            \Spatie\Permission\Models\Role::create(['name' => $role, 'guard_name' => 'web']);
        }
        $migration = require base_path('database/migrations/tenant/2026_11_14_000002_add_orthodontics_permissions.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('doctor')->hasPermissionTo('manage-orthodontics'));
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('clinic_super_doctor')->hasPermissionTo('view-orthodontics'));
        $this->assertFalse(\Spatie\Permission\Models\Role::findByName('secretary')->hasPermissionTo('view-orthodontics'));
    }

    // ---- helpers -------------------------------------------------------------------

    private function url(int $case, string $path = ''): string
    {
        return "/api/tenant/cases/$case/orthodontics" . ($path !== '' ? '/' . $path : '');
    }

    private function useClinic(string $connection): void
    {
        config(['database.default' => $connection]);
        DB::setDefaultConnection($connection);
    }

    private function enable(bool $on): void
    {
        DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->update(['setting_value' => $on ? '1' : '0']);
    }

    private function actingAsStaff(array $permissions, array $roles = ['clinic_super_doctor']): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Dr. A']);
        $user->shouldReceive('hasPermissionTo')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->shouldReceive('checkPermissionTo')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($r) => in_array($r, $roles, true));
        $this->actingAs($user);
    }

    private function binaryStl(int $triangles): string
    {
        $data = str_pad('binary stl', 80, ' ') . pack('V', $triangles);
        for ($i = 0; $i < $triangles; $i++) {
            $data .= pack('g12', 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0) . pack('v', 0);
        }

        return $data;
    }

    private function seedClinicA(): void
    {
        DB::table('users')->insert([['id' => 1, 'name' => 'Dr. A'], ['id' => 2, 'name' => 'Dr. B']]);
        DB::table('statuses')->insert(['id' => 1, 'name_ar' => 'جديد', 'name_en' => 'New']);
        DB::table('patients')->insert([['id' => 1, 'name' => 'Sara', 'phone' => '0770'], ['id' => 2, 'name' => 'Ali', 'phone' => '0771']]);
        DB::table('case_categories')->insert([
            ['id' => 1, 'name' => 'تقويم', 'category_type' => 'dental', 'is_orthodontic' => true, 'is_active' => true],
            ['id' => 2, 'name' => 'Filling', 'category_type' => 'dental', 'is_orthodontic' => false, 'is_active' => true],
        ]);
        DB::table('cases')->insert([
            ['id' => self::ORTHO_CASE, 'patient_id' => 1, 'doctor_id' => 1, 'case_categores_id' => 1, 'status_id' => 1],
            ['id' => self::FILLING_CASE, 'patient_id' => 1, 'doctor_id' => 1, 'case_categores_id' => 2, 'status_id' => 1],
            ['id' => self::OTHER_DOCTOR_CASE, 'patient_id' => 2, 'doctor_id' => 2, 'case_categores_id' => 1, 'status_id' => 1],
            ['id' => self::SECOND_ORTHO_CASE, 'patient_id' => 2, 'doctor_id' => 1, 'case_categores_id' => 1, 'status_id' => 1],
        ]);
    }

    private function seedClinicB(): void
    {
        // Clinic B: same ids, different people, and case 1 is not orthodontic.
        DB::table('users')->insert(['id' => 1, 'name' => 'Dr. Other']);
        DB::table('statuses')->insert(['id' => 1, 'name_ar' => 'جديد', 'name_en' => 'New']);
        DB::table('patients')->insert(['id' => 1, 'name' => 'Other patient', 'phone' => '0999']);
        DB::table('case_categories')->insert(['id' => 1, 'name' => 'Cleaning', 'category_type' => 'dental', 'is_orthodontic' => false, 'is_active' => true]);
    }

    private function createClinicSchema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('statuses', function (Blueprint $t) {
            $t->id();
            $t->string('name_ar')->nullable();
            $t->string('name_en')->nullable();
            $t->string('color')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('sex')->nullable();
            $t->integer('age')->nullable();
            $t->date('birth_date')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('case_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category_type')->default('dental');
            $t->boolean('is_orthodontic')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('department_id')->nullable();
            $t->integer('order')->nullable();
            $t->integer('item_cost')->nullable();
            $t->boolean('without_detect_tooth')->default(false);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('cases', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('patient_id');
            $t->unsignedBigInteger('doctor_id');
            $t->unsignedBigInteger('case_categores_id')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('status_id');
            $t->bigInteger('price')->nullable();
            $t->text('tooth_num')->nullable();
            $t->text('root_stuffing')->nullable();
            $t->boolean('is_paid')->default(false);
            $t->dateTime('case_date')->nullable();
            $t->bigInteger('item_cost')->default(0);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('clinic_settings', function (Blueprint $t) {
            $t->id();
            $t->string('setting_key')->nullable();
            $t->text('setting_value')->nullable();
            $t->string('setting_type')->default('string');
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });
    }
}
