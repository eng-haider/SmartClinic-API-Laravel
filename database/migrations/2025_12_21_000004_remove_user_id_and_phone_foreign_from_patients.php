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
        Schema::table('patients', function (Blueprint $table) {
            // Drop index for user_id
            $table->dropIndex(['user_id']);
            
            // Drop user_id column
            if (Schema::hasColumn('patients', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Add back user_id column
            $table->unsignedBigInteger('user_id')->nullable()->after('age');
            
            // Add back index
            $table->index('user_id');
        });
    }
};
