<?php

namespace App\Http\Helpers;

use App\Repositories\ClinicSettingRepository;

/**
 * Shared helper for the balance_per_patient clinic setting: whether a patient's payments
 * on one case also settle their other cases with the same doctor, as on the patient page.
 */
class PatientBalanceHelper
{
    /**
     * Off when the setting is missing: every case then keeps its own balance.
     */
    public static function isPerPatient(): bool
    {
        return ClinicSwitch::isOn(ClinicSettingRepository::BALANCE_PER_PATIENT_KEY);
    }
}
