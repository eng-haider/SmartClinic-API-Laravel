<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The phone app's store releases: one row per store. After a new version is
 * live on Google Play or the App Store, raise that row's version and
 * build_number and the app offers the update with a button to the store.
 * Raise min_build_number too when older builds must update (no "Later").
 *
 * Separate from app_versions, which serves the side-loaded APK updater.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_store_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->unique(); // android | ios
            $table->string('version', 50);            // shown, e.g. 2.1.3
            $table->unsignedBigInteger('build_number'); // compared, e.g. 16
            $table->unsignedBigInteger('min_build_number')->default(0);
            $table->text('store_url');
            $table->text('message_ar')->nullable();
            $table->text('message_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('app_store_releases')->insert([
            [
                'platform' => 'android',
                'version' => '2.1.2',
                'build_number' => 10,
                'min_build_number' => 0,
                'store_url' => 'https://play.google.com/store/apps/details?id=software.smartclinic.smart_clinic_app',
                'message_ar' => null,
                'message_en' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                // Off until its App Store link is filled in.
                'platform' => 'ios',
                'version' => '2.1.2',
                'build_number' => 10,
                'min_build_number' => 0,
                'store_url' => 'https://apps.apple.com/app/idAPP_STORE_ID',
                'message_ar' => null,
                'message_en' => null,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_store_releases');
    }
};
