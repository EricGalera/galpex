<?php

declare(strict_types=1);

/*
 * Caché interna de Laravel en archivos. (La caché de cotizaciones de GALPEX está en la tabla api_cache.)
 */
return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'array' => ['driver' => 'array', 'serialize' => false],
    ],
    'prefix' => 'galpex_cache_',
];
