<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

return new class extends Migration
{
    /**
     * Run the migrations on the current tenant database.
     */
    public function up(): void
    {
        try {
            if (!Schema::hasColumn('bills', 'bill_date')) {
                Schema::table('bills', function (Blueprint $table) {
                    $table->dateTime('bill_date')->nullable()->after('use_credit');
                });
            }
            
            // Update existing records to have created_at as bill_date
            DB::table('bills')
                ->whereNull('bill_date')
                ->update(['bill_date' => DB::raw('created_at')]);
        } catch (\Exception $e) {
            // Silently ignore if column already exists
        }
    }

    /**
     * Reverse the migrations on the current tenant database.
     */
    public function down(): void
    {
        try {
            if (Schema::hasColumn('bills', 'bill_date')) {
                Schema::table('bills', function (Blueprint $table) {
                    $table->dropColumn('bill_date');
                });
            }
        } catch (\Exception $e) {
            // Silently ignore
        }
    }
};
