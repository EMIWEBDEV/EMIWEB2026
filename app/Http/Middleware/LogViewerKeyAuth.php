<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogViewerKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->query('key') === 'LIMS') {
            return $next($request);
        }

        abort(403, 'Access denied. Provide valid key: /log-viewer?key=LIMS');
    }
}
