<?php

namespace App\Tenancy;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;

/**
 * Custom Database Manager for Hostinger Shared Hosting
 * 
 * Hostinger limitation: Each database can only have ONE user.
 * You must create both the database AND its dedicated user in hPanel before creating the tenant.
 * 
 * Naming convention:
 * - Database: {prefix}{tenant_id} → u876784197_tenant_alamal
 * - User: Same as database name → u876784197_tenant_alamal
 * - Password: Set in .env as TENANT_DB_PASSWORD (same for all tenant DBs)
 */
class HostingerDatabaseManager extends MySQLDatabaseManager
{
    /**
     * Create the tenant database.
     * On Hostinger, this is a no-op - databases must be created manually in hPanel.
     */
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        // Skip database creation - must be done manually in hPanel
        return true;
    }

    /**
     * Delete the tenant database.
     * On Hostinger, this is a no-op - databases must be deleted manually in hPanel.
     */
    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        // Skip database deletion - must be done manually in hPanel
        return true;
    }

    /**
     * Make the tenant's database connection.
     * This overrides the default to use per-tenant database credentials.
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $config = parent::makeConnectionConfig($baseConfig, $databaseName);
        
        // On Hostinger, each tenant database has its own user with the same name as the database
        // Use the database name as the username
        $config['username'] = $databaseName;
        
        // Use the tenant database password from .env
        // All tenant databases should use the same password (set this in hPanel when creating DBs)
        $config['password'] = env('TENANT_DB_PASSWORD', env('DB_PASSWORD'));
        
        return $config;
    }

    /**
     * Check if the database exists and is accessible.
     */
    public function databaseExists(string $name): bool
    {
        try {
            // Try to connect using tenant-specific credentials
            $connection = $this->database()->connection();
            $config = $connection->getConfig();
            
            // Create a test connection with tenant credentials
            $testConfig = [
                'driver' => 'mysql',
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $name,
                'username' => $name, // On Hostinger, username = database name
                'password' => env('TENANT_DB_PASSWORD', env('DB_PASSWORD')),
                'charset' => $config['charset'] ?? 'utf8mb4',
                'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
            ];
            
            $pdo = new \PDO(
                "mysql:host={$testConfig['host']};port={$testConfig['port']};dbname={$testConfig['database']}",
                $testConfig['username'],
                $testConfig['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
