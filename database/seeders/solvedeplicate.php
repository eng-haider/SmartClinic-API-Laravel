<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

/**
 * FixDuplicatePatientsSeeder
 *
 * PROBLEM:
 *   After 2025-12-24, some patients were re-entered into the system,
 *   creating duplicates of patients that already existed before that date.
 *   Both the old and new records may have cases/bills attached to them.
 *
 * FIX:
 *   For each tenant DB:
 *     1. Find duplicate patient pairs (matched by PATIENT NAME)
 *        — one record created BEFORE 2025-12-25 (the OLD record)
 *        — one record created ON OR AFTER 2025-12-25 (the NEW record)
 *     2. Transfer all related data from OLD patient → NEW patient:
 *           cases, bills, reservations, recipes,
 *           polymorphic notes (noteable), polymorphic images (imageable)
 *     3. Soft-delete the OLD patient record (sets deleted_at).
 *
 * USAGE:
 *   php artisan db:seed --class=FixDuplicatePatientsSeeder
 *
 *   Dry-run (no DB changes, only reports):
 *   Set DRY_RUN = true below before running.
 */
class FixDuplicatePatientsSeeder extends Seeder
{
    // ──────────────────────────────────────────────────────────────────────────
    // CONFIGURATION
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * When true, no changes are written – just prints what would happen.
     */
    private bool $dryRun = false;

    /**
     * Patients created BEFORE this date are considered "old" duplicates.
     * Patients created ON OR AFTER this date are the "new" canonical records.
     */
    private string $cutoffDate = '2025-12-25';

    /**
     * FOR LOCAL TESTING: Set a database name to connect directly to that DB.
     * Leave empty '' to use the tenant system.
     *
     * Example: 'mina_last' — connects directly to mina_last database (bypasses tenancy)
     */
    private string $directDatabaseName = 'mina_last';

    /**
     * Which tenant IDs to process. Leave empty [] to process ALL tenants.
     * (Only used if $directDatabaseName is empty)
     *
     * Example: ['saffat', 'mina_last']
     */
    private array $onlyTenants = [];

    // ──────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔧 FIX DUPLICATE PATIENTS SEEDER');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("   Cutoff date : {$this->cutoffDate}");
        $this->command->info("   Mode        : " . ($this->dryRun ? '🟡 DRY RUN (no changes)' : '🟢 LIVE'));
        $this->command->info('');

        // If direct database is specified, use that instead of tenant system
        if (!empty($this->directDatabaseName)) {
            $this->command->info("📦 Mode: Direct database connection");
            $this->command->info("   Database: {$this->directDatabaseName}");
            $this->command->info('');

            $this->processDirectDatabase();

            $this->command->info('');
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info('✅ All tenants processed.');
            if ($this->dryRun) {
                $this->command->warn('   ⚠  DRY RUN — no data was changed.');
            }
            $this->command->info('');
            return;
        }

        // Otherwise, use tenant system
        $this->command->info("📦 Mode: Tenant system");
        $this->command->info('');

