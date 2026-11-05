<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Binance — datos PÚBLICOS de mercado de criptomonedas (no requiere clave).
 * Usa el endpoint data-api.binance.vision, exclusivo para datos de mercado.
 * Docs: https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
 */
final class BinanceProvider extends AbstractProvider implements BatchQuoteProvider
{
    public function code(): string
    {
        return 'binance';
    }

    public function name(): string
    {
        return 'Binance';
    }

    private function base(): string
    {
        return rtrim((string) config('market.sources.binance.url'), '/');
    }

    public function isConfigured(): bool
    {
        return (bool) config('market.sources.binance.enabled', true);
    }

    public function supports(array $instrument, string $capability): bool
    {
        return in_array($capability, ['quote', 'history'], true)
            && ($instrument['market_code'] ?? '') === 'CRYPTO'
            && $this->providerSymbol($instrument, 'binance') !== null;
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $symbol = (string) $this->providerSymbol($instrument, 'binance');
        $d = $this->http->getJson($this->base() . '/api/v3/ticker/24hr', ['symbol' => $symbol], [], $this->code());
        if (!isset($d['lastPrice'])) {
            $this->fail('Binance: ' . ($d['msg'] ?? "sin datos para $symbol"));
        }

        return self::fromTicker($d);
    }

    /** Una sola llamada para todas las criptomonedas visibles. */
    public function quoteMany(array $instruments): array
    {
        $ids = [];
        foreach ($instruments as $i) {
            $s = $this->providerSymbol($i, 'binance');
            if ($s !== null) {
                $ids[$s][] = (int) $i['id'];
            }
        }
        if ($ids === []) {
            return [];
        }
        $rows = $this->http->getJson($this->base() . '/api/v3/ticker/24hr', [
            'symbols' => json_encode(array_keys($ids), JSON_UNESCAPED_SLASHES),
        ], [], $this->code());
        $out = [];
        foreach ($rows as $d) {
            if (!is_array($d) || !isset($d['symbol'], $d['lastPrice'])) {
                continue;
            }
            foreach ($ids[$d['symbol']] ?? [] as $id) {
                $out[$id] = self::fromTicker($d);
            }
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     * @param array<string, mixed> $d
     */
    private static function fromTicker(array $d): array
    {
        return [
            'price' => (float) $d['lastPrice'],
            'prev_close' => self::f($d['openPrice'] ?? null) ?: self::f($d['prevClosePrice'] ?? null), // variación de 24 h
            'open' => self::f($d['openPrice'] ?? null),
            'high' => self::f($d['highPrice'] ?? null),
            'low' => self::f($d['lowPrice'] ?? null),
            'volume' => self::f($d['quoteVolume'] ?? null),
            'quoted_at' => gmdate('Y-m-d H:i:s', intdiv((int) ($d['closeTime'] ?? time() * 1000), 1000)),
            'is_delayed' => false,
        ];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $symbol = (string) $this->providerSymbol($instrument, 'binance');
        $bars = [];
        $start = strtotime($from . ' 00:00:00 UTC') * 1000;
        $end = strtotime($to . ' 23:59:59 UTC') * 1000;

        // Binance devuelve como máximo 1000 velas por llamada: se pagina.
        for ($page = 0; $page < 5 && $start < $end; $page++) {
            $rows = $this->http->getJson($this->base() . '/api/v3/klines', [
                'symbol' => $symbol, 'interval' => '1d', 'startTime' => $start, 'endTime' => $end, 'limit' => 1000,
            ], [], $this->code());
            if ($rows === []) {
                break;
            }
            foreach ($rows as $r) {
                $bars[] = [
                    'date' => gmdate('Y-m-d', intdiv((int) $r[0], 1000)),
                    'open' => (float) $r[1],
                    'high' => (float) $r[2],
                    'low' => (float) $r[3],
                    'close' => (float) $r[4],
                    'adj_close' => (float) $r[4],
                    'volume' => (float) $r[7],
                ];
            }
            $start = (int) end($rows)[0] + 86400000;
        }

        return $bars;
    }
}
