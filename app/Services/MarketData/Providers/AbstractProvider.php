<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

use App\Exceptions\ProviderException;
use App\Services\MarketData\HttpClient;

/**
 * Base de los proveedores: cliente HTTP, símbolo propio de cada proveedor y conversión de números.
 */
abstract class AbstractProvider implements MarketDataProvider
{
    public function __construct(protected HttpClient $http = new HttpClient()) {}

    /**
     * @param array<string, mixed> $instrument
     */
    public function profile(array $instrument): array
    {
        return [];
    }

    /**
     * Símbolo del instrumento para este proveedor (o el símbolo propio).
     *
     * @param array<string, mixed> $instrument
     */
    protected function providerSymbol(array $instrument, string $key): ?string
    {
        $map = json_decode((string) ($instrument['provider_symbols'] ?? ''), true);

        return is_array($map) && isset($map[$key]) ? (string) $map[$key] : null;
    }

    protected function fail(string $message, bool $rateLimited = false): never
    {
        throw new ProviderException($message, $this->code(), $rateLimited);
    }

    protected static function f(mixed $v): ?float
    {
        return $v === null || $v === '' || !is_numeric($v) ? null : (float) $v;
    }
}
