<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->string('setting_key')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->string('setting_key')->nullable(false)->default('')->change();
        });
    }
};
