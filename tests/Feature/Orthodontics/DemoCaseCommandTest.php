<?php

namespace Tests\Feature\Orthodontics;

use App\Modules\Orthodontics\Console\CreateDemoCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * php artisan orthodontics:demo, run against an in-memory clinic database
 * (the tenant lookup is replaced; no real clinic database is touched).
 */
class DemoCaseCommandTest extends TestCase
{
    /** @var list<string> */
    private array $photoFolders = [];

    protected function tearDown(): void
    {
        foreach ($this->photoFolders as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.demo_clinic' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('demo_clinic');
        config(['database.default' => 'demo_clinic']);
        DB::setDefaultConnection('demo_clinic');
        $this->createClinicSchema();
        (require base_path('database/migrations/tenant/2026_11_14_000001_add_enable_orthodontics_setting.php'))->up();
        (require base_path('database/migrations/tenant/2026_11_14_000003_create_orthodontics_tables.php'))->up();

        DB::table('users')->insert(['id' => 7, 'name' => 'Dr. Demo']);
        DB::table('statuses')->insert(['id' => 1, 'name_ar' => 'جديد', 'name_en' => 'New']);
        DB::table('case_categories')->insert(['id' => 1, 'name' => 'Filling', 'is_orthodontic' => false]);

        Storage::fake('local');

        // Run the command's work directly in this database instead of a tenant's.
        $this->app->bind(CreateDemoCase::class, fn () => new class extends CreateDemoCase {
            protected function inClinic(string $tenantId, \Closure $work): ?int
            {
                return $tenantId === 'demo' ? (int) $work() : null;
            }
        });
    }

    public function test_it_builds_a_complete_demo_case(): void
    {
        $this->artisan('orthodontics:demo', ['--tenant' => 'demo', '--photos' => $this->photosFolder()])
            ->expectsOutputToContain('Photos: none found')
            ->assertSuccessful();

        $this->assertSame('1', DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->value('setting_value'));

        // A flagged category was created, and the case uses it.
        $case = DB::table('cases')->first();
        $this->assertTrue((bool) DB::table('case_categories')->where('id', $case->case_categores_id)->value('is_orthodontic'));
        $this->assertSame(7, (int) $case->doctor_id);
        $patient = DB::table('patients')->find($case->patient_id);
        $this->assertSame(CreateDemoCase::PATIENT_IDENTIFIER, $patient->identifier);

        // The ceph is fully traced, ruler-calibrated and computed by the server.
        $ceph = DB::table('orthodontic_analyses')->where('type', 'ceph')->first();
        $this->assertSame('draft', $ceph->status);
        $results = json_decode($ceph->results, true);
        $this->assertSame('ruler', $results['calibration']['method']);
        $this->assertEqualsWithDelta(10 / 85, $results['calibration']['mm_per_px'], 1e-9);
        $this->assertEqualsWithDelta(88.9, $results['measurements']['SNA']['value'], 0.1);
        $this->assertEqualsWithDelta(90.4, $results['measurements']['SNB']['value'], 0.1);
        $this->assertEqualsWithDelta(-1.6, $results['measurements']['ANB']['value'], 0.1);
        $this->assertCount(count(CreateDemoCase::LANDMARKS), json_decode($ceph->data, true)['landmarks']);

        // The radiograph and both models are stored; model units are confirmed.
        $files = DB::table('orthodontic_files')->where('case_id', $case->id)->get()->keyBy('kind');
        $this->assertEqualsCanonicalizing(['ceph_xray', 'model_upper', 'model_lower'], $files->keys()->all());
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file->path);
        }
        $this->assertSame(2272, (int) $files['ceph_xray']->width);
        $meta = json_decode($files['model_upper']->meta, true);
        $this->assertTrue($meta['units_confirmed']);
        $this->assertSame('mm', $meta['units']);
        $this->assertSame('binary', $meta['encoding']);

        // The cast analysis uses both models and gives the documented Bolton ratios.
        $cast = DB::table('orthodontic_analyses')->where('type', 'cast')->first();
        $castData = json_decode($cast->data, true);
        $this->assertSame(['upper' => (int) $files['model_upper']->id, 'lower' => (int) $files['model_lower']->id], $castData['model_file_ids']);
        $bolton = json_decode($cast->results, true)['bolton'];
        $this->assertEqualsWithDelta(90.87, $bolton['overall']['ratio'], 0.01);
        $this->assertSame('within', $bolton['overall']['comparison']['status']);
        $this->assertEqualsWithDelta(74.67, $bolton['anterior']['ratio'], 0.01);
        $this->assertSame('below', $bolton['anterior']['comparison']['status']);

        // The CBCT phantom is a finished study with every slice.
        $study = DB::table('orthodontic_cbct_studies')->where('case_id', $case->id)->first();
        $this->assertSame('ready', $study->status);
        $this->assertSame(80, (int) $study->instances_count);

        $plan = DB::table('orthodontic_treatment_plans')->where('case_id', $case->id)->first();
        $this->assertSame('III', $plan->skeletal_class);
        $this->assertCount(4, json_decode($plan->stages, true));

