<?php

declare(strict_types=1);

return [
    'name' => 'GALPEX',
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    // Clave para cifrar credenciales de exchanges conectados (generada por el instalador).
    'key' => (string) env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'America/Argentina/Buenos_Aires'),
    'session_name' => 'galpex_session',
    'session_idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 120),   // se cierra tras 2 h sin uso
    'session_absolute_hours' => (int) env('SESSION_ABSOLUTE_HOURS', 12), // y siempre a las 12 h
    'api_rate_per_minute' => (int) env('API_RATE_PER_MINUTE', 180),
    // Instalar y actualizar solo desde la computadora donde corre XAMPP (true = también desde la red; no recomendado)
    'setup_allow_remote' => (bool) env('SETUP_ALLOW_REMOTE', false),

    // Seguridad de acceso (ver docs/seguridad.md)
    'login_max_attempts' => 5,        // fallidos por cuenta y conexión
    'login_max_per_account' => 20,    // fallidos por cuenta desde cualquier conexión
    'login_max_per_ip' => 50,         // fallidos desde una conexión probando distintas cuentas
    'login_window_seconds' => 900,    // ventana de 15 minutos
    'password_reset_minutes' => 60,

    // Análisis de riesgo: tasa libre de riesgo por defecto (cada usuario puede cambiarla)
    'default_risk_free_rate' => (float) env('RISK_FREE_RATE', 0.04),         // 4 % anual

    // Monedas soportadas como moneda base
    'currencies' => ['USD', 'ARS'],
    'timezones' => [
        'America/Argentina/Buenos_Aires', 'America/Montevideo', 'America/Santiago',
        'America/Sao_Paulo', 'America/Mexico_City', 'America/Bogota', 'America/New_York', 'Europe/Madrid', 'UTC',
    ],
];
