<?php

namespace App\Console\Commands;

use App\Repositories\ClinicSettingRepository;

/**
 * Turns the balance_per_patient setting of one clinic on or off. On: the Bills page, the
 * outstanding balances and the doctors report count what a patient owes a doctor over all
 * their cases, as the patient page does. Off (and missing): every case on its own.
 *
 * Usage:
 *   php artisan clinic:balance-per-patient _clinic
 *   php artisan clinic:balance-per-patient _clinic --off
 */
class BalancePerPatient extends SwitchClinicSetting
{
    protected $signature = 'clinic:balance-per-patient
                            {tenant : The clinic (tenant ID)}
                            {--off : Back to counting every case on its own}';

    protected $description = 'Count what a patient owes over all their cases (as the patient page does) for one clinic';

    protected function settingKey(): string
    {
        return ClinicSettingRepository::BALANCE_PER_PATIENT_KEY;
    }

    protected function settingDescription(): string
    {
        return "When enabled, a patient's payments on one case also settle their other cases with the same doctor.";
    }

    protected function label(): string
    {
        return 'Balance per patient';
    }
}
