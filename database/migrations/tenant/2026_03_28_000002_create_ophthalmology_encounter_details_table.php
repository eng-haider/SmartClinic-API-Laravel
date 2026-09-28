<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores ophthalmology-specific data for encounters (cases).
     * Each ophthalmology encounter can have eye-specific clinical data.
     */
    public function up(): void
    {
        Schema::create('ophthalmology_encounter_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->unique();

            // Eye selection
            $table->enum('eye_side', ['left', 'right', 'both'])->nullable();

            // Visual Acuity
            $table->string('visual_acuity_left', 20)->nullable();   // e.g. "6/6", "6/12"
            $table->string('visual_acuity_right', 20)->nullable();

            // Intraocular Pressure (IOP)
            $table->decimal('iop_left', 5, 1)->nullable();          // e.g. 14.5 mmHg
            $table->decimal('iop_right', 5, 1)->nullable();

            // Refraction
            $table->string('refraction_left', 50)->nullable();      // e.g. "-2.50 / -0.75 x 180"
            $table->string('refraction_right', 50)->nullable();

            // Clinical findings
            $table->text('anterior_segment')->nullable();            // Slit lamp findings
            $table->text('posterior_segment')->nullable();            // Fundus findings
            $table->text('diagnosis')->nullable();

            // Extra flexible data
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
        Schema::dropIfExists('ophthalmology_encounter_details');
    }
};
