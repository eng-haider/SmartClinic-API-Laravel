<?php

namespace Tests\Feature;

use App\Models\AppVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppVersionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('app_versions');
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->default('android');
            $table->string('version', 50);
            $table->unsignedBigInteger('build_number');
            $table->boolean('force_update')->default(false);
            $table->text('apk_url');
            $table->text('message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_latest_android_release_returns_highest_active_released_build(): void
    {
        AppVersion::create([
            'platform' => 'android',
            'version' => '1.0.2',
            'build_number' => 6,
            'apk_url' => 'https://example.com/build-6.apk',
            'is_active' => true,
            'released_at' => now()->subDay(),
        ]);

        AppVersion::create([
            'platform' => 'android',
            'version' => '1.0.3',
            'build_number' => 7,
            'force_update' => true,
            'apk_url' => 'https://example.com/build-7.apk',
            'message' => 'New release',
            'is_active' => true,
            'released_at' => now(),
        ]);

        AppVersion::create([
            'platform' => 'android',
            'version' => '1.0.4',
            'build_number' => 8,
            'apk_url' => 'https://example.com/build-8.apk',
            'is_active' => true,
            'released_at' => now()->addDay(),
        ]);

        $this->getJson('/api/public/app-versions/latest?platform=android')
            ->assertOk()
            ->assertExactJson([
                'version' => '1.0.3',
                'build_number' => 7,
                'force_update' => true,
                'apk_url' => 'https://example.com/build-7.apk',
                'message' => 'New release',
            ]);
    }

    public function test_latest_release_returns_not_found_when_table_has_no_active_release(): void
    {
        $this->getJson('/api/public/app-versions/latest?platform=android')
            ->assertNotFound();
    }
}
