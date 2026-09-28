<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Default bill-of-materials ("kit") per case category. When a case of a
     * given category is created without an explicit items list, these defaults
     * are consumed automatically.
     */
    public function up(): void
    {
        Schema::create('case_category_warehouse_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_category_id');
            $table->unsignedBigInteger('warehouse_item_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('case_category_id')
                  ->references('id')
                  ->on('case_categories')
                  ->onDelete('cascade');

            $table->foreign('warehouse_item_id')
                  ->references('id')
                  ->on('warehouse_items')
                  ->onDelete('cascade');

            // A category lists each item at most once
            $table->unique(['case_category_id', 'warehouse_item_id'], 'cat_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_category_warehouse_item');
    }
};
