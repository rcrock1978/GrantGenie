<?php

declare(strict_types=1);

use App\Http\Middleware\CorrelationIdMiddleware;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/*
|--------------------------------------------------------------------------
| Logging Configuration (T024)
|--------------------------------------------------------------------------
|
| Implements FR-015 + SC-006 + Constitution Principle V.
|  - Structured JSON to storage/logs/laravel-YYYY-MM-DD.json
|  - Daily rotation (Monolog RotatingFileHandler, 30-day retention)
|  - Every log line includes correlation_id, account_id, user_id when present
|  - Error channel separate for ops alerting
|
*/

return [
    'default' => env('LOG_CHANNEL', 'structured'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'structured' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => RotatingFileHandler::class,
            'with' => [
                'filename' => storage_path('logs/laravel.log'),
                'maxFiles' => env('LOG_MAX_FILES', 30),
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'formatter_with' => [
                'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES,
                'appendNewline' => true,
                'includeStacktraces' => true,
            ],
            'processors' => [
                App\Logging\InjectContextProcessor::class,
            ],
        ],

        'error' => [
            'driver' => 'monolog',
            'level' => 'error',
            'handler' => RotatingFileHandler::class,
            'with' => [
                'filename' => storage_path('logs/error.log'),
                'maxFiles' => env('LOG_MAX_FILES', 30),
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'processors' => [
                App\Logging\InjectContextProcessor::class,
            ],
        ],

        'audit' => [
            'driver' => 'monolog',
            'level' => 'info',
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => [
                'stream' => storage_path('logs/audit.log'),
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'processors' => [
                App\Logging\InjectContextProcessor::class,
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'formatter' => env('LOG_STDERR_FORMATTER') === 'json'
                ? Monolog\Formatter\JsonFormatter::class
                : Monolog\Formatter\LineFormatter::class,
        ],

        'null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class],
    ],
];
