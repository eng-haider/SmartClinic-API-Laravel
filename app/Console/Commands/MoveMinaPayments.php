<?php

namespace App\Console\Commands;

use App\Models\CaseModel;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-off for clinic _mina, doctor #8: some patients' instalments were saved on later
 * visits priced 0, so the treatment they were for still shows as unpaid. This puts those
 * payments on the treatment. Only the payment's case changes: its amount, its date and
 * who took it stay.
 *
 * A patient's payments move only when there is no doubt:
 *  - exactly one of their cases with this doctor is unpaid,
 *  - everything paid on their visits priced 0 adds up to exactly that debt,
 *  - none of those payments is older than the unpaid case, and
 *  - none of their cases is overpaid or has a broken price (1-14).
 * The other patients are listed for the clinic to check.
 *
 * Run it after money:mina --repair --apply: the debts are wrong before that.
 *
 * Usage:
 *   php artisan mina:move-payments                  # report only, changes nothing
 *   php artisan mina:move-payments --skip=259,292   # leave these patients alone
 *   php artisan mina:move-payments --apply          # move (saves an undo file first)
 *   php artisan mina:move-payments --undo           # put the last move back
 */
class MoveMinaPayments extends Command
{
    private const TENANT = '_mina';

    private const DOCTOR = 8;

    protected $signature = 'mina:move-payments
                            {--apply : Move the payments; without it only a report is printed}
                            {--skip= : Patient IDs to leave alone, comma separated}
                            {--undo= : Put back the payments a run moved, from its undo file or else the last one}';

    protected $description = 'Clinic _mina only: move doctor #8\'s payments from visits priced 0 to the treatment they paid for';

