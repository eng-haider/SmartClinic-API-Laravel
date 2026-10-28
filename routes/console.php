<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tasks run in-process via Schedule::call because the host disables proc_open,
// which Schedule::command needs to spawn a child process

// ── Messaging Automation Scheduler ──
Schedule::call(fn () => Artisan::call('automation:process'))
    ->name('automation:process')
    ->everyMinute();

// ── Account deletion: purge accounts whose grace period has ended ──
Schedule::call(fn () => Artisan::call('accounts:purge-deleted'))
    ->name('accounts:purge-deleted')
    ->dailyAt('03:00');
