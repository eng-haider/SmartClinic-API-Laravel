<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Tenancy;

/**
 * Backfill dental encounter details from existing cases data.
 *
 * This command copies tooth_num and root_stuffing from the cases table
 * into the new dental_encounter_details table for all dental tenants.
 *
 * SAFE TO RUN MULTIPLE TIMES:
 * - Uses updateOrCreate, so it won't duplicate rows
 * - Only processes dental tenants
 * - Only processes cases that have tooth_num or root_stuffing
 *
 * Usage:
 *   php artisan dental:backfill                 # All dental tenants
 *   php artisan dental:backfill --tenant=clinic_1  # Specific tenant
 *   php artisan dental:backfill --dry-run        # Preview only
 */
class BackfillDentalEncounterDetails extends Command
{
    protected $signature = 'dental:backfill
                            {--tenant= : Specific tenant ID to process}
                            {--dry-run : Preview without saving}
                            {--batch=500 : Number of records per batch}';

    protected $description = 'Backfill dental_encounter_details from existing cases.tooth_num/root_stuffing data';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');
        $specificTenant = $this->option('tenant');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE — no data will be saved');
        }

        // Get dental tenants
        $query = \App\Models\Tenant::query();
        if ($specificTenant) {
            $query->where('id', $specificTenant);
        } else {
            $query->where('specialty', 'dental');
        }

        $tenants = $query->get();
        $this->info("Found {$tenants->count()} dental tenant(s) to process.");

        $totalMigrated = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("┌─ Tenant: {$tenant->id}");

            try {
                // Initialize tenancy for this tenant
                tenancy()->initialize($tenant);

                // Check if dental_encounter_details table exists
                if (!DB::connection('tenant')->getSchemaBuilder()->hasTable('dental_encounter_details')) {
                    $this->warn("│  ⚠ Table dental_encounter_details does not exist — skipping");
                    $this->info("│  Run: php artisan tenants:migrate --tenants={$tenant->id}");
                    tenancy()->end();
                    continue;
                }

                // Count cases with dental data
                $totalCases = DB::connection('tenant')
                    ->table('cases')
                    ->where(function ($q) {
                        $q->whereNotNull('tooth_num')
                          ->orWhereNotNull('root_stuffing');
                    })
                    ->count();

                // Count already migrated
                $alreadyMigrated = DB::connection('tenant')
                    ->table('dental_encounter_details')
                    ->count();

                $this->info("│  📊 Cases with dental data: {$totalCases}");
                $this->info("│  📊 Already in detail table: {$alreadyMigrated}");

                if ($totalCases === 0) {
                    $this->info("└─ ✅ Nothing to migrate");
                    tenancy()->end();
                    continue;
                }

                // Process in batches
                $migrated = 0;
                $skipped = 0;

                DB::connection('tenant')
                    ->table('cases')
                    ->where(function ($q) {
                        $q->whereNotNull('tooth_num')
                          ->orWhereNotNull('root_stuffing');
                    })
                    ->orderBy('id')
                    ->chunk($batchSize, function ($cases) use (&$migrated, &$skipped, $isDryRun) {
                        foreach ($cases as $case) {
                            // Check if already exists
                            $exists = DB::connection('tenant')
                                ->table('dental_encounter_details')
                                ->where('case_id', $case->id)
                                ->exists();

                            if ($exists) {
                                $skipped++;
                                continue;
                            }

                            if (!$isDryRun) {
                                DB::connection('tenant')
                                    ->table('dental_encounter_details')
                                    ->insert([
                                        'case_id' => $case->id,
                                        'tooth_num' => $case->tooth_num,
                                        'root_stuffing' => $case->root_stuffing,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                            }

                            $migrated++;
                        }
                    });

                $totalMigrated += $migrated;
                $totalSkipped += $skipped;

                $action = $isDryRun ? 'Would migrate' : 'Migrated';
                $this->info("└─ ✅ {$action}: {$migrated}, Skipped (already exists): {$skipped}");

                tenancy()->end();

            } catch (\Throwable $e) {
                $this->error("└─ ❌ Failed: {$e->getMessage()}");
                try { tenancy()->end(); } catch (\Throwable $ex) {}
            }
        }

        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $action = $isDryRun ? 'Would migrate' : 'Total migrated';
        $this->info("✅ {$action}: {$totalMigrated}, Skipped: {$totalSkipped}");

        return Command::SUCCESS;
    }
}
