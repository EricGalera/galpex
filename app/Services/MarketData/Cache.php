<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Support\Database;

/**
 * Caché persistente en la tabla api_cache (sobrevive entre peticiones).
 */
final class Cache
{
    public static function get(string $key): mixed
    {
        $row = Database::first(
            'SELECT payload, expires_at FROM api_cache WHERE cache_key = ?',
            [hash('sha256', $key)],
        );
        if ($row === null || (int) $row['expires_at'] < time()) {
            return null;
        }

        return unserialize((string) $row['payload'], ['allowed_classes' => false]);
    }

    public static function put(string $key, mixed $value, int $ttlSeconds): void
    {
        Database::query(
            'REPLACE INTO api_cache (cache_key, payload, expires_at) VALUES (?, ?, ?)',
            [hash('sha256', $key), serialize($value), time() + $ttlSeconds],
        );
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $callback();
        if ($value !== null) {
            self::put($key, $value, $ttlSeconds);
        }

        return $value;
    }

    public static function forget(string $key): void
    {
        Database::query('DELETE FROM api_cache WHERE cache_key = ?', [hash('sha256', $key)]);
    }

    public static function purgeExpired(): int
    {
        return Database::query('DELETE FROM api_cache WHERE expires_at < ?', [time()])->rowCount();
    }
}
