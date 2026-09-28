<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-created database pool for Hostinger shared hosting.
     * Since Hostinger does not allow programmatic CREATE DATABASE,
     * databases are created manually in hPanel and tracked here.
     * At clinic signup, one 'available' record is claimed atomically.
     */
    public function up(): void
    {
        Schema::create('database_pool', function (Blueprint $table) {
            $table->id();
            $table->string('db_name')->unique();
            $table->string('db_username');
            $table->string('db_password');
            $table->enum('status', ['available', 'used'])->default('available')->index();
            $table->string('tenant_id')->nullable()->index(); // filled when claimed
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_pool');
    }
};