        $tenants = empty($this->onlyTenants)
            ? Tenant::all()
            : Tenant::whereIn('id', $this->onlyTenants)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found.');
            return;
        }

        $this->command->info("Found {$tenants->count()} tenant(s) to process.");
        $this->command->info('');

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('✅ All tenants processed.');
        if ($this->dryRun) {
            $this->command->warn('   ⚠  DRY RUN — no data was changed.');
        }
        $this->command->info('');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function processTenant(Tenant $tenant): void
    {
        $this->command->info("┌─ Tenant: {$tenant->id}");

        try {
            $tenant->run(function () use ($tenant) {
                $this->fixDuplicates();
            });
            $this->command->info("└─ Done: {$tenant->id}");
        } catch (\Exception $e) {
            $this->command->error("└─ ❌ Skipped tenant {$tenant->id}: " . $e->getMessage());
        }

        $this->command->info('');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function processDirectDatabase(): void
    {
        $connName = 'direct_fix_connection';

        try {
            // Register direct connection to the specified database
            $baseConfig = config('database.connections.mysql');
            $baseConfig['database'] = $this->directDatabaseName;
            config(["database.connections.{$connName}" => $baseConfig]);

            // Test connection
            DB::connection($connName)->statement('SELECT 1');

            $this->command->info("┌─ Database: {$this->directDatabaseName}");

            // Use the direct connection for the fix
            DB::setDefaultConnection($connName);
            $this->fixDuplicates();

            $this->command->info("└─ Done: {$this->directDatabaseName}");
        } catch (\Exception $e) {
            $this->command->error("└─ ❌ Error processing {$this->directDatabaseName}: " . $e->getMessage());
        }

        $this->command->info('');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function fixDuplicates(): void
    {
        // Find patient names that appear in BOTH old and new records.
        // "old" = created before cutoff, "new" = created on/after cutoff.
        $duplicateNames = DB::table('patients as p_new')
            ->join('patients as p_old', 'p_old.name', '=', 'p_new.name')
            ->whereNull('p_new.deleted_at')
            ->whereNull('p_old.deleted_at')
            ->where('p_new.created_at', '>=', $this->cutoffDate)
            ->where('p_old.created_at', '<', $this->cutoffDate)
            ->whereColumn('p_new.id', '!=', 'p_old.id')
            ->select(
                'p_new.id   as new_id',
                'p_new.name as new_name',
                'p_new.phone as new_phone',
                'p_new.created_at as new_created',
                'p_old.id   as old_id',
                'p_old.name as old_name',
                'p_old.phone as old_phone',
                'p_old.created_at as old_created'
            )
            ->get();

        if ($duplicateNames->isEmpty()) {
            $this->command->info("│  ✓ No duplicate patients found.");
            return;
        }

        $this->command->info("│  Found {$duplicateNames->count()} duplicate pair(s).");
        $this->command->info('│');

        $merged   = 0;
        $skipped  = 0;

        foreach ($duplicateNames as $pair) {
            $oldId = $pair->old_id;
            $newId = $pair->new_id;

            $this->command->info("│  ┌ Pair: name=\"{$pair->old_name}\"");
            $this->command->info("│  │  OLD patient  id={$oldId}  name=\"{$pair->old_name}\"  phone={$pair->old_phone}  created={$pair->old_created}");
            $this->command->info("│  │  NEW patient  id={$newId}  name=\"{$pair->new_name}\"  phone={$pair->new_phone}  created={$pair->new_created}");

            // Count related records to transfer
            $casesCount        = DB::table('cases')->whereNull('deleted_at')->where('patient_id', $oldId)->count();
            $billsCount        = DB::table('bills')->whereNull('deleted_at')->where('patient_id', $oldId)->count();
            $reservationsCount = DB::table('reservations')->whereNull('deleted_at')->where('patient_id', $oldId)->count();
            $recipesCount      = DB::table('recipes')->whereNull('deleted_at')->where('patient_id', $oldId)->count();
            $notesCount        = DB::table('notes')
                ->whereNull('deleted_at')
                ->where('noteable_type', 'App\\Models\\Patient')
                ->where('noteable_id', $oldId)
                ->count();
            $imagesCount       = DB::table('images')
                ->whereNull('deleted_at')
                ->where('imageable_type', 'App\\Models\\Patient')
                ->where('imageable_id', $oldId)
                ->count();

            $this->command->info("│  │  Transfer: cases={$casesCount}, bills={$billsCount}, reservations={$reservationsCount}, recipes={$recipesCount}, notes={$notesCount}, images={$imagesCount}");

            if ($this->dryRun) {
                $this->command->info("│  └ [DRY RUN] Skipped — no changes made.");
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($oldId, $newId) {
                $now = now()->toDateTimeString();

                // Transfer cases
                DB::table('cases')
                    ->whereNull('deleted_at')
                    ->where('patient_id', $oldId)
                    ->update(['patient_id' => $newId, 'updated_at' => $now]);

                // Transfer bills
                DB::table('bills')
                    ->whereNull('deleted_at')
                    ->where('patient_id', $oldId)
                    ->update(['patient_id' => $newId, 'updated_at' => $now]);

                // Transfer reservations
                DB::table('reservations')
                    ->whereNull('deleted_at')
                    ->where('patient_id', $oldId)
                    ->update(['patient_id' => $newId, 'updated_at' => $now]);

                // Transfer recipes
                DB::table('recipes')
                    ->whereNull('deleted_at')
                    ->where('patient_id', $oldId)
                    ->update(['patient_id' => $newId, 'updated_at' => $now]);

                // Transfer polymorphic notes
                DB::table('notes')
                    ->whereNull('deleted_at')
                    ->where('noteable_type', 'App\\Models\\Patient')
                    ->where('noteable_id', $oldId)
                    ->update(['noteable_id' => $newId, 'updated_at' => $now]);

                // Transfer polymorphic images
                DB::table('images')
                    ->whereNull('deleted_at')
                    ->where('imageable_type', 'App\\Models\\Patient')
                    ->where('imageable_id', $oldId)
                    ->update(['imageable_id' => $newId, 'updated_at' => $now]);

                // Soft-delete the old patient
                DB::table('patients')
                    ->where('id', $oldId)
                    ->update(['deleted_at' => $now, 'updated_at' => $now]);
            });

            $this->command->info("│  └ ✓ Merged old→new and soft-deleted old patient id={$oldId}.");
            $merged++;
        }

        $this->command->info('│');
        $this->command->info("│  Summary: merged={$merged}, skipped={$skipped} (dry-run).");
    }
}
