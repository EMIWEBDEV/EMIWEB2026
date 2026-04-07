<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRequestOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appEnv = strtolower(config('services.project_config.app_env', 'production'));

        if ($appEnv === 'production') {

            $allowedHosts = array_map(
                fn ($url) => parse_url($url, PHP_URL_HOST),
                config('services.emilab.valid_origins', [])
            );

            $currentHost = strtolower($request->getHost());

            if (!in_array($currentHost, $allowedHosts)) {
                abort(503); 
            }
        }

        return $next($request);
    }

}
