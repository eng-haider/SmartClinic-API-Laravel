<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PatientPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated memory database, like BillingOverviewTest: never touch a clinic.
        config([
            'database.default' => 'patient_photo_test',
            'database.connections.patient_photo_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('patient_photo_test');
        $this->createSchema();
        Storage::fake('public');
        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
        $this->actAs(canEditPatients: true);
    }

    public function test_upload_sets_photo_and_replaces_the_previous_one(): void
    {
        $patient = $this->patient();

        $first = $this->postJson("/api/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertNotNull($first->json('data.profile_photo_url'));
        $firstPath = DB::table('images')->value('path');

        $second = $this->postJson("/api/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->image('b.png')])
            ->assertOk();

        $live = DB::table('images')->whereNull('deleted_at')->where('type', 'profile')->get();
        $this->assertCount(1, $live);
        $this->assertNotSame($firstPath, $live[0]->path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($live[0]->path);
        $this->assertStringContainsString($live[0]->path, $second->json('data.profile_photo_url'));
    }

    public function test_patient_show_and_list_include_the_photo(): void
    {
        $patient = $this->patient();
        $this->postJson("/api/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $path = DB::table('images')->value('path');

        $this->assertStringContainsString($path, $this->getJson("/api/patients/{$patient}")
            ->assertOk()->json('data.profile_photo_url'));
        $this->assertStringContainsString($path, $this->getJson('/api/patients')
            ->assertOk()->json('data.0.profile_photo_url'));
    }

    public function test_other_patient_images_are_not_the_profile_photo(): void
    {
        $patient = $this->patient();
        DB::table('images')->insert([
            'path' => 'images/document/x.jpg', 'disk' => 'public', 'type' => 'document',
            'imageable_type' => 'Patient', 'imageable_id' => $patient, 'order' => 0,
        ]);

        $this->getJson("/api/patients/{$patient}")->assertOk()
            ->assertJsonPath('data.profile_photo_url', null);
    }

    public function test_delete_removes_the_photo(): void
    {
        $patient = $this->patient();
        $this->postJson("/api/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $path = DB::table('images')->value('path');

        $this->deleteJson("/api/patients/{$patient}/photo")->assertOk()
            ->assertJsonPath('data.profile_photo_url', null);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, DB::table('images')->whereNull('deleted_at')->count());
    }

    public function test_upload_rejects_non_images_and_unknown_patients(): void
    {
        $patient = $this->patient();

        $this->postJson("/api/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->postJson('/api/patients/999/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertNotFound();
    }

    public function test_photo_routes_need_edit_patient_permission(): void
    {
        $patient = $this->patient();
        $this->actAs(canEditPatients: false);

        $this->postJson("/api/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
        $this->deleteJson("/api/patients/{$patient}/photo")->assertForbidden();
        $this->postJson("/api/tenant/patients/{$patient}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
        $this->assertSame(0, DB::table('images')->count());
    }

    private function actAs(bool $canEditPatients): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Doctor One', 'clinic_id' => 1]);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('hasPermissionTo')->andReturnUsing(
            fn (string $permission) => $permission !== 'edit-patient' || $canEditPatients
        );
        $this->actingAs($user);
    }

    private function patient(): int
    {
        return DB::table('patients')->insertGetId(['name' => 'Patient', 'phone' => '0770000']);
    }

    private function createSchema(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            // A real column in production; without it the resource would lazy-load
            // the notes() relation of the same name.
            $table->text('notes')->nullable();
            $table->string('public_token')->nullable();
            $table->boolean('is_public_profile_enabled')->default(true);
            $table->text('tooth_details')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('type')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('tooth_num', 100)->nullable();
            $table->integer('order')->default(0);
            $table->morphs('imageable');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
