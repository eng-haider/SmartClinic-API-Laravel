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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->morphs('billable'); // Creates billable_id and billable_type
            $table->boolean('is_paid')->default(false);
            $table->bigInteger('price');
            $table->unsignedBigInteger('clinics_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->boolean('use_credit')->default(false);
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('set null');

            $table->foreign('clinics_id')
                  ->references('id')
                  ->on('clinics')
                  ->onDelete('set null');

            $table->foreign('doctor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for common queries
            $table->index('patient_id');
            $table->index('clinics_id');
            $table->index('doctor_id');
            $table->index('is_paid');
            $table->index('use_credit');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
