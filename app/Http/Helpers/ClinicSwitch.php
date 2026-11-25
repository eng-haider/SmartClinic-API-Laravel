<?php

namespace App\Http\Helpers;

use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Log;

/**
 * Reads an on/off clinic setting. Off when the setting is missing or can't be read.
 */
class ClinicSwitch
{
    public static function isOn(string $key): bool
    {
        try {
            return filter_var(
                ClinicSetting::where('setting_key', $key)->where('is_active', true)->value('setting_value'),
                FILTER_VALIDATE_BOOLEAN
            );
        } catch (\Exception $e) {
            Log::warning("ClinicSwitch: Could not read the {$key} setting", ['error' => $e->getMessage()]);

            return false;
        }
    }
}
