<?php

namespace App\Console\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Points the `tenant` connection at one tenant database and connects, for commands that walk
 * every tenant (tenants:migrate, tenants:seed, tenants:list).
 *
 * Hostinger refuses new MySQL connections for a while once an account opens ~20 of them in a
 * short burst, with "SQLSTATE[HY000] [2002] Operation not permitted". Walking every tenant does
 * exactly that (each tenant DB has its own user, so no connection can be reused), so a 2002 is
 * retried after a pause instead of failing that tenant and every tenant after it.
 */
trait ConnectsToTenantDatabase
{
    /** Seconds to wait before each retry after a 2002. */
    protected array $tenantConnectWaits = [10, 30, 60];

    /** Set once a tenant still got a 2002 after every retry, so later tenants fail fast. */
    private bool $tenantConnectRetriesExhausted = false;

    /**
     * @throws \Exception when the tenant database cannot be reached
     */
    protected function connectTenantDatabase(string $dbName, string $username, ?string $password): void
    {
        $central = config('database.connections.central');

        config([
            'database.connections.tenant.database' => $dbName,
            'database.connections.tenant.username' => $username,
            'database.connections.tenant.password' => $password,
            'database.connections.tenant.host' => $central['host'],
            'database.connections.tenant.port' => $central['port'],
        ]);

        $waits = $this->tenantConnectRetriesExhausted ? [] : $this->tenantConnectWaits;

        for ($attempt = 0; ; $attempt++) {
            DB::purge('tenant');

            try {
                DB::connection('tenant')->getPdo();
                return;
            } catch (\Exception $e) {
                if (!str_contains($e->getMessage(), '[2002]')) {
                    throw $e;
                }

                if (!isset($waits[$attempt])) {
                    $this->tenantConnectRetriesExhausted = true;
                    throw $e;
                }

                $this->warn("  … MySQL refused the connection, retrying in {$waits[$attempt]}s (" . ($attempt + 1) . '/' . count($waits) . ')');
                sleep($waits[$attempt]);
            }
        }
    }
}
