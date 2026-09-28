<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAutomationTargetsJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Tenancy;

class ProcessAutomation extends Command
{
    protected $signature = 'automation:process {--tenant= : Process only a specific tenant}';

    protected $description = 'Process pending automation targets across all tenants and dispatch message jobs';

    public function handle(): int
    {
        $specificTenant = $this->option('tenant');

        $query = Tenant::query();
        if ($specificTenant) {
            $query->where('id', $specificTenant);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return self::SUCCESS;
        }

        $this->info("Processing automation for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant) {
                    ProcessAutomationTargetsJob::dispatch($tenant->id);
                });

                $this->line("  ✓ Dispatched for tenant: {$tenant->id}");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for tenant {$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
