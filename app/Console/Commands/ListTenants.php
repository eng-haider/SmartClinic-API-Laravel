<?php

namespace App\Console\Commands;

use App\Console\Concerns\ConnectsToTenantDatabase;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ListTenants extends Command
{
    use ConnectsToTenantDatabase;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:list 
                            {--test-connection : Test database connection for each tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenants and their database information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testConnection = $this->option('test-connection');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return 0;
        }

        $this->info("Found {$tenants->count()} tenant(s):");
        $this->newLine();

        $headers = ['ID', 'Name', 'Database', 'Username'];
        if ($testConnection) {
            $headers[] = 'Connection';
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            $dbName = $tenant->db_name ?? (config('tenancy.database.prefix') . $tenant->id);
            $dbUsername = $tenant->db_username ?? $dbName;
            
            $row = [
                $tenant->id,
                $tenant->name,
                $dbName,
                $dbUsername,
            ];

            if ($testConnection) {
                $dbPassword = $tenant->db_password ?? env('TENANT_DB_PASSWORD');

                try {
                    $this->connectTenantDatabase($dbName, $dbUsername, $dbPassword);
                    $row[] = '✓ Connected';
                } catch (\Exception $e) {
                    $row[] = '✗ Failed';
                }
            }

            $rows[] = $row;
        }

        $this->table($headers, $rows);

        return 0;
    }
}
