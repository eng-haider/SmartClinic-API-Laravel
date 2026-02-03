<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListTenants extends Command
{
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
                $centralConfig = config('database.connections.central');
                
                config([
                    'database.connections.tenant.database' => $dbName,
                    'database.connections.tenant.username' => $dbUsername,
                    'database.connections.tenant.password' => $dbPassword,
                    'database.connections.tenant.host' => $centralConfig['host'],
                    'database.connections.tenant.port' => $centralConfig['port'],
                ]);

                DB::purge('tenant');

                try {
                    DB::connection('tenant')->getPdo();
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
