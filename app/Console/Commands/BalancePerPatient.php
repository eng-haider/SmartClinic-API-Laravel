<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Repositories\ClinicSettingRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Turns the balance_per_patient setting of one clinic on or off. On: the Bills page, the
 * outstanding balances and the doctors report count what a patient owes a doctor over all
 * their cases, as the patient page does. Off (and missing): every case on its own.
 *
 * Only the setting changes; no case or payment is touched.
 *
 * Usage:
 *   php artisan clinic:balance-per-patient _clinic
 *   php artisan clinic:balance-per-patient _clinic --off
 */
class BalancePerPatient extends Command
{
    protected $signature = 'clinic:balance-per-patient
                            {tenant : The clinic (tenant ID)}
                            {--off : Back to counting every case on its own}';

    protected $description = 'Count what a patient owes over all their cases (as the patient page does) for one clinic';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error("No clinic with ID {$this->argument('tenant')}.");

            return self::FAILURE;
        }

        $on = !$this->option('off');

        tenancy()->initialize($tenant);

        try {
            DB::table('clinic_settings')->updateOrInsert(
                ['setting_key' => ClinicSettingRepository::BALANCE_PER_PATIENT_KEY],
                [
                    'setting_value' => $on ? '1' : '0',
                    'setting_type' => 'boolean',
                    'description' => "When enabled, a patient's payments on one case also settle their other cases with the same doctor.",
                    'is_active' => true,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]
            );
        } finally {
            tenancy()->end();
        }

        $this->info("Balance per patient is now " . ($on ? 'ON' : 'OFF') . " for {$tenant->id} ({$tenant->name}).");

        return self::SUCCESS;
    }
}
