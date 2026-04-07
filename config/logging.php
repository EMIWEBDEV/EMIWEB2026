<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],
        
        'TrackingUrl' => [
            'driver' => 'daily',
            'path' => storage_path('logs/TrackingUrl.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UjiSampelController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UjiSampelController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'AuthController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/AuthController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'BarangAnalisaController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/BarangAnalisaController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'BindingIdentityController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/BindingIdentityController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'BindingJenisAnalisaController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/BindingJenisAnalisaController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FormulatorValidasiHirarkiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FormulatorValidasiHirarkiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FormulatorValidasiUjiTrialController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FormulatorValidasiUjiTrialController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'KlasifikasiJenisAnalisaController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/KlasifikasiJenisAnalisaController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FormulatorTrialSampelController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FormulatorTrialSampelController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'JenisAnalisaBerkalaController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/JenisAnalisaBerkalaController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UjiValidasiFinalController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UjiValidasiFinalController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UjiSampelSementaraController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UjiSampelSementaraController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'StandarRentangController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/StandarRentangController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'QuisyController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/QuisyController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'PrinterTemplatesControllerController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/PrinterTemplatesControllerController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'TrackingPerformersSementara' => [
            'driver' => 'daily',
            'path' => storage_path('logs/TrackingPerformersSementara.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'trackingPerformersToDatabaseMultiRumus' => [
            'driver' => 'daily',
            'path' => storage_path('logs/trackingPerformersToDatabaseMultiRumus.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'trackingPerformersToDatabaseResampling' => [
            'driver' => 'daily',
            'path' => storage_path('logs/trackingPerformersToDatabaseResampling.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'IdentityController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/IdentityController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FormulatorRegistrasiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FormulatorRegistrasiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'MesinAnalisaController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/MesinAnalisaController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'PengajuanBukaUlangUjiSampelController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/PengajuanBukaUlangUjiSampelController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'PerhitunganController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/PerhitunganController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'POSampelMultiQrCodeController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/POSampelMultiQrCodeController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'POSampleController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/POSampleController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'ResamplingController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ResamplingController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UjiSampelDetailSementaraController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UjiSampelDetailSementaraController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UjiSampleDetailController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UjiSampleDetailController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://' . env('PAPERTRAIL_URL') . ':' . env('PAPERTRAIL_PORT'),
            ],
        ],

        'purchase_order' => [
            'driver' => 'daily',
            'path' => storage_path('logs/purchase_order_log.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'raw_material' => [
            'driver' => 'daily',
            'path' => storage_path('logs/raw_material_log.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'packaging' => [
            'driver' => 'daily',
            'path' => storage_path('logs/packaging_log.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'expedition' => [
            'driver' => 'daily',
            'path' => storage_path('logs/expedition_log.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
