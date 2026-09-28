<?php

namespace Database\Seeders;

use App\Models\DatabasePool;
use Illuminate\Database\Seeder;

/**
 * DatabasePoolSeeder
 *
 * Adds pre-created Hostinger databases to the pool.
 * Run after you manually create new databases in hPanel:
 *
 *   php artisan db:seed --class=DatabasePoolSeeder
 *
 * Already-existing entries (matched by db_name) are skipped,
 * so it is safe to re-run after adding new databases.
 *
 * ──────────────────────────────────────────────────────────────
 * HOW TO ADD MORE DATABASES:
 *   1. In Hostinger hPanel → Databases → MySQL Databases:
 *      - Create database:  u876784197_tenant_XX
 *      - Create user:      u876784197_tenant_XX
 *      - Password:         9!iSeEys:6sO
 *      - Assign user → All Privileges
 *   2. Add another entry to the $databases array below.
 *   3. Run:  php artisan db:seed --class=DatabasePoolSeeder
 * ──────────────────────────────────────────────────────────────
 */
class DatabasePoolSeeder extends Seeder
{
    /**
     * All manually pre-created Hostinger databases.
     * Add new rows here whenever you create more databases in hPanel.
     */
    private array $databases = [
        ['db_name' => 'u876784197_tenant_01', 'db_username' => 'u876784197_tenant_01'],
        ['db_name' => 'u876784197_tenant_02', 'db_username' => 'u876784197_tenant_02'],
        ['db_name' => 'u876784197_tenant_03', 'db_username' => 'u876784197_tenant_03'],
        ['db_name' => 'u876784197_tenant_04', 'db_username' => 'u876784197_tenant_04'],
        ['db_name' => 'u876784197_tenant_05', 'db_username' => 'u876784197_tenant_05'],
        ['db_name' => 'u876784197_tenant_06', 'db_username' => 'u876784197_tenant_06'],
        ['db_name' => 'u876784197_tenant_07', 'db_username' => 'u876784197_tenant_07'],
        ['db_name' => 'u876784197_tenant_08', 'db_username' => 'u876784197_tenant_08'],
        ['db_name' => 'u876784197_tenant_09', 'db_username' => 'u876784197_tenant_09'],
        ['db_name' => 'u876784197_tenant_10', 'db_username' => 'u876784197_tenant_10'],
        // Add more databases here as you create them in hPanel:
        // ['db_name' => 'u876784197_tenant_11', 'db_username' => 'u876784197_tenant_11'],
    ];

    /** Shared password for all tenant databases (set in Hostinger hPanel). */
    private string $password = '9!iSeEys:6sO';

    public function run(): void
    {
        $added = 0;
        $skipped = 0;

        foreach ($this->databases as $db) {
            $exists = DatabasePool::where('db_name', $db['db_name'])->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            DatabasePool::create([
                'db_name'     => $db['db_name'],
                'db_username' => $db['db_username'],
                'db_password' => $this->password,
                'status'      => 'available',
            ]);

            $added++;
        }

        $available = DatabasePool::availableCount();

        $this->command->info("Database pool: {$added} added, {$skipped} skipped. Total available: {$available}");
    }
}
