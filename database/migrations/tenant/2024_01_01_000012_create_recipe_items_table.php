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
            $table->unsignedBigInteger('recipes_id')->nullable();
            $table->string('name');
            $table->text('dosage')->nullable();
            $table->text('frequency')->nullable();
            $table->text('duration')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('recipes_id')->references('id')->on('recipes')->onDelete('cascade');
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
