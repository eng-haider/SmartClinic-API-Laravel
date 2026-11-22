<?php

namespace Tests\Feature;

use App\Models\AppStoreRelease;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppStoreReleaseControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('app_store_releases');
        $migration = require database_path('migrations/2026_11_21_000001_create_app_store_releases_table.php');
        $migration->up();
    }

    public function test_migration_adds_the_google_play_link_and_keeps_ios_off(): void
    {
        $this->getJson('/api/public/app-store-releases/latest?platform=android')
            ->assertOk()
            ->assertJson([
                'platform' => 'android',
                'build_number' => 10,
                'min_build_number' => 0,
                'store_url' => 'https://play.google.com/store/apps/details?id=software.smartclinic.smart_clinic_app',
            ]);

        $this->getJson('/api/public/app-store-releases/latest?platform=ios')
            ->assertNotFound();
    }

    public function test_returns_the_store_row_once_it_is_filled_in(): void
    {
        AppStoreRelease::where('platform', 'ios')->update([
            'version' => '2.1.3',
            'build_number' => 16,
            'min_build_number' => 12,
            'store_url' => 'https://apps.apple.com/app/id1234567890',
            'message_ar' => 'تحسينات جديدة',
            'message_en' => 'New improvements',
            'is_active' => true,
        ]);

        $this->getJson('/api/public/app-store-releases/latest?platform=IOS')
            ->assertOk()
            ->assertExactJson([
                'platform' => 'ios',
                'version' => '2.1.3',
                'build_number' => 16,
                'min_build_number' => 12,
                'store_url' => 'https://apps.apple.com/app/id1234567890',
                'message_ar' => 'تحسينات جديدة',
                'message_en' => 'New improvements',
            ]);
    }

    public function test_rejects_an_unknown_platform(): void
    {
        $this->getJson('/api/public/app-store-releases/latest?platform=windows')
            ->assertStatus(422);
        $this->getJson('/api/public/app-store-releases/latest')
            ->assertStatus(422);
    }
}
