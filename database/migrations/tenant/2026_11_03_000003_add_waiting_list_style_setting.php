<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the waiting_list_style setting to existing tenant databases.
 *
 * Defaults to 'table', the original waiting list, so clinics only see the
 * cards once they pick them in Settings.
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
            ->where('setting_key', 'waiting_list_style')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('clinic_settings')->insert([
            'setting_key'   => 'waiting_list_style',
            'setting_value' => 'table',
            'setting_type'  => 'string',
            'description'   => 'How the waiting list shows today\'s reservations: table (the original data table) or cards',
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
            ->where('setting_key', 'waiting_list_style')
            ->delete();
    }
};
