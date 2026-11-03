<?php

namespace App\Console\Commands;

use App\Models\CaseModel;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For a clinic whose doctor switched from typing full dinars (25000) to thousands (25):
 * divides the amounts typed before the switch by 1000, so old and new records agree.
 *
 * Every money column is converted together. Converting bills alone would leave each
 * case at 25000 with 25 paid, owing 24975.
 *
 * Only amounts of 1000 or more change, so the doctor's new entries (25) are left alone.
 * --before limits it to records typed before the switch, which also protects new
 * large amounts (2500 = 2.5M).
 *
 * It must run once per clinic: an amount of a million or more is still 1000+ after
 * converting (2,500,000 -> 2500) and a second pass would divide it again. So it
 * refuses while the clinic has a conversion backup, unless --again.
 *
 * Usage:
 *   php artisan money:thousands _clinic                               # report only, changes nothing
 *   php artisan money:thousands _clinic --before=2026-10-15           # report for records before that day
 *   php artisan money:thousands _clinic --before=2026-10-15 --apply   # convert (saves a backup first)
 *   php artisan money:thousands _clinic --doctor=8 --before=2025-01-01 # one doctor's cases dated before 2025
 *   php artisan money:thousands _clinic --restore=storage/app/money-backups/....json
 *
 * With --doctor, only that doctor's cases (by case date) and every payment on them
 * change, whatever the payment's date or who took it, so each case stays in one unit.
 * The clinic's other amounts (expenses, credit, stock) are left alone.
 *
 * Amounts that aren't exact thousands (12,500) block the conversion unless --round is
 * given, which rounds them to the nearest thousand (12,500 -> 13).
 */
