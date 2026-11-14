<?php

namespace App\Modules\Orthodontics\Http\Middleware;

use App\Modules\Orthodontics\Services\OrthodonticsException;
use App\Modules\Orthodontics\Services\OrthodonticsModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects every orthodontics API call while the clinic has the module off.
 * Saved records are untouched - switching the module back on shows them again.
 */
class EnsureOrthodonticsEnabled
{
    public function __construct(private OrthodonticsModule $module)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->module->enabled()) {
            return OrthodonticsException::disabled()->render();
        }

        return $next($request);
    }
}
