<?php

namespace App\Http\Helpers;

use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Log;

/**
 * Shared helper for checking the doctor_bills_isolation clinic setting.
 * Used by DoctorFilterTrait (controllers) and CaseResource.
 */
class BillsIsolationHelper
{
    /**
     * Returns true if the clinic has enabled doctor_bills_isolation.
     * Safe default: false (no behavior change until clinic opts in).
     */
    public static function isEnabled(): bool
    {
        try {
            $setting = ClinicSetting::where('setting_key', 'doctor_bills_isolation')
                ->where('is_active', true)
                ->first();

            if (!$setting) {
                return false;
            }

            return filter_var($setting->setting_value, FILTER_VALIDATE_BOOLEAN);
        } catch (\Exception $e) {
            Log::warning('BillsIsolationHelper: Could not read doctor_bills_isolation setting', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
