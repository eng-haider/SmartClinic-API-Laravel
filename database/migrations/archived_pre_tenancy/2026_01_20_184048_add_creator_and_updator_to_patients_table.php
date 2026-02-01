<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('creator_id')->nullable()->after('clinics_id')->constrained('users')->onDelete('set null');
            $table->foreignId('updator_id')->nullable()->after('creator_id')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->dropForeign(['updator_id']);
            $table->dropColumn(['creator_id', 'updator_id']);
        });
    }
};