    public function handle(): int
    {
        $tenant = Tenant::find(self::TENANT);

        if (!$tenant) {
            $this->error('No clinic with ID ' . self::TENANT . '.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            // Not option(): it is null for an --undo without a file, which must never move
            return $this->input->hasParameterOption('--undo') ? $this->undo() : $this->move();
        } finally {
            tenancy()->end();
        }
    }

    private function move(): int
    {
        if ($this->repairPending()) {
            $this->error('Some prices are still broken. Run this first: php artisan money:mina --repair --apply');

            return self::FAILURE;
        }

        $skip = array_map('intval', array_filter(explode(',', (string) $this->option('skip'))));
        $patients = $this->patients()->map(fn ($patient) => in_array($patient['id'], $skip, true)
            ? ['doubt' => 'left out with --skip'] + $patient
            : $patient);
        $sure = $patients->whereNull('doubt');
        $moves = $sure->flatMap(fn ($patient) => $patient['payments']->map(fn ($payment) => [
            'patient' => $patient['id'],
            'name' => $patient['name'],
            'payment' => (int) $payment->id,
            'paid_on' => $payment->paid_on,
            'amount' => (int) $payment->price,
            'from' => (int) $payment->billable_id,
            'to' => (int) $patient['owed']->first()->id,
        ]))->values();

        $this->info('Payments to move from visits priced 0 to the unpaid case:');
        $this->table(
            ['Patient', 'Name', 'Payment', 'Paid on', 'Amount', 'From case', 'To case'],
            $moves->map(fn ($move) => [$move['patient'], $move['name'], $move['payment'], $move['paid_on'], number_format($move['amount']), $move['from'], $move['to']])
        );
        $this->line("{$sure->count()} patient(s), {$moves->count()} payment(s), " . number_format($moves->sum('amount')) . ' in total.');

        $doubtful = $patients->whereNotNull('doubt');
        if ($doubtful->isNotEmpty()) {
            $this->newLine();
            $this->info('Not moved, for the clinic to check:');
            $this->table(
                ['Patient', 'Name', 'Paid on visits priced 0', 'Owes', 'Why'],
                $doubtful->map(fn ($patient) => [$patient['id'], $patient['name'], number_format($patient['payments']->sum('price')), number_format($patient['debt']), $patient['doubt']])
            );
        }

        if ($moves->isEmpty()) {
            $this->info('Nothing to move.');

            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->info('Report only. Add --apply to move them.');

            return self::SUCCESS;
        }

        $file = $this->apply($moves);

        $this->info("Moved {$moves->count()} payment(s).");
        $this->line("To undo: php artisan mina:move-payments --undo={$file}");

        return self::SUCCESS;
    }

    /**
     * Everyone who paid this doctor on visits priced 0 while a case with them is unpaid.
     * 'doubt' says why their payments can't be moved; null when they can.
     */
    private function patients(): Collection
    {
        // The same payments and cases the Bills page counts
        $payments = DB::table('bills')
            ->join('cases', 'cases.id', '=', 'bills.billable_id')
            ->whereIn('bills.billable_type', CaseModel::BILLABLE_TYPES)
            ->where('bills.is_paid', true)
            ->where('bills.price', '<>', 0)
            ->whereNull('bills.deleted_at')
            ->whereNull('cases.deleted_at')
            ->where('cases.doctor_id', self::DOCTOR)
            ->selectRaw('bills.id, bills.billable_id, bills.price, DATE(COALESCE(bills.bill_date, bills.created_at)) as paid_on')
            ->orderBy('bills.id')
            ->get()
            ->groupBy('billable_id');

        return DB::table('cases')
            ->join('patients', 'patients.id', '=', 'cases.patient_id')
            ->whereNull('cases.deleted_at')
            ->whereNull('patients.deleted_at')
            ->where('cases.doctor_id', self::DOCTOR)
            ->selectRaw('cases.id, cases.patient_id, patients.name, COALESCE(cases.price, 0) as price, DATE(COALESCE(cases.case_date, cases.created_at)) as opened_on')
            ->orderBy('cases.id')
            ->get()
            ->each(fn ($case) => $case->paid = (int) $payments->get($case->id, collect())->sum('price'))
            ->groupBy('patient_id')
            ->map(function (Collection $cases, $patientId) use ($payments) {
                $onFreeVisits = $cases->where('price', 0)->flatMap(fn ($case) => $payments->get($case->id, collect()))->values();
                $owed = $cases->filter(fn ($case) => $case->price > $case->paid);
                $debt = (int) $owed->sum(fn ($case) => $case->price - $case->paid);

                return [
                    'id' => (int) $patientId,
                    'name' => $cases->first()->name,
                    'payments' => $onFreeVisits,
                    'owed' => $owed,
                    'debt' => $debt,
                    'doubt' => $onFreeVisits->isEmpty() || $owed->isEmpty() ? null : $this->doubt($cases, $onFreeVisits, $owed, $debt),
                ];
            })
            ->reject(fn ($patient) => $patient['payments']->isEmpty() || $patient['owed']->isEmpty())
            ->values();
    }

    private function doubt(Collection $cases, Collection $onFreeVisits, Collection $owed, int $debt): ?string
    {
        $paid = (int) $onFreeVisits->sum('price');

        return match (true) {
            $cases->contains(fn ($case) => $case->price >= 1 && $case->price <= 14) => 'a case has a broken price (1-14)',
            $cases->contains(fn ($case) => $case->price > 0 && $case->paid > $case->price) => 'another case is overpaid',
            $owed->count() > 1 => 'more than one case is unpaid',
            $onFreeVisits->min('paid_on') < $owed->first()->opened_on => 'a payment is older than the unpaid case',
            $paid > $debt => 'paid more than the debt',
            $paid < $debt => 'the payments cover only part of the debt',
            default => null,
        };
    }

    /**
     * All payments move or none does. Returns the undo file, saved before anything changes.
     */
    private function apply(Collection $moves): string
    {
        $cases = $moves->pluck('from')->merge($moves->pluck('to'))->unique()->values();
        $file = storage_path('app/payment-moves/' . self::TENANT . '-' . now()->format('Ymd-His') . '.json');

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        $saved = file_put_contents($file, json_encode([
            'tenant' => self::TENANT,
            'moves' => $moves,
            'is_paid' => DB::table('cases')->whereIn('id', $cases)->pluck('is_paid', 'id'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($saved === false) {
            throw new \RuntimeException("Could not write the undo file {$file}; nothing was moved.");
        }

        try {
            DB::transaction(function () use ($moves, $cases) {
                // Query builder: no model events, so no notifications and no updated_at change
                foreach ($moves as $move) {
                    $moved = DB::table('bills')->where('id', $move['payment'])->where('billable_id', $move['from'])
                        ->where('price', $move['amount'])->whereNull('deleted_at')
                        ->update(['billable_id' => $move['to']]);

                    if ($moved !== 1) {
                        throw new \RuntimeException("Payment {$move['payment']} was changed meanwhile; nothing was moved.");
                    }
                }

                // The treatment is now exactly paid, and the visit owes nothing
                DB::table('cases')->whereIn('id', $cases)->update(['is_paid' => true]);
            });
        } catch (\Throwable $e) {
            unlink($file);

            throw $e;
        }

        return $file;
    }

    private function undo(): int
    {
        $files = glob(storage_path('app/payment-moves/' . self::TENANT . '-*.json')) ?: [];
        $file = $this->option('undo') ?: end($files);
        $saved = $file && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (($saved['tenant'] ?? null) !== self::TENANT) {
            $this->error($file ? "{$file} is not an undo file of this command." : 'There is no move to undo.');

            return self::FAILURE;
        }

        $changed = [];

        DB::transaction(function () use ($saved, &$changed) {
            $flags = $saved['is_paid'];

            foreach ($saved['moves'] as $move) {
                $back = DB::table('bills')->where('id', $move['payment'])->where('billable_id', $move['to'])
                    ->whereNull('deleted_at')->update(['billable_id' => $move['from']]);

                // Moved or deleted since: leave it, and the paid flag of its cases
                if ($back !== 1) {
                    $changed[] = $move['payment'];
                    unset($flags[$move['from']], $flags[$move['to']]);
                }
            }

            foreach ($flags as $case => $isPaid) {
                DB::table('cases')->where('id', $case)->update(['is_paid' => $isPaid]);
            }
        });

        rename($file, "{$file}.undone");

        $this->info('Put back ' . (count($saved['moves']) - count($changed)) . ' payment(s).');
        if ($changed) {
            $this->line('Changed since the move, left alone: payments ' . implode(', ', $changed));
        }

        return self::SUCCESS;
    }

    /**
     * Prices of 1-14 from the double conversion, and no repair on record (see money:mina --repair).
     */
    private function repairPending(): bool
    {
        $broken = DB::table('cases')->where('doctor_id', self::DOCTOR)->whereNull('deleted_at')
            ->whereBetween('price', [1, 14])->exists();

        if (!$broken) {
            return false;
        }

        foreach (glob(storage_path('app/money-backups/' . self::TENANT . '-*.json')) ?: [] as $file) {
            if (isset(json_decode((string) file_get_contents($file), true)['repair'])) {
                return false;
            }
        }

        return true;
    }
}
