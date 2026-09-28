<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Materials actually consumed per case (source of truth for the case).
     * unit_cost is snapshotted at consumption time so historical cost is stable.
     */
    public function up(): void
    {
        Schema::create('case_warehouse_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('warehouse_item_id');
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('case_id')
                  ->references('id')
                  ->on('cases')
                  ->onDelete('cascade');

            $table->foreign('warehouse_item_id')
                  ->references('id')
                  ->on('warehouse_items')
                  ->onDelete('cascade');

            // Indexes
            $table->index('case_id');
            $table->index('warehouse_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_warehouse_item');
    }
};
