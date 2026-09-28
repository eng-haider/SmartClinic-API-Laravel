<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class TransferCaseDateSeeder extends Seeder
{
    /**
     * Run the database seeds on all tenant databases.
     */
    public function run(): void
    {
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔄 TRANSFER CASE DATE FROM CREATED_AT');
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
                
                $this->command->info("│  └─ ✅ Updated {$updated} case(s)");
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
        $this->command->info("📊 Total cases updated: {$totalUpdated}");
        $this->command->info('');
    }

    private function processTenant($tenant): int
    {
        return $tenant->run(function () {
            // Check if case_date column exists
            if (!DB::getSchemaBuilder()->hasColumn('cases', 'case_date')) {
                $this->command->info("│  ⚠ case_date column not found in this tenant");
                return 0;
            }

            // Get all cases where case_date is null
            $casesToUpdate = DB::table('cases')
                ->whereNull('case_date')
                ->select('id', 'created_at')
                ->get();

            if ($casesToUpdate->isEmpty()) {
                $this->command->info("│  ✓ All cases already have case_date set.");
                return 0;
            }

            $this->command->info("│  Found {$casesToUpdate->count()} case(s) to update.");

            $updated = 0;

            foreach ($casesToUpdate as $case) {
                // Transfer created_at date to case_date (format as date only)
                $caseDate = date('Y-m-d', strtotime($case->created_at));
                
                DB::table('cases')
                    ->where('id', $case->id)
                    ->update(['case_date' => $caseDate]);

                $this->command->info("│  ✓ Case ID {$case->id}: {$case->created_at} → {$caseDate}");
                $updated++;
            }

            return $updated;
        });
    }
}
