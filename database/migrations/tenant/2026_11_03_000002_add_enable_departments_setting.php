<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the enable_departments setting to existing tenant databases.
 *
 * Defaults to false, so no clinic sees a change until it is switched on in
 * Settings. When on, case categories can be put in departments and the cases
 * and bills pages can be filtered by department.
 *
 * Safe to run multiple times - it never overwrites a value already set.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::table('clinic_settings')
            ->where('setting_key', 'enable_departments')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('clinic_settings')->insert([
            'setting_key'   => 'enable_departments',
            'setting_value' => '0',
            'setting_type'  => 'boolean',
            'description'   => 'When enabled, case categories can be put in departments (e.g. Orthodontics, Cosmetic) and cases and bills can be filtered by department',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('clinic_settings')
            ->where('setting_key', 'enable_departments')
            ->delete();
    }
};
