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
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->nullable(); // e.g. piece, ml, box
            $table->integer('quantity')->default(0); // current running stock balance
            $table->integer('min_quantity')->default(0); // low-stock alert threshold
            $table->decimal('cost_price', 15, 2)->default(0); // last purchase cost per unit
            $table->unsignedBigInteger('clinic_expense_category_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('clinic_expense_category_id')
                  ->references('id')
                  ->on('clinic_expense_categories')
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
            $table->index('name');
            $table->index('clinic_expense_category_id');
            $table->index('quantity');
            $table->index('creator_id');
            $table->index('updator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_items');
    }
};
