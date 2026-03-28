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
     * Locally, we create the database automatically.
     */
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        // if (app()->environment('local', 'development', 'testing')) {
        //     $databaseName = $this->getTenantDatabaseName($tenant);
        //     DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");
        //     return true;
        // }

        // Skip database creation on production - must be done manually in hPanel
        return true;
    }

    /**
     * Delete the tenant database.
     * On Hostinger, this is a no-op - databases must be deleted manually in hPanel.
     * Locally, we drop the database automatically.
     */
    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        if (app()->environment('local', 'development', 'testing')) {
            $databaseName = $tenant->db_name
                ?? (config('tenancy.database.prefix') . $tenant->getTenantKey() . config('tenancy.database.suffix'));
            DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
            return true;
        }

        // Skip database deletion on production - must be done manually in hPanel
        return true;
    }

    /**
     * Get the database name for a tenant.
     */
    // protected function getTenantDatabaseName(TenantWithDatabase $tenant): string
    // {
    //     $prefix = config('tenancy.database.prefix', 'tenant');
    //     return $prefix . $tenant->getTenantKey();
    // }

    /**
     * Get the tenant database connection configuration.
     *
     * Priority 1 — Pool credentials stored on the tenant model (db_name / db_username / db_password).
     *   These are set during registration from the pre-created database pool.
     *   Stancl passes us a constructed name (prefix+tenant_id) which is WRONG for pool DBs,
     *   so we read directly from the model via the active tenancy singleton.
     *
     * Priority 2 — Legacy / local fallback: username = database name, password from env.
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        // Access the tenant currently being initialized.
        // tenancy()->tenant is set by Tenancy::initialize() before bootstrappers run,
        // so it is available here during the DatabaseTenancyBootstrapper bootstrap call.
        $tenant = app(\Stancl\Tenancy\Tenancy::class)->tenant ?? null;

        if ($tenant && !empty($tenant->db_name)) {
            // Pool system: use the exact credentials stored at registration time.
            return array_merge($baseConfig, [
                'database' => $tenant->db_name,
                'username' => $tenant->db_username,
                'password' => $tenant->db_password,
            ]);
        }

        // Fallback: legacy convention (username = database name, password from .env).
        return array_merge($baseConfig, [
            'database' => $databaseName,
            'username' => $databaseName,
            'password' => env('TENANT_DB_PASSWORD', $baseConfig['password'] ?? ''),
        ]);
    }

    /**
     * Check if the database exists and is accessible.
     * On Hostinger shared hosting, we assume the database exists (created manually).
     * Skipping this check prevents errors during tenant creation.
     */
    public function databaseExists(string $name): bool
    {
        // On shared hosting, databases are created manually in hPanel
        // We skip the existence check to avoid permission errors
        // The actual connection test happens in TenantController
        return true;
    }
}
