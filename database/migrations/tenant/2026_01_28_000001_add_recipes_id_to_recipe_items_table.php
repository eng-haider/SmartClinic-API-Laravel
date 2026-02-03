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
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->unsignedBigInteger('recipes_id')->nullable()->after('name');
            
            // Foreign key
            $table->foreign('recipes_id')
                  ->references('id')
                  ->on('recipes')
                  ->onDelete('cascade');
            
            // Index
            $table->index('recipes_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->dropForeign(['recipes_id']);
            $table->dropIndex(['recipes_id']);
            $table->dropColumn('recipes_id');
        });
    }
};
