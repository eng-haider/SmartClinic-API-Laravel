<?php

namespace App\Console\Commands;

use App\Repositories\ClinicSettingRepository;

/**
 * Turns the strict_payments setting on or off, for one clinic or all of them. On: the API
 * refuses payments and prices that don't fit a case (see App\Services\CasePaymentGuard).
 *
 * Usage:
 *   php artisan clinic:strict-payments _clinic
 *   php artisan clinic:strict-payments --all
 *   php artisan clinic:strict-payments _clinic --off
 */
class StrictPayments extends SwitchClinicSetting
{
    protected $signature = 'clinic:strict-payments
                            {tenant? : The clinic (tenant ID)}
                            {--all : Every clinic}
                            {--off : Accept any payment and price again}';

    protected $description = 'Refuse payments without a case price, over the price, or prices below what was paid';

    protected function settingKey(): string
    {
        return ClinicSettingRepository::STRICT_PAYMENTS_KEY;
    }

    protected function settingDescription(): string
    {
        return 'When enabled, payments on a case without a price, payments over the price and prices below what was paid are refused.';
    }

    protected function label(): string
    {
        return 'Strict payments';
    }
}
