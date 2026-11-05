<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

use App\Services\MarketData\Cache;

/**
 * Índices de la bolsa argentina desde los datos abiertos de BYMA (sin clave).
 * Desde octubre de 2026 el índice de referencia es el MSCI Merval (símbolo MMSCI).
 *
 * BYMA no publica la serie histórica en sus datos abiertos: GALPEX guarda el
 * cierre real de cada día para ir formando la serie propia.
 */
final class BymaIndexProvider extends AbstractProvider implements BatchQuoteProvider
{
    private const URL = 'https://open.bymadata.com.ar/vanoms-be-core/rest/api/bymadata/free/index-price';

    public function code(): string
    {
        return 'byma';
    }

    public function name(): string
    {
        return 'BYMA Datos abiertos';
    }

    public function isConfigured(): bool
    {
        return (bool) config('market.sources.byma.enabled', true);
    }

    public function supports(array $instrument, string $capability): bool
    {
        return $capability === 'quote' && $this->providerSymbol($instrument, 'byma') !== null;
    }

    /** @return array<string,array<string,mixed>> símbolo de BYMA => fila */
    private function indices(): array
    {
        return Cache::remember('byma:indices', 60, function () {
            $out = [];
            foreach ($this->http->postJson(self::URL, [], $this->code())['data'] ?? [] as $r) {
                if (is_array($r) && isset($r['symbol'])) {
                    $out[(string) $r['symbol']] = $r;
                }
            }

            return $out === [] ? null : $out;
        }) ?? [];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $q = $this->quoteMany([$instrument])[(int) $instrument['id']] ?? null;
        if ($q === null) {
            $this->fail('BYMA no informa el índice ' . $instrument['symbol']);
        }

        return $q;
    }

    public function quoteMany(array $instruments): array
    {
        $rows = $this->indices();
        $out = [];
        foreach ($instruments as $i) {
            $r = $rows[(string) $this->providerSymbol($i, 'byma')] ?? null;
            $price = $r !== null ? self::f($r['price'] ?? null) ?? self::f($r['trade'] ?? null) : null;
            if ($price === null || $price <= 0) {
                continue;
            }
            $out[(int) $i['id']] = [
                'price' => $price,
                'prev_close' => self::f($r['previousClosingPrice'] ?? null),
                'high' => self::f($r['highValue'] ?? null),
                'low' => self::f($r['minValue'] ?? null),
                'quoted_at' => gmdate('Y-m-d H:i:s'),
                'is_delayed' => true,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        return [];
    }
}
