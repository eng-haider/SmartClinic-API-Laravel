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
        Schema::table('users', function (Blueprint $table) {
            // Drop index first if it exists
            if (Schema::hasColumn('users', 'role')) {
                // Try to drop index, ignore if it doesn't exist
                try {
                    $table->dropIndex(['role']);
                } catch (\Exception $e) {
                    // Index doesn't exist, continue
                }
                $table->dropColumn('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'doctor', 'nurse', 'receptionist', 'user'])->default('user');
            $table->index('role');
        });
    }
};
