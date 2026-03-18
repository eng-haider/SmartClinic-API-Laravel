<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

class SeedAllTenantsRolesAndPermissions extends Seeder
{
    /**
     * Run the RoleAndPermissionSeeder for all tenant databases.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔧 SEED ROLES & PERMISSIONS FOR ALL TENANTS');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found.');
            return;
        }

        $this->command->info("Found {$tenants->count()} tenant(s) to process.");
        $this->command->info('');

        $processed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("┌─ Tenant: {$tenant->id}");

            try {
                $tenant->run(function () {
                    // Run the RoleAndPermissionSeeder within tenant context
                    $seeder = new RoleAndPermissionSeeder();
                    $seeder->setCommand($this->command);
                    $seeder->run();
                });
                
                $this->command->info("└─ ✅ Done: {$tenant->id}");
                $processed++;
            } catch (\Exception $e) {
                $this->command->error("└─ ❌ Failed: {$tenant->id}");
                $this->command->error("   Error: " . $e->getMessage());
                $failed++;
            }

            $this->command->info('');
        }

        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("✅ Summary: {$processed} succeeded, {$failed} failed");
        $this->command->info('');
    }
}
