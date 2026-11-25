<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Base of the commands that turn one on/off setting of a clinic on, or off with --off.
 * Only the setting changes; no case or payment is touched.
 */
abstract class SwitchClinicSetting extends Command
{
    abstract protected function settingKey(): string;

    abstract protected function settingDescription(): string;

    abstract protected function label(): string;

    public function handle(): int
    {
        $all = $this->hasOption('all') && $this->option('all');
        $tenants = $all ? Tenant::all() : Tenant::where('id', $this->argument('tenant'))->get();

        if ($tenants->isEmpty()) {
            $this->error($all ? 'No clinics found.' : "No clinic with ID {$this->argument('tenant')}.");

            return self::FAILURE;
        }

        $on = !$this->option('off');
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->switch($tenant, $on);
                $this->info("{$this->label()} is now " . ($on ? 'ON' : 'OFF') . " for {$tenant->id} ({$tenant->name}).");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("{$tenant->id}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function switch(Tenant $tenant, bool $on): void
    {
        tenancy()->initialize($tenant);

        try {
            DB::table('clinic_settings')->updateOrInsert(
                ['setting_key' => $this->settingKey()],
                [
                    'setting_value' => $on ? '1' : '0',
                    'setting_type' => 'boolean',
                    'description' => $this->settingDescription(),
                    'is_active' => true,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]
            );
        } finally {
            tenancy()->end();
        }
    }
}
