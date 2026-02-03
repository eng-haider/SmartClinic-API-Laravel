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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('doctors_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('cascade');

            $table->foreign('doctors_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes
            $table->index('patient_id');
            $table->index('doctors_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
