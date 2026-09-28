<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the doctor_bills_isolation setting to existing tenant databases.
 *
 * This migration safely upserts the setting with a default value of false (0),
 * so no existing clinic behavior is changed until they explicitly enable it.
 *
 * Safe to run multiple times (uses INSERT ... ON DUPLICATE KEY UPDATE).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('clinic_settings')->updateOrInsert(
            ['setting_key' => 'doctor_bills_isolation'],
            [
                'setting_value' => '0',
                'setting_type'  => 'boolean',
                'description'   => 'When enabled, doctors with the "doctor" role can only see their own bills and cases. Admins and super-doctors are unaffected.',
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('clinic_settings')
            ->where('setting_key', 'doctor_bills_isolation')
            ->delete();
    }
};
