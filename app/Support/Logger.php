<?php

declare(strict_types=1);

namespace App\Support;

use App\Security\SecurityPolicy;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Eventos de GALPEX (proveedores de datos, instalación, seguridad) en storage/logs/galpex-AAAA-MM-DD.log,
 * a través del canal "galpex" de Laravel (config/logging.php).
 *
 * El mensaje se limpia de saltos de línea para que nadie pueda falsificar líneas del registro.
 */
final class Logger
{
    /** @param array<string,mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('galpex')->log($level, SecurityPolicy::logText($message), $context);
        } catch (Throwable) {
            // Sin permiso de escritura en storage/logs no se interrumpe la petición.
        }
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }
}