        // The demo assets are copied, never moved.
        $this->assertFileExists(database_path('demo/orthodontics/ceph_lateral.jpg'));
    }

    public function test_it_uploads_the_face_and_intraoral_photos_in_the_photos_folder(): void
    {
        $dir = $this->photosFolder([
            'face_frontal.jpg' => [40, 50],
            'face_smile.png' => [40, 50],
            'face_profile.jpg' => [50, 40],
            'intraoral_upper.jpg' => [60, 30],
            'Intraoral_lower.JPG' => [60, 30],
            'notes.txt' => 'not a photo, ignored',
        ]);

        $this->artisan('orthodontics:demo', ['--tenant' => 'demo', '--photos' => $dir])
            ->expectsOutputToContain('Photos: 1 face_frontal, 1 face_smile, 1 face_profile, 2 intraoral_photo')
            ->assertSuccessful();

        $photos = DB::table('orthodontic_files')->whereNotIn('kind', ['ceph_xray', 'model_upper', 'model_lower'])->get();
        $this->assertSame(
            ['face_frontal' => 1, 'face_profile' => 1, 'face_smile' => 1, 'intraoral_photo' => 2],
            $photos->countBy('kind')->sortKeys()->all()
        );
        foreach ($photos as $photo) {
            Storage::disk('local')->assertExists($photo->path);
        }
        $frontal = $photos->firstWhere('kind', 'face_frontal');
        $this->assertSame('face_frontal.jpg', $frontal->original_name);
        $this->assertSame([40, 50], [(int) $frontal->width, (int) $frontal->height]);

        // The doctor runs the face detection live, so no face analysis is made.
        $this->assertSame(0, DB::table('orthodontic_analyses')->where('type', 'face')->count());

        // The photos are copied, never moved.
        $this->assertFileExists("$dir/face_frontal.jpg");
    }

    public function test_it_skips_a_photo_it_cannot_use_and_still_builds_the_case(): void
    {
        $dir = $this->photosFolder([
            'face_frontal.jpg' => 'this is not an image',
            'holiday.jpg' => [40, 50],
            'face_smile.jpg' => [40, 50],
        ]);

        $this->artisan('orthodontics:demo', ['--tenant' => 'demo', '--photos' => $dir])
            ->expectsOutputToContain("Photo 'face_frontal.jpg' skipped: The file is not a readable image.")
            ->expectsOutputToContain("Photo 'holiday.jpg' skipped: start its name with face_frontal, face_smile, face_profile, intraoral")
            ->assertSuccessful();

        $kinds = DB::table('orthodontic_files')->pluck('kind')->all();
        $this->assertNotContains('face_frontal', $kinds);
        $this->assertContains('face_smile', $kinds);
        $this->assertSame(1, DB::table('orthodontic_treatment_plans')->count());
    }

    public function test_it_refuses_a_photos_folder_that_does_not_exist(): void
    {
        $this->artisan('orthodontics:demo', ['--tenant' => 'demo', '--photos' => '/no/such/demo/photos'])
            ->expectsOutputToContain("No folder at '/no/such/demo/photos'")
            ->assertFailed();

        $this->assertSame(0, DB::table('cases')->count());
    }

    public function test_running_it_again_reuses_the_patient_and_adds_a_case(): void
    {
        $this->artisan('orthodontics:demo', ['--tenant' => 'demo'])->assertSuccessful();
        $this->artisan('orthodontics:demo', ['--tenant' => 'demo'])->assertSuccessful();

        $this->assertSame(1, DB::table('patients')->count());
        $this->assertSame(2, DB::table('cases')->count());
        $this->assertSame(1, DB::table('case_categories')->where('is_orthodontic', true)->count());
    }

    public function test_it_uses_the_category_it_is_given_and_can_leave_the_setting_off(): void
    {
        $this->artisan('orthodontics:demo', ['--tenant' => 'demo', '--category' => 1, '--no-enable' => true])->assertSuccessful();

        $this->assertSame(1, (int) DB::table('cases')->value('case_categores_id'));
        $this->assertTrue((bool) DB::table('case_categories')->where('id', 1)->value('is_orthodontic'));
        $this->assertSame('0', DB::table('clinic_settings')->where('setting_key', 'enable_orthodontics')->value('setting_value'));
    }

    public function test_it_refuses_an_unknown_clinic_or_a_missing_tenant_option(): void
    {
        $this->artisan('orthodontics:demo', ['--tenant' => 'nope'])->assertFailed();
        $this->artisan('orthodontics:demo')->assertFailed();
        $this->assertSame(0, DB::table('cases')->count());
    }

    /**
     * A temporary photos folder. Each entry is a generated image of
     * [width, height] pixels (PNG or JPEG by its extension), or a string
     * written as the file's content.
     *
     * @param array<string, array{0: int, 1: int}|string> $files
     */
    private function photosFolder(array $files = []): string
    {
        $dir = sys_get_temp_dir() . '/ortho-demo-photos-' . Str::random(8);
        File::ensureDirectoryExists($dir);
        $this->photoFolders[] = $dir;

        foreach ($files as $name => $content) {
            $path = "$dir/$name";
            if (is_string($content)) {
                file_put_contents($path, $content);

                continue;
            }
            $image = imagecreatetruecolor($content[0], $content[1]);
            str_ends_with(strtolower($name), '.png') ? imagepng($image, $path) : imagejpeg($image, $path);
        }

        return $dir;
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
            $t->integer('order')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('phone');
            $t->integer('age')->nullable();
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->text('notes')->nullable();
            $t->string('identifier')->nullable();
            $t->string('public_token')->nullable();
            $t->boolean('is_public_profile_enabled')->default(false);
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->unsignedBigInteger('updator_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('case_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('name_ar')->nullable();
            $t->string('name_en')->nullable();
            $t->string('category_type')->default('dental');
            $t->boolean('is_orthodontic')->default(false);
            $t->boolean('is_active')->default(true);
            $t->integer('order')->nullable();
            $t->bigInteger('item_cost')->default(0);
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
