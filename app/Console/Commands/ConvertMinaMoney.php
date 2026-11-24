<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * One-off for clinic _mina: doctor #8 typed full dinars until 2024 and thousands
 * since 2025, so his cases before 2025 (and every payment on them) are divided by
 * 1000, rounding half-thousands (12,500 -> 13). See ConvertMoneyToThousands.
 *
 * Usage:
 *   php artisan money:mina                    # report only, changes nothing
 *   php artisan money:mina --apply            # convert (saves a backup first)
 *   php artisan money:mina --restore=<file>   # undo with that backup
 *   php artisan money:mina --repair           # report; with --apply, puts back the amounts converted twice
 *
 * Before the 2026-11-03 run staff had already retyped some old cases in thousands
 * (1,725,000 as 1725), so it turned those into 2. --repair puts them back.
 */
class ConvertMinaMoney extends Command
{
    protected $signature = 'money:mina
                            {--apply : Change the data; without it only a report is printed}
                            {--restore= : Put back the amounts saved in this backup file}
                            {--repair= : Put back only the amounts that were converted twice, from this backup file or else the one the conversion saved}';

    protected $description = 'Clinic _mina only: divide doctor #8\'s amounts from before 2025 by 1000';

    public function handle(): int
    {
        // Not option(): it is null for a --repair without a file, which must never convert
        if ($this->input->hasParameterOption('--restore')) {
            return $this->call('money:thousands', ['tenant' => '_mina', '--restore' => $this->option('restore')]);
        }

        if ($this->input->hasParameterOption('--repair')) {
            return $this->call('money:thousands', [
                'tenant' => '_mina',
                '--repair' => $this->option('repair'),
                // Payments that really were 10,000 and 5,000 dinars: 10 and 5 are right
                '--keep' => 'bills:10005,bills:22653',
                '--apply' => $this->option('apply'),
            ]);
        }

        return $this->call('money:thousands', array_filter([
            'tenant' => '_mina',
            '--doctor' => '8',
            '--before' => '2025-01-01',
            '--round' => true,
            // A payment on an old case typed in thousands on 2026-09-03: 1300 is 1.3 million
            '--keep' => 'bills:40801',
            '--apply' => $this->option('apply'),
        ]));
    }
}
