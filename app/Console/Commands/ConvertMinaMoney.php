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
 */
class ConvertMinaMoney extends Command
{
    protected $signature = 'money:mina
                            {--apply : Change the data; without it only a report is printed}
                            {--restore= : Put back the amounts saved in this backup file}';

    protected $description = 'Clinic _mina only: divide doctor #8\'s amounts from before 2025 by 1000';

    public function handle(): int
    {
        if ($this->option('restore')) {
            return $this->call('money:thousands', ['tenant' => '_mina', '--restore' => $this->option('restore')]);
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
