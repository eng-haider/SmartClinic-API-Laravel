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
        try {
            if (!Schema::hasColumn('cases', 'case_date')) {
                Schema::table('cases', function (Blueprint $table) {
                    $table->date('case_date')->nullable()->after('is_paid');
                });
            }
        } catch (\Exception $e) {
            // Silently ignore if column already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('case_date');
        });
    }
};
