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
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('doctors_id')->nullable();
            $table->unsignedBigInteger('clinics_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('doctors_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('clinics_id')
                  ->references('id')
                  ->on('clinics')
                  ->onDelete('set null');

            // Indexes
            $table->index('doctors_id');
            $table->index('clinics_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
