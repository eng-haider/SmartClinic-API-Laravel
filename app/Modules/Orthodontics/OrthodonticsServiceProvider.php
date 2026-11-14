<?php

namespace App\Modules\Orthodontics;

use App\Modules\Orthodontics\Console\CleanupStaleCbctUploads;
use Illuminate\Support\ServiceProvider;

/**
 * Optional Smart Clinic Orthodontics module. Routes are loaded from
 * routes/tenant.php (inside the tenant API group); this provider only wires
 * config and the maintenance command.
 */
class OrthodonticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('orthodontics.php'), 'orthodontics');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CleanupStaleCbctUploads::class]);
        }
    }
}
