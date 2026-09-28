<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the baby_teeth_notation setting to existing tenant databases.
 *
 * Defaults to 'fdi' - the 51-85 numbering the chart has always shown - so no
 * existing clinic sees a change until a doctor picks letters in Settings.
 * Cases keep being stored with the FDI number either way; the setting only
 * changes the label drawn on the chart.
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
        // Insert only - a clinic that already picked a notation through the
        // settings screen keeps its choice.
        $exists = DB::table('clinic_settings')
            ->where('setting_key', 'baby_teeth_notation')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('clinic_settings')->insert([
            'setting_key'   => 'baby_teeth_notation',
            'setting_value' => 'fdi',
            'setting_type'  => 'string',
            'description'   => 'How baby (primary) teeth are labelled on the dental chart: fdi (51-85), universal (A-T) or palmer (A-E per quadrant)',
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
            ->where('setting_key', 'baby_teeth_notation')
            ->delete();
    }
};
