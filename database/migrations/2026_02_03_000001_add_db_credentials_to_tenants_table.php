<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This adds database credential columns to the tenants table for Hostinger one-user-per-database setup.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('db_name')->nullable()->after('data');
            $table->string('db_username')->nullable()->after('db_name');
            $table->string('db_password')->nullable()->after('db_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['db_name', 'db_username', 'db_password']);
        });
    }
};
