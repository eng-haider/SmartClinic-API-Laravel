<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class TransferBillDateSeeder extends Seeder
{
    /**
     * Run the database seeds on all tenant databases.
     */
    public function run(): void
    {
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔄 TRANSFER BILL DATE FROM CREATED_AT');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');

        // Get all tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found.');
            return;
        }

        $this->command->info("Found {$tenants->count()} tenant(s) to process.");
        $this->command->info('');

        $processed = 0;
        $failed = 0;
        $totalUpdated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("┌─ Tenant: {$tenant->id}");

            try {
                $updated = $this->processTenant($tenant);
                $totalUpdated += $updated;
                
                $this->command->info("│  └─ ✅ Updated {$updated} bill(s)");
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
        $this->command->info("📊 Total bills updated: {$totalUpdated}");
        $this->command->info('');
    }

    private function processTenant($tenant): int
    {
        return $tenant->run(function () {
            // Check if bill_date column exists
            if (!DB::getSchemaBuilder()->hasColumn('bills', 'bill_date')) {
                $this->command->info("│  ⚠ bill_date column not found in this tenant");
                return 0;
            }

            // Get all bills where bill_date is null
            $billsToUpdate = DB::table('bills')
                ->whereNull('bill_date')
                ->select('id', 'created_at')
                ->get();

            if ($billsToUpdate->isEmpty()) {
                $this->command->info("│  ✓ All bills already have bill_date set.");
                return 0;
            }

            $this->command->info("│  Found {$billsToUpdate->count()} bill(s) to update.");

            $updated = 0;

            foreach ($billsToUpdate as $bill) {
                // Transfer created_at datetime to bill_date
                $billDateTime = $bill->created_at;
                
                DB::table('bills')
                    ->where('id', $bill->id)
                    ->update(['bill_date' => $billDateTime]);

                $this->command->info("│  ✓ Bill ID {$bill->id}: {$bill->created_at} → {$billDateTime}");
                $updated++;
            }

            return $updated;
        });
    }
}
