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
        // Change default value
        Schema::table('bills', function (Blueprint $table) {
            $table->boolean('is_paid')->default(true)->change();
        });

        // Optional: Update existing bills to paid
        // DB::table('bills')->where('is_paid', 0)->update(['is_paid' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->change();
        });
    }
};
