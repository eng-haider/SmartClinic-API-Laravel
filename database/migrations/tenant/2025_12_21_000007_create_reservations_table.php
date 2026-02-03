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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->text('notes')->nullable();
            $table->date('reservation_start_date');
            $table->date('reservation_end_date');
            $table->time('reservation_from_time');
            $table->time('reservation_to_time')->nullable();
            $table->boolean('is_waiting')->default(false);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('cascade');

            $table->foreign('doctor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('status_id')
                  ->references('id')
                  ->on('statuses')
                  ->onDelete('cascade');

            $table->foreign('creator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('updator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for common queries
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('status_id');
            $table->index('reservation_start_date');
            $table->index('reservation_end_date');
            $table->index('is_waiting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
