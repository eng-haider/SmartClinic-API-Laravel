<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores dental-specific data for encounters (cases).
     * Separates dental fields from the generic cases table.
     * Old data stays in cases.tooth_num/root_stuffing for backward compat.
     */
    public function up(): void
    {
        Schema::create('dental_encounter_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->unique();
            $table->text('tooth_num')->nullable();
            $table->text('root_stuffing')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            $table->foreign('case_id')
                  ->references('id')
                  ->on('cases')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_encounter_details');
    }
};