class ConvertMoneyToThousands extends Command
{
    protected $signature = 'money:thousands
                            {tenant : The clinic (tenant ID) to convert}
                            {--before= : Only records created before this date or time ("YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS"), when the doctor started typing in thousands}
                            {--doctor= : Only this doctor\'s cases (dated before --before) and every payment on them}
                            {--skip= : Amounts to leave alone, comma separated (e.g. warehouse_items.cost_price)}
                            {--round : Round amounts that are not exact thousands to the nearest thousand (12,500 -> 13)}
                            {--keep= : Rows to leave alone, comma separated table:id (e.g. bills:40801, already typed in thousands)}
                            {--again : Run although this clinic was converted before (only for a different doctor or date)}
                            {--apply : Change the data; without it only a report is printed}
                            {--restore= : Put back the amounts saved in this backup file}';

    protected $description = 'Divide a clinic\'s old amounts by 1000 after its doctor switched to typing prices in thousands';

    /**
     * [table, column, date column for --before (null: not dated), decimal places].
     * Cases come before bills: a bill update fires the trigger that compares the
     * case's paid total with its price, so the price must already be converted.
     */
    private const COLUMNS = [
        ['cases', 'price', 'created_at', 0],
        ['cases', 'item_cost', 'created_at', 0],
        ['bills', 'price', 'created_at', 0],
        ['clinic_expenses', 'price', 'created_at', 2],
        ['case_categories', 'item_cost', null, 0],
        ['patients', 'credit_balance', null, 0],
        ['warehouse_items', 'cost_price', null, 2],
        ['warehouse_transactions', 'unit_cost', 'created_at', 2],
        ['case_warehouse_item', 'unit_cost', 'created_at', 2],
    ];

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error("No clinic with ID {$this->argument('tenant')}.");

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            return $this->option('restore')
                ? $this->restore($tenant, $this->option('restore'))
                : $this->convert($tenant);
        } finally {
            tenancy()->end();
        }
    }

    private function convert(Tenant $tenant): int
    {
        $before = $this->option('before');

        if ($before !== null && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $before)) {
            $this->error('--before must be a date like 2026-10-15, or with a time: "2026-10-15 19:14:09".');

            return self::FAILURE;
        }

        $doctor = $this->option('doctor');
        $doctorCases = null;

        if ($doctor !== null) {
            if (!ctype_digit((string) $doctor)) {
                $this->error('--doctor must be the doctor\'s user ID, like 8.');

                return self::FAILURE;
            }

            // The case's own date: created_at can be when old records were imported
            $caseDate = Schema::hasColumn('cases', 'case_date') ? 'COALESCE(case_date, created_at)' : 'created_at';
            $doctorCases = DB::table('cases')->select('id')->where('doctor_id', (int) $doctor)
                ->when($before !== null, fn ($q) => $q->whereRaw("{$caseDate} < ?", [$before]));
        }

        $this->info("Clinic: {$tenant->id} ({$tenant->name})");

        $previous = glob(storage_path("app/money-backups/{$tenant->id}-*.json")) ?: [];
        if ($previous && !$this->option('again')) {
            $this->error('This clinic was already converted (' . basename(end($previous)) . ').');
            $this->line('Running it again would divide amounts of a million or more a second time (2500 -> 3).');
            $this->line('Use --again only for a different doctor or date that was not converted.');

            return self::FAILURE;
        }

        if ($doctorCases) {
            $this->line("Doctor #{$doctor}: cases dated " . ($before ? "before {$before}" : 'any time') . ' and every payment on them, amounts of 1000 or more.');
            $this->line('The clinic\'s other amounts (expenses, credit, stock) are left alone.');
        } else {
            $this->line($before ? "Records created before {$before}, amounts of 1000 or more." : 'All amounts of 1000 or more (no --before date given).');
        }
        $this->newLine();

        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));
        $round = (bool) $this->option('round');
        $keep = array_filter(array_map('trim', explode(',', (string) $this->option('keep'))));
        $rows = [];
        $changes = [];
        $inexact = [];
        $rounded = 0;
        $firstSmall = null;

        foreach (self::COLUMNS as [$table, $column, $dateColumn, $decimals]) {
            if (in_array("{$table}.{$column}", $skip, true) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            if ($doctorCases && !in_array($table, ['cases', 'bills'], true)) {
                continue;
            }

            $select = array_filter(['id', $column, $dateColumn]);
            if ($table === 'bills') {
                $select = [...$select, 'billable_type', 'billable_id'];
            }
            $found = $this->candidates($table, $column, $dateColumn, $before, $doctorCases)->get($select)
                ->reject(fn ($row) => in_array("{$table}:{$row->id}", $keep, true))
                ->values();

            // Whole-number columns need an exact thousand; 2-decimal columns a multiple of 10
            $step = $decimals === 0 ? 1000 : 10;
            $bad = $found->filter(fn ($row) => fmod((float) $row->{$column}, $step) != 0);

            $small = $this->scope($table, $doctorCases)->whereRaw("ABS({$column}) BETWEEN 1 AND 999");
            $smallCount = (clone $small)->count();

            // The doctor's own entries show when the switch happened; stock costs can be small anyway
            if (!$doctorCases && $column === 'price' && in_array($table, ['cases', 'bills'], true) && $smallCount) {
                $first = (clone $small)->min($dateColumn);
                $firstSmall = $firstSmall === null || $first < $firstSmall ? $first : $firstSmall;
            }

            $changes["{$table}.{$column}"] = $this->newValues($table, $column, $decimals, $found);

            $rows[] = [
                "{$table}.{$column}",
                $found->count(),
                $bad->count(),
                $smallCount,
                number_format((float) $found->sum($column)),
                number_format(array_sum(array_column($changes["{$table}.{$column}"], 'new')), $decimals),
            ];

            if ($round) {
                $rounded += $bad->count();
            } else {
                foreach ($bad->take(10) as $row) {
                    $inexact[] = "{$table}.{$column} #{$row->id} = {$row->{$column}}" . ($dateColumn ? " (typed {$row->{$dateColumn}})" : '');
                }
            }
        }

        $this->table(['Amount', 'Will change', 'Not exact', 'Already small (1-999)', 'Total now', 'Total after'], $rows);

        if ($keep) {
            $this->line('Left alone: ' . implode(', ', $keep));
        }

        if ($firstSmall) {
            $this->line("The first small amount (1-999) was typed on {$firstSmall}. That is probably when the doctor switched; use it as --before.");
        }

        $total = array_sum(array_map('count', $changes));

        if ($inexact) {
            $this->newLine();
            $this->error('Some amounts are not exact thousands, so dividing them would lose money (12,500 -> 12.5 does not fit). Nothing can be applied until they are fixed:');
            foreach ($inexact as $line) {
                $this->line("  {$line}");
            }
            if ($before === null) {
                $this->line('If these were typed after the switch (2500 meaning 2.5 million), add --before=<switch date> to leave them out.');
            }
            $this->line('Or add --round to round them to the nearest thousand (12,500 -> 13).');

            return self::FAILURE;
        }

        if ($rounded) {
            $this->line("{$rounded} amount(s) are not exact thousands and will be rounded to the nearest thousand (12,500 -> 13).");
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->info("Report only. {$total} amount(s) would change. Add --apply to convert them.");

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Nothing to convert.');

            return self::SUCCESS;
        }

        // Backup first: if it can't be written, nothing is changed
        $backup = storage_path('app/money-backups/' . $tenant->id . '-' . now()->format('Ymd-His') . '.json');
        if (!is_dir(dirname($backup))) {
            mkdir(dirname($backup), 0755, true);
        }
        file_put_contents($backup, json_encode(['tenant' => $tenant->id, 'doctor' => $doctor, 'before' => $before, 'changes' => $changes], JSON_PRETTY_PRINT), LOCK_EX);

        DB::transaction(function () use ($changes) {
            foreach ($changes as $key => $list) {
                [$table, $column] = explode('.', $key);

                // Exact ones in bulk; rounded ones one by one with their own value
                $exact = array_filter($list, fn ($change) => abs($change['new'] * 1000 - $change['old']) < 0.001);
                foreach (array_chunk(array_column($exact, 'id'), 500) as $ids) {
                    DB::table($table)->whereIn('id', $ids)->update([$column => DB::raw("{$column} / 1000")]);
                }
                foreach (array_diff_key($list, $exact) as $change) {
                    DB::table($table)->where('id', $change['id'])->update([$column => $change['new']]);
                }
            }
        });

        $this->syncPaid($doctorCases);

        $this->info("Converted {$total} amount(s).");
        $this->line("Backup: {$backup}");
        $this->line("To undo: php artisan money:thousands {$tenant->id} --restore={$backup}");

        return self::SUCCESS;
    }

    private function restore(Tenant $tenant, string $file): int
    {
        $backup = is_file($file) ? json_decode(file_get_contents($file), true) : null;

        if (!$backup || ($backup['tenant'] ?? null) !== $tenant->id) {
            $this->error("{$file} is not a backup of clinic {$tenant->id}.");

            return self::FAILURE;
        }

        $count = 0;
        DB::transaction(function () use ($backup, &$count) {
            // Same order as the conversion, cases before bills
            foreach ($backup['changes'] as $key => $list) {
                [$table, $column] = explode('.', $key);
                foreach ($list as $row) {
                    DB::table($table)->where('id', $row['id'])->update([$column => $row['old']]);
                    $count++;
                }
            }
        });

        $this->syncPaid();

        // Undone, so it no longer counts as a conversion of this clinic
        rename($file, "{$file}.restored");
        $this->info("Restored {$count} amount(s) from {$file}.");

        return self::SUCCESS;
    }

    /**
     * Each amount divided by 1000, rounded when it doesn't divide exactly (only reached
     * with --round). A case's payments are rounded on their running total, so they still
     * add up to the rounded total: 12,500 + 12,500 become 13 + 12, not 13 + 13.
     *
     * @return list<array{id: int, old: mixed, new: int|float}>
     */
    private function newValues(string $table, string $column, int $decimals, Collection $found): array
    {
        $divide = fn (float $amount) => $decimals === 0 ? (int) round($amount / 1000) : round($amount / 1000, 2);

        if ($table !== 'bills') {
            return $found->map(fn ($row) => ['id' => $row->id, 'old' => $row->{$column}, 'new' => $divide((float) $row->{$column})])->values()->all();
        }

        $changes = [];
        $payments = $found->sortBy('id')->groupBy(fn ($row) => $row->billable_id ? "{$row->billable_type}:{$row->billable_id}" : "bill:{$row->id}");

        foreach ($payments as $bills) {
            $sum = 0.0;
            foreach ($bills as $row) {
                $before = $divide($sum);
                $sum += (float) $row->{$column};
                $changes[] = ['id' => $row->id, 'old' => $row->{$column}, 'new' => $divide($sum) - $before];
            }
        }

        return $changes;
    }

    private function candidates(string $table, string $column, ?string $dateColumn, ?string $before, $doctorCases)
    {
        // ABS: a patient's credit balance can be negative
        $query = $this->scope($table, $doctorCases)->whereRaw("ABS({$column}) >= 1000");

        // A doctor's cases are already picked by date; their payments go with them whatever their date
        if (!$doctorCases && $before !== null && $dateColumn !== null) {
            $query->where($dateColumn, '<', $before);
        }

        return $query;
    }

    /**
     * The rows a run may touch: the whole table, or with --doctor, the doctor's cases and their payments.
     */
    private function scope(string $table, $doctorCases)
    {
        $query = DB::table($table);

        if ($doctorCases) {
            match ($table) {
                'cases' => $query->whereIn('id', $doctorCases),
                'bills' => $query->whereIn('billable_type', CaseModel::BILLABLE_TYPES)->whereIn('billable_id', $doctorCases),
            };
        }

        return $query;
    }

    /**
     * Prices and payments changed, so recheck which cases are fully paid.
     */
    private function syncPaid($onlyCases = null): void
    {
        CaseModel::withTrashed()
            ->when($onlyCases, fn ($q) => $q->whereIn('id', $onlyCases))
            ->chunkById(200, fn ($cases) => $cases->each->syncIsPaid());
    }
}
