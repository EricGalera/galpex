<?php

declare(strict_types=1);

/*
 * Conexión a MySQL/MariaDB (XAMPP). Los datos salen del .env.
 * Laravel abre la conexión y App\Support\Database trabaja sobre ella.
 */
return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'galpex'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => (string) env('DB_PASSWORD', ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => null, // se respeta el modo SQL del servidor, igual que antes de Laravel
            'engine' => 'InnoDB',
            'timezone' => '+00:00', // todas las fechas se guardan en UTC
            'options' => [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],

    // Las migraciones de GALPEX son archivos .sql propios (tabla "migrations"); Laravel no usa esa tabla.
    'migrations' => ['table' => 'laravel_migrations', 'update_date_on_publish' => false],
];
