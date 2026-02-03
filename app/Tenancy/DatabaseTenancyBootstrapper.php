<?php

namespace App\Tenancy;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper as BaseDatabaseTenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Custom Database Tenancy Bootstrapper for Hostinger
 * 
 * Overrides the default bootstrapper to use per-tenant database credentials.
 * On Hostinger, each database has its own user (same name as database).
 */
class DatabaseTenancyBootstrapper extends BaseDatabaseTenancyBootstrapper
{
    /**
     * The original default database connection.
     */
    protected $originalDefaultConnection;

    /**
     * Bootstrap tenancy for the given tenant.
     */
    public function bootstrap(Tenant $tenant)
    {
        /** @var DatabaseManager $database */
        $database = app(DatabaseManager::class);

        $originalDefaultConnection = $database->getDefaultConnection();
        $tenantConnection = config('tenancy.database.tenant_connection_name', 'tenant');
        $centralConnection = config('tenancy.database.central_connection');

        $database->extend($tenantConnection, function ($config, $name) use ($tenant, $centralConnection) {
            $baseConfig = config("database.connections.{$centralConnection}");
            
            // Build database name
            $prefix = config('tenancy.database.prefix', 'tenant');
            $suffix = config('tenancy.database.suffix', '');
            $databaseName = $prefix . $tenant->getTenantKey() . $suffix;
            
            // On Hostinger: username = database name, password from TENANT_DB_PASSWORD
            return [
                'driver' => $baseConfig['driver'] ?? 'mysql',
                'host' => $baseConfig['host'],
                'port' => $baseConfig['port'] ?? 3306,
                'database' => $databaseName,
                'username' => $databaseName, // Hostinger: user = database name
                'password' => env('TENANT_DB_PASSWORD', $baseConfig['password']),
                'charset' => $baseConfig['charset'] ?? 'utf8mb4',
                'collation' => $baseConfig['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix' => $baseConfig['prefix'] ?? '',
                'prefix_indexes' => $baseConfig['prefix_indexes'] ?? true,
                'strict' => $baseConfig['strict'] ?? true,
                'engine' => $baseConfig['engine'] ?? null,
                'options' => $baseConfig['options'] ?? [],
            ];
        });

        $database->purge($tenantConnection);
        $database->setDefaultConnection($tenantConnection);

        // Store original connection for reversion
        $this->originalDefaultConnection = $originalDefaultConnection;
    }

    /**
     * Revert tenancy changes.
     */
    public function revert()
    {
        $database = app(DatabaseManager::class);
        $tenantConnection = config('tenancy.database.tenant_connection_name', 'tenant');

        $database->purge($tenantConnection);
        
        if (isset($this->originalDefaultConnection)) {
            $database->setDefaultConnection($this->originalDefaultConnection);
            $this->originalDefaultConnection = null;
        }
    }
}
