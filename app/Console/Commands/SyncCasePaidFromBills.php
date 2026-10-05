<?php

namespace App\Console\Commands;

use App\Models\CaseModel;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mark cases paid whose bills already cover them.
 *
 * Two kinds of cases are left with is_paid = 0 although nothing is owed:
 *  - Paid from the web patient page: the bill was saved, but the follow-up
 *    PUT /cases/{id} was rejected (it lacked patient_id), so the flag was never set.
 *    Bill now keeps the flag in step itself; this repairs the cases paid before that.
 *  - Migrated from the old system, where paying several cases at once could put every
 *    bill on one case: the patient's bills cover all their cases, but some case keeps 0.
 *
 * So a case is marked paid when its own bills cover its price, or when the patient's
 * bills cover the price of all their cases.
 *
 * SAFE TO RUN MULTIPLE TIMES:
 * - Only ever sets is_paid from 0 to 1, never back.
 * - Deleted cases and deleted bills are ignored.
 * - Updates go through the query builder: no model events, automations or updated_at changes.
 * - Every applied run writes an undo file (storage/logs/cases-sync-paid-*.sql) holding
 *   the UPDATE that puts the changed cases back to unpaid, per tenant database.
 * - A tenant that fails is reported and skipped; the others still run.
 *
 * Usage:
 *   php artisan cases:sync-paid --tenant=clinic_1 --dry-run   # Preview one tenant
 *   php artisan cases:sync-paid --tenant=clinic_1             # Apply to one tenant
 *   php artisan cases:sync-paid --all --dry-run               # Preview every tenant
 *
 * --all has to be asked for: a clinic that never used is_paid would have most of its
 * cases flipped, so check each tenant's preview first.
 */
class SyncCasePaidFromBills extends Command
{
    protected $signature = 'cases:sync-paid
                            {--tenant= : Specific tenant ID to process}
                            {--all : Process every tenant}
                            {--dry-run : Preview without saving}';

    protected $description = 'Mark cases paid when their bills (or the patient\'s bills) already cover the price';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $specificTenant = $this->option('tenant');

        if (!$specificTenant && !$this->option('all')) {
            $this->error('Pass --tenant=<id> for one tenant, or --all for every tenant.');
            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE — no data will be saved');
        }

        $tenants = Tenant::query()
            ->when($specificTenant, fn ($q) => $q->where('id', $specificTenant))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error($specificTenant ? "Tenant '{$specificTenant}' not found." : 'No tenants found.');
            return self::FAILURE;
        }

        // Resolved before tenancy starts, which points storage_path() at the tenant's folder.
        $undoFile = storage_path('logs/cases-sync-paid-' . now()->format('Y-m-d_His') . '.sql');

        $total = 0;
        $failed = [];

        foreach ($tenants as $tenant) {
            try {
                $total += $tenant->run(function () use ($tenant, $isDryRun, $undoFile) {
                    [$coveredByCase, $coveredByPatient] = $this->findCasesToMark();
                    $caseIds = array_values(array_unique(array_merge($coveredByCase, $coveredByPatient)));
                    sort($caseIds);

                    $this->info(sprintf(
                        '%s: %d cases to mark paid (%d covered by their own bills, %d by the patient\'s bills)',
                        $tenant->id,
                        count($caseIds),
                        count($coveredByCase),
                        count(array_diff($coveredByPatient, $coveredByCase))
                    ));

                    if (!$isDryRun && $caseIds) {
                        // Written first, so the undo exists even if the update fails half way.
                        file_put_contents($undoFile, sprintf(
                            "-- %s (database %s)\nUPDATE cases SET is_paid = 0 WHERE id IN (%s);\n",
                            $tenant->id,
                            DB::connection()->getDatabaseName(),
                            implode(',', $caseIds)
                        ), FILE_APPEND);

                        DB::transaction(function () use ($caseIds) {
                            foreach (array_chunk($caseIds, 500) as $chunk) {
                                DB::table('cases')->whereIn('id', $chunk)->update(['is_paid' => 1]);
                            }
                        });
                    }

                    return count($caseIds);
                });
            } catch (\Throwable $e) {
                $failed[] = $tenant->id;
                $this->error("{$tenant->id}: skipped - {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info($isDryRun
            ? "🔍 {$total} cases would be marked paid. Run again without --dry-run to apply."
            : "✅ {$total} cases marked paid.");

        if (!$isDryRun && $total > 0) {
            $this->info("↩️  Undo file: {$undoFile}");
        }

        if ($failed) {
            $this->error('Failed tenants: ' . implode(', ', $failed));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int[], 1: int[]} ids covered by their own bills, ids covered by the patient's bills
     */
    private function findCasesToMark(): array
    {
        $paidPerCase = DB::table('bills')
            ->whereNull('deleted_at')
            ->whereIn('billable_type', CaseModel::BILLABLE_TYPES)
            ->groupBy('billable_id')
            ->select('billable_id', DB::raw('SUM(price) AS paid'));

        $unpaidCases = fn () => DB::table('cases AS c')
            ->whereNull('c.deleted_at')
            ->where('c.is_paid', 0)
            ->where('c.price', '!=', 0);

        $coveredByCase = $unpaidCases()
            ->joinSub($paidPerCase, 'b', 'b.billable_id', '=', 'c.id')
            ->whereColumn('b.paid', '>=', 'c.price')
            ->pluck('c.id')
            ->all();

        $patientTotals = DB::table('cases AS c2')
            ->leftJoinSub($paidPerCase, 'b2', 'b2.billable_id', '=', 'c2.id')
            ->whereNull('c2.deleted_at')
            ->groupBy('c2.patient_id')
            ->select(
                'c2.patient_id',
                DB::raw('COALESCE(SUM(c2.price), 0) AS owed'),
                DB::raw('COALESCE(SUM(b2.paid), 0) AS paid')
            );

        $coveredByPatient = $unpaidCases()
            ->joinSub($patientTotals, 'p', 'p.patient_id', '=', 'c.patient_id')
            ->whereColumn('p.paid', '>=', 'p.owed')
            ->pluck('c.id')
            ->all();

        return [$coveredByCase, $coveredByPatient];
    }
}
