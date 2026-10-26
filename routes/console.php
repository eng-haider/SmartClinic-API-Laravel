<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Messaging Automation Scheduler ──
Schedule::command('automation:process')->everyMinute();

// ── Account deletion: purge accounts whose grace period has ended ──
Schedule::command('accounts:purge-deleted')->dailyAt('03:00');
