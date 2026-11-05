<?php

declare(strict_types=1);

/*
 * Sesión de Laravel: se guarda en archivos (storage/framework/sessions), sin tablas extra.
 * Cookie propia, HttpOnly (JavaScript no la puede leer) y SameSite=Lax. Si se usa HTTPS, también Secure.
 * El vencimiento absoluto y la rotación del ID los agrega App\Http\Middleware\SessionSecurity.
 */
return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_IDLE_MINUTES', 120), // minutos sin uso hasta que vence
    'expire_on_close' => true,                              // se borra al cerrar el navegador
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'cookie' => 'galpex_session',
    'path' => '/',
    'domain' => null,
    'secure' => env('SESSION_SECURE_COOKIE'),               // null: se activa sola con HTTPS
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
    'lottery' => [2, 100],
];
