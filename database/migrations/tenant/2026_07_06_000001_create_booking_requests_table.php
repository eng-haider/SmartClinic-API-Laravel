<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staging table for public booking submissions from a clinic's website.
     * Rows here are NOT reservations yet — staff review each one and, on
     * approval, a real patient + reservation is created and linked back.
     */
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();

            // Data entered by the patient on the public booking page
            $table->string('name');
            $table->string('phone', 33);
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->text('note')->nullable();

            // Review workflow: pending -> approved | rejected
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();

            // Set once a staff member reviews the request
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('patient_id')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('set null');

            $table->foreign('reservation_id')
                  ->references('id')
                  ->on('reservations')
                  ->onDelete('set null');

            $table->foreign('reviewed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('status');
            $table->index('phone');
            $table->index('preferred_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
