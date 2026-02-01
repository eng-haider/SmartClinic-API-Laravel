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
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('case_categores_id'); // Note: keeping original typo for compatibility
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->bigInteger('price')->nullable();
            $table->text('tooth_num')->nullable();
            $table->bigInteger('item_cost')->default(0);
            $table->text('root_stuffing')->nullable();
            $table->boolean('is_paid')->default(false);
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
                  ->onDelete('cascade');

            $table->foreign('case_categores_id')
                  ->references('id')
                  ->on('case_categories')
                  ->onDelete('cascade');

            $table->foreign('status_id')
                  ->references('id')
                  ->on('statuses')
                  ->onDelete('cascade');

            // Indexes for common queries
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('case_categores_id');
            $table->index('status_id');
            $table->index('is_paid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
