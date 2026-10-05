<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the hide_dashboard_numbers setting to existing tenant databases.
 *
 * Defaults to false, so no clinic sees a change until it is switched on in
 * Settings. When on, the dashboard opens with its counts and amounts hidden
 * and shows a button to reveal them.
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
            ->where('setting_key', 'hide_dashboard_numbers')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('clinic_settings')->insert([
            'setting_key'   => 'hide_dashboard_numbers',
            'setting_value' => '0',
            'setting_type'  => 'boolean',
            'description'   => 'When enabled, the dashboard opens with its counts and amounts hidden, and a button shows or hides them',
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
            ->where('setting_key', 'hide_dashboard_numbers')
            ->delete();
    }
};
