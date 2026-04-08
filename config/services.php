<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'turnstile' => [
        'sitekey' => env('CLOUDFLARE_TURNSTILE_SITEKEY'),
        'secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
    ],
    'emilab' => [
        'valid_origins' => explode(',', env('EMILAB_VALID_ORIGINS', 'https://weblab.runnercloud.app')),
    ],
    'project_config' => [
        'app_name' => env('APP_NAME', 'Laravel'),
        'app_env' => env('APP_ENV', 'production'),
        'app_debug' => env('APP_DEBUG', false),
    ],

    'frans' => require __DIR__ . '/developer/fransDeveloperEvo.php',
    'ridho' => require __DIR__ . '/developer/ridhoDeveloperEvo.php',
];
