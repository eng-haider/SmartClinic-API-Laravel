<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_items', function (Blueprint $table) {
            if (!Schema::hasColumn('recipe_items', 'recipes_id')) {
                $table->unsignedBigInteger('recipes_id')->nullable()->after('id');
                $table->foreign('recipes_id')
                      ->references('id')
                      ->on('recipes')
                      ->onDelete('cascade');
                $table->index('recipes_id');
            }

            if (!Schema::hasColumn('recipe_items', 'dosage')) {
                $table->string('dosage')->nullable()->after('name');
            }

            if (!Schema::hasColumn('recipe_items', 'frequency')) {
                $table->string('frequency')->nullable()->after('dosage');
            }

            if (!Schema::hasColumn('recipe_items', 'duration')) {
                $table->string('duration')->nullable()->after('frequency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->dropForeign(['recipes_id']);
            $table->dropIndex(['recipes_id']);
            $table->dropColumn(['recipes_id', 'dosage', 'frequency', 'duration']);
        });
    }
};
