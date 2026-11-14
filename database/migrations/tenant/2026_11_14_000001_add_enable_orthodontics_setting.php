<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the enable_orthodontics setting to existing tenant databases.
 *
 * Defaults to off, so no clinic sees a change until an administrator switches
 * the module on in Settings. When on, orthodontics tools appear inside cases
 * whose category is flagged is_orthodontic.
 *
 * Safe to run multiple times - it never overwrites a value already set. The
 * down() only removes the switch; orthodontic records are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('clinic_settings')
            ->where('setting_key', 'enable_orthodontics')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('clinic_settings')->insert([
            'setting_key' => 'enable_orthodontics',
            'setting_value' => '0',
            'setting_type' => 'boolean',
            'description' => 'Orthodontics module: cephalometric, 3D model, CBCT and facial analysis tools inside orthodontic cases',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('clinic_settings')
            ->where('setting_key', 'enable_orthodontics')
            ->delete();
    }
};
