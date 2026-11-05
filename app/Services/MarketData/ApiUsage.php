<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Support\Database;

/**
 * Control de límites de solicitudes por proveedor (por minuto y por día)
 * y registro del último éxito/error. Evita que una clave gratuita se bloquee.
 */
final class ApiUsage
{
    /** Reserva una llamada. Devuelve false si se alcanzó el límite. */
    public static function acquire(string $code): bool
    {
        $p = Database::first('SELECT * FROM api_providers WHERE code = ?', [$code]);
        if ($p === null) {
            return true;
        }
        $now = time();
        $minute = intdiv($now, 60);
        $today = gmdate('Y-m-d');

        $callsMinute = (int) $p['minute_window'] === $minute ? (int) $p['calls_minute'] : 0;
        $callsToday = $p['calls_date'] === $today ? (int) $p['calls_today'] : 0;

        if ($callsMinute >= (int) $p['requests_per_minute'] || $callsToday >= (int) $p['requests_per_day']) {
            return false;
        }

        Database::query(
            'UPDATE api_providers SET calls_minute = ?, minute_window = ?, calls_today = ?, calls_date = ? WHERE code = ?',
            [$callsMinute + 1, $minute, $callsToday + 1, $today, $code],
        );

        return true;
    }

    public static function success(string $code): void
    {
        Database::query('UPDATE api_providers SET last_success_at = ? WHERE code = ?', [now_utc(), $code]);
    }

    public static function failure(string $code, string $message): void
    {
        Database::query(
            'UPDATE api_providers SET last_error_at = ?, last_error = ? WHERE code = ?',
            [now_utc(), mb_substr($message, 0, 250), $code],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function status(): array
    {
        return Database::all('SELECT * FROM api_providers ORDER BY id');
    }
}
