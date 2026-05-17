<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MaintenanceModeCheck
{
    private const BYPASS_KEY  = 'maintenance-true';
    private const SESSION_KEY = 'maintenance_bypass';

    public function handle(Request $request, Closure $next)
    {
        if (!config('app.app_maintenance', false)) {
            return $next($request);
        }

        if ($request->query('key') === self::BYPASS_KEY) {
            session([self::SESSION_KEY => true]);
        }

        if (session(self::SESSION_KEY)) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [], 503);
    }
}
