<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->unique(['platform', 'build_number']);
            $table->index(['platform', 'is_active', 'build_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
