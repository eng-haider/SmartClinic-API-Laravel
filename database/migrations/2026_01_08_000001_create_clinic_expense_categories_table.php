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
        Schema::create('clinic_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('clinic_id')
                  ->references('id')
                  ->on('clinics')
                  ->onDelete('cascade');

            $table->foreign('creator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('updator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes
            $table->index('clinic_id');
            $table->index('is_active');
            $table->index('creator_id');
            $table->index('updator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_expense_categories');
    }
};
