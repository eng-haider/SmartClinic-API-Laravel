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
        Schema::create('clinic_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('quantity')->nullable();
            $table->unsignedBigInteger('clinic_expense_category_id')->nullable();
            $table->unsignedBigInteger('clinic_id');
            $table->date('date')->default(now());
            $table->decimal('price', 15, 2);
            $table->boolean('is_paid')->default(true);
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('clinic_expense_category_id')
                  ->references('id')
                  ->on('clinic_expense_categories')
                  ->onDelete('set null');

            $table->foreign('clinic_id')
                  ->references('id')
                  ->on('clinics')
                  ->onDelete('cascade');

            $table->foreign('doctor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('creator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('updator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for common queries
            $table->index('clinic_expense_category_id');
            $table->index('clinic_id');
            $table->index('doctor_id');
            $table->index('date');
            $table->index('is_paid');
            $table->index('creator_id');
            $table->index('updator_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_expenses');
    }
};
