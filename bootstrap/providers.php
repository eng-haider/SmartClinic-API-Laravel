<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Providers\MessagingServiceProvider::class,
    App\Modules\Orthodontics\OrthodonticsServiceProvider::class,
    Tymon\JwtAuth\Providers\LaravelServiceProvider::class,
];
