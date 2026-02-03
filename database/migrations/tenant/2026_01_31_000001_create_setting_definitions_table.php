<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores the MASTER list of setting definitions.
     * Super Admin manages this table (adds/removes setting keys).
     * When a new clinic is created, settings are created from this table.
     */
    public function up(): void
    {
        Schema::create('setting_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->string('setting_type')->default('string'); // string, boolean, integer, json
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // general, appointment, notification, financial, display
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('category');
            $table->index('is_active');
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_definitions');
    }
};
