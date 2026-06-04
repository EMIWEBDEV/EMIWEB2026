<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'proses_login',
        'handle-task',           // Cloud Tasks handler (CLOUD_TASKS_URI default)
        'api/cloudtasks/handle', // Cloud Tasks handler (alternatif path)
    ];
}
