<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Database;

/**
 * Limitador de intentos por ventana de tiempo, persistido en la tabla rate_limits.
 * Se usa para login, registro, recuperación de contraseña y la API interna.
 */
final class RateLimiter
{
    /**
     * Registra un intento y devuelve true si todavía está dentro del límite.
     */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $key = substr(hash('sha256', $key), 0, 64);
        $now = time();

        $row = Database::first('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?', [$key]);
        if ($row === null || ($now - (int) $row['window_start']) >= $windowSeconds) {
            Database::query(
                'REPLACE INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, ?)',
                [$key, $now],
            );

            return true;
        }

        if ((int) $row['hits'] >= $maxAttempts) {
            return false;
        }

        Database::query('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?', [$key]);

        return true;
    }

    public static function tooMany(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $hashed = substr(hash('sha256', $key), 0, 64);
        $row = Database::first('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?', [$hashed]);

        return $row !== null
            && (time() - (int) $row['window_start']) < $windowSeconds
            && (int) $row['hits'] >= $maxAttempts;
    }

    public static function clear(string $key): void
    {
        Database::query('DELETE FROM rate_limits WHERE rl_key = ?', [substr(hash('sha256', $key), 0, 64)]);
    }
}
