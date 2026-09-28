<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Add the default WhatsApp message templates to every existing clinic.
 *
 *   php artisan db:seed --class=SeedAllTenantsMessageTemplates
 *
 * (Equivalent to `php artisan tenants:seed --class=MessageTemplatesSeeder`,
 * kept as a seeder for hosts where only db:seed is available.)
 */
class SeedAllTenantsMessageTemplates extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('💬 SEED WHATSAPP MESSAGE TEMPLATES FOR ALL TENANTS');
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
                    $seeder = new MessageTemplatesSeeder();
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
