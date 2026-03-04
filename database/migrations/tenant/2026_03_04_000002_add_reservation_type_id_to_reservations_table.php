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
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_type_id')->nullable()->after('status_id');
            // Free-text note used when reservation_type is "Other"
            $table->string('reservation_type_note')->nullable()->after('reservation_type_id');

            $table->foreign('reservation_type_id')
                  ->references('id')
                  ->on('reservation_types')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['reservation_type_id']);
            $table->dropColumn(['reservation_type_id', 'reservation_type_note']);
        });
    }
};
