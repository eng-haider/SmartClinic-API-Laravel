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
        Schema::table('case_categories', function (Blueprint $table) {
            $table->boolean('without_detect_tooth')->default(0)->after('item_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_categories', function (Blueprint $table) {
            $table->dropColumn('without_detect_tooth');
        });
    }
};
