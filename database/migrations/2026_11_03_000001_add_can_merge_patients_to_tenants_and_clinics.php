<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic switch for merging two patient records into one (PatientMergeService).
     * Off for every clinic until it is turned on through the tenant update API.
     */
    public function up(): void
    {
        foreach (['tenants', 'clinics'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'can_merge_patients')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->boolean('can_merge_patients')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tenants', 'clinics'] as $table) {
            if (Schema::hasColumn($table, 'can_merge_patients')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('can_merge_patients');
                });
            }
        }
    }
};
