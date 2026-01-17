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
        Schema::table('cases', function (Blueprint $table) {
            // Drop item_cost column
            if (Schema::hasColumn('cases', 'item_cost')) {
                $table->dropColumn('item_cost');
            }
            
            // Add clinic_id column
            if (!Schema::hasColumn('cases', 'clinic_id')) {
                $table->unsignedBigInteger('clinic_id')->nullable()->after('doctor_id');
                
                // Add foreign key
                $table->foreign('clinic_id')
                      ->references('id')
                      ->on('clinics')
                      ->onDelete('cascade');
                
                // Add index
                $table->index('clinic_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Drop foreign key and clinic_id column
            if (Schema::hasColumn('cases', 'clinic_id')) {
                $table->dropForeign(['clinic_id']);
                $table->dropIndex(['clinic_id']);
                $table->dropColumn('clinic_id');
            }
            
            // Add back item_cost column
            $table->bigInteger('item_cost')->default(0)->after('tooth_num');
        });
    }
};
