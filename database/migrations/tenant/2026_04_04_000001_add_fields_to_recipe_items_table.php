<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_items', function (Blueprint $table) {
            $table->unsignedBigInteger('recipes_id')->nullable()->after('id');
            $table->string('dosage')->nullable()->after('name');
            $table->string('frequency')->nullable()->after('dosage');
            $table->string('duration')->nullable()->after('frequency');

            $table->foreign('recipes_id')
                  ->references('id')
                  ->on('recipes')
                  ->onDelete('cascade');

            $table->index('recipes_id');
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
