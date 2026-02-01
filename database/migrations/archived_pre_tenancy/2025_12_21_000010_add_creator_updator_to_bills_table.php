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
        Schema::table('bills', function (Blueprint $table) {
            $table->unsignedBigInteger('creator_id')->nullable()->after('doctor_id');
            $table->unsignedBigInteger('updator_id')->nullable()->after('creator_id');

            // Foreign keys
            $table->foreign('creator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('updator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes
            $table->index('creator_id');
            $table->index('updator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->dropForeign(['updator_id']);
            $table->dropIndex(['creator_id']);
            $table->dropIndex(['updator_id']);
            $table->dropColumn(['creator_id', 'updator_id']);
        });
    }
};
