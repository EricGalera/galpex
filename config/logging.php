<?php

declare(strict_types=1);

/*
 * Registro de errores de Laravel en storage/logs/laravel.log.
 * Los eventos propios de GALPEX van a storage/logs/galpex-AAAA-MM-DD.log (App\Support\Logger).
 */
return [
    'default' => env('LOG_CHANNEL', 'single'),
    'deprecations' => ['channel' => 'null', 'trace' => false],
    'channels' => [
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'warning'), 'replace_placeholders' => true],
        // Eventos propios de GALPEX: un archivo por día, se conservan 30 días.
        'galpex' => ['driver' => 'daily', 'path' => storage_path('logs/galpex.log'), 'level' => 'info', 'days' => 30, 'replace_placeholders' => true],
        'null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class],
    ],
];
