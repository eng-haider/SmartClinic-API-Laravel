<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('notes', 'noteable_type')) {
                $table->string('noteable_type')->nullable()->after('id');
            }
            if (!Schema::hasColumn('notes', 'noteable_id')) {
                $table->unsignedBigInteger('noteable_id')->nullable()->after('noteable_type');
            }
            
            // Add index for polymorphic relationship
            if (!Schema::hasColumn('notes', 'noteable_type') || !Schema::hasColumn('notes', 'noteable_id')) {
                $table->index(['noteable_type', 'noteable_id']);
            }
        });

        // Migrate existing data: copy patient_id to noteable_id and set noteable_type
        DB::statement("UPDATE notes SET noteable_type = 'App\\\\Models\\\\Patient', noteable_id = patient_id WHERE patient_id IS NOT NULL AND noteable_id IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['noteable_type', 'noteable_id']);
            $table->dropColumn(['noteable_type', 'noteable_id']);
        });
    }
};
