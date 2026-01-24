<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
