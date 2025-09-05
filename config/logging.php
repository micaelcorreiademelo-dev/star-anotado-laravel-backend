<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

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
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        // Custom channels for application monitoring
        'user_activity' => [
            'driver' => 'daily',
            'path' => storage_path('logs/user_activity.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'api_performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api_performance.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 7,
            'replace_placeholders' => true,
        ],

        'application_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/application_errors.log'),
            'level' => env('LOG_LEVEL', 'error'),
            'days' => 60,
            'replace_placeholders' => true,
        ],

        'transactions' => [
            'driver' => 'daily',
            'path' => storage_path('logs/transactions.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 365, // Manter logs financeiros por 1 ano
            'replace_placeholders' => true,
        ],

        'file_uploads' => [
            'driver' => 'daily',
            'path' => storage_path('logs/file_uploads.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'auth_attempts' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auth_attempts.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'slow_queries' => [
            'driver' => 'daily',
            'path' => storage_path('logs/slow_queries.log'),
            'level' => env('LOG_LEVEL', 'warning'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => env('LOG_LEVEL', 'warning'),
            'days' => 180, // Manter logs de segurança por 6 meses
            'replace_placeholders' => true,
        ],

        'cache' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cache.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 7,
            'replace_placeholders' => true,
        ],

        'queue_jobs' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue_jobs.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'system_metrics' => [
            'driver' => 'daily',
            'path' => storage_path('logs/system_metrics.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // Canal para desenvolvimento com mais detalhes
        'debug' => [
            'driver' => 'single',
            'path' => storage_path('logs/debug.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ],

        // Canal para produção com alertas críticos
        'production_alerts' => [
            'driver' => 'stack',
            'channels' => ['single', 'slack'],
            'level' => 'error',
        ],
    ],

];