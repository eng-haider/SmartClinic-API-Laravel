<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Immutable ledger of every stock movement (audit trail). The running
     * balance lives on warehouse_items.quantity; this table records the deltas.
     */
    public function up(): void
    {
        Schema::create('warehouse_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_item_id');
            $table->string('type'); // purchase | consumption | adjustment
            $table->integer('quantity_change'); // signed: +in / -out
            $table->decimal('unit_cost', 15, 2)->nullable(); // cost per unit at time of movement
            $table->nullableMorphs('source'); // ClinicExpense (purchase) or CaseModel (consumption)
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('warehouse_item_id')
                  ->references('id')
                  ->on('warehouse_items')
                  ->onDelete('cascade');

            $table->foreign('doctor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('creator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for common queries
            $table->index('warehouse_item_id');
            $table->index('type');
            $table->index('doctor_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transactions');
    }
};
