<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateAllTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate 
                            {--seed : Also run seeders after migration}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--tenant= : Migrate only a specific tenant by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for all tenant databases (Hostinger compatible)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificTenant = $this->option('tenant');
        $shouldSeed = $this->option('seed');
        $shouldFresh = $this->option('fresh');

        // Get tenants
        $query = Tenant::query();
        
        if ($specificTenant) {
            $query->where('id', $specificTenant);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return 1;
        }

        $this->info("Found {$tenants->count()} tenant(s) to migrate.");
        $this->newLine();

        $successful = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->id} ({$tenant->name})");
            
            try {
                // Get database credentials
                $dbName = $tenant->db_name ?? (config('tenancy.database.prefix') . $tenant->id);
                $dbUsername = $tenant->db_username ?? $dbName;
                $dbPassword = $tenant->db_password ?? env('TENANT_DB_PASSWORD');

                if (empty($dbPassword)) {
                    $this->warn("  ⚠ No database password found for tenant {$tenant->id}. Skipping.");
                    $failed++;
                    continue;
                }

                // Configure tenant connection
                $centralConfig = config('database.connections.central');
                
                config([
                    'database.connections.tenant.database' => $dbName,
                    'database.connections.tenant.username' => $dbUsername,
                    'database.connections.tenant.password' => $dbPassword,
                    'database.connections.tenant.host' => $centralConfig['host'],
                    'database.connections.tenant.port' => $centralConfig['port'],
                ]);

                DB::purge('tenant');

                // Test connection
                try {
                    DB::connection('tenant')->getPdo();
                    $this->line("  ✓ Connected to database: {$dbName}");
                } catch (\Exception $e) {
                    $this->error("  ✗ Cannot connect to database: {$dbName}");
                    $this->error("    Error: " . $e->getMessage());
                    $failed++;
                    continue;
                }

                // Run migrations
                $migrateCommand = $shouldFresh ? 'migrate:fresh' : 'migrate';
                
                $this->line("  Running {$migrateCommand}...");
                
                Artisan::call($migrateCommand, [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);

                $this->line("  ✓ Migrations completed");

                // Run seeders if requested
                if ($shouldSeed) {
                    $this->line("  Running seeders...");
                    
                    Artisan::call('db:seed', [
                        '--database' => 'tenant',
                        '--class' => 'RoleAndPermissionSeeder',
                        '--force' => true,
                    ]);

                    Artisan::call('db:seed', [
                        '--database' => 'tenant',
                        '--class' => 'TenantDatabaseSeeder',
                        '--force' => true,
                    ]);

                    $this->line("  ✓ Seeders completed");
                }

                $this->info("  ✓ Tenant {$tenant->id} migrated successfully!");
                $successful++;

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to migrate tenant {$tenant->id}");
                $this->error("    Error: " . $e->getMessage());
                Log::error("Tenant migration failed", [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info("=== Migration Summary ===");
        $this->info("Successful: {$successful}");
        if ($failed > 0) {
            $this->error("Failed: {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }
}
