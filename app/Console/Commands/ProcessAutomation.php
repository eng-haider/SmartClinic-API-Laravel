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

    private const PAUSE_BETWEEN_TENANTS_US = 100_000;

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

        foreach ($tenants->values() as $i => $tenant) {
            // Every tenant opens a new MySQL connection, and Hostinger refuses more than
            // 20 new connections a second for the whole account, web requests included.
            // Opened back to back, this every-minute run made requests fail in the first
            // seconds of each minute, so it stays at about 10 a second.
            if ($i > 0) {
                usleep(self::PAUSE_BETWEEN_TENANTS_US);
            }

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
