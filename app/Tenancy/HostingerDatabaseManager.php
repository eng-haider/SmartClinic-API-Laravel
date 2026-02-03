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
     * Get the tenant database connection configuration.
     * On Hostinger, each database has its own user with the same name as the database.
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        // Start with parent config
        $config = $baseConfig;
        
        // Set database name
        $config['database'] = $databaseName;
        
        // On Hostinger: username = database name
        $config['username'] = $databaseName;
        
        // Use tenant database password from .env (same for all tenants)
        $config['password'] = env('TENANT_DB_PASSWORD', $baseConfig['password'] ?? '');
        
        return $config;
    }

    /**
     * Check if the database exists and is accessible.
     */
    public function databaseExists(string $name): bool
    {
        try {
            // Get central connection config
            $connection = $this->database()->connection();
            $config = $connection->getConfig();
            
            // Try to connect using tenant-specific credentials
            $pdo = new \PDO(
                "mysql:host={$config['host']};port={$config['port']};dbname={$name}",
                $name, // username = database name on Hostinger
                env('TENANT_DB_PASSWORD', $config['password']),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
