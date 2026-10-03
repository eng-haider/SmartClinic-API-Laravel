<?php

namespace App\Providers;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Persistent PDO connections survive DB::purge() until the process exits, so a console
        // process that walks tenants (tenants:migrate, queue workers, the scheduler) keeps one
        // open socket per tenant DB. Past ~20 of them Hostinger refuses new connections with
        // "SQLSTATE[HY000] [2002] Operation not permitted". Web requests keep DB_PERSISTENT.
        // Artisan::starting rather than here directly: config:cache boots a fresh app in the
        // CLI, and stripping the option then would bake it out of the cached web config too.
        if ($this->app->runningInConsole()) {
            Artisan::starting(function () {
                foreach (config('database.connections', []) as $name => $connection) {
                    if (isset($connection['options'][\PDO::ATTR_PERSISTENT])) {
                        $options = $connection['options'];
                        unset($options[\PDO::ATTR_PERSISTENT]);
                        config(["database.connections.{$name}.options" => $options]);
                    }
                }
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define morph map for polymorphic relationships
        // Using morphMap (not enforceMorphMap) to allow both short names and full class names
        Relation::morphMap([
            'User' => 'App\Models\User',
            'Patient' => 'App\Models\Patient',
            'Case' => 'App\Models\CaseModel',
            'CaseModel' => 'App\Models\CaseModel',
            'Reservation' => 'App\Models\Reservation',
            'Recipe' => 'App\Models\Recipe',
            'App\Models\Case' => 'App\Models\CaseModel', // Handle legacy full class name
            'App\Models\CaseModel' => 'App\Models\CaseModel', // Handle full class name
            'App\Models\Patient' => 'App\Models\Patient',
            'App\Models\User' => 'App\Models\User',
            'App\Models\Reservation' => 'App\Models\Reservation',
            'App\Models\Recipe' => 'App\Models\Recipe',
        ]);
    }
}
