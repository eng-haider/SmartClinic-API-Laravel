<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the patient_age_input setting to existing tenant databases.
 *
 * Defaults to 'birth_date', which is what the patient form already asks for,
 * so no clinic sees a change until it picks 'age' in Settings.
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
            ->where('setting_key', 'patient_age_input')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('clinic_settings')->insert([
            'setting_key'   => 'patient_age_input',
            'setting_value' => 'birth_date',
            'setting_type'  => 'string',
            'description'   => 'What the patient form asks for: birth_date (the age is worked out from it) or age (a number of years)',
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
            ->where('setting_key', 'patient_age_input')
            ->delete();
    }
};
