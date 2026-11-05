<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Yahoo Finance — cotizaciones e historia diaria de acciones, ETFs, índices,
 * divisas y cripto de casi todas las bolsas del mundo. No requiere clave.
 *
 * Endpoints públicos usados:
 *   /v7/finance/spark   cotización de hasta 20 símbolos por consulta (con nombre y tipo)
 *   /v8/finance/chart   serie diaria con cierre ajustado por dividendos y splits
 */
final class YahooFinanceProvider extends AbstractProvider implements BatchQuoteProvider
{
    private const BASE = 'https://query1.finance.yahoo.com';
    private const BATCH = 20;

    public function code(): string
    {
        return 'yahoo';
    }

    public function name(): string
    {
        return 'Yahoo Finance';
    }

    public function isConfigured(): bool
    {
        return (bool) config('market.sources.yahoo.enabled', true);
    }

    public function supports(array $instrument, string $capability): bool
    {
        return in_array($capability, ['quote', 'history'], true) && self::symbolFor($instrument) !== null;
    }

    /**
     * Símbolo de Yahoo para un instrumento del catálogo (o null si Yahoo no lo cubre).
     *
     * @param array<string, mixed> $instrument
     */
    public static function symbolFor(array $instrument): ?string
    {
        $map = json_decode((string) ($instrument['provider_symbols'] ?? ''), true);
        if (is_array($map) && array_key_exists('yahoo', $map)) {
            return $map['yahoo'] !== '' ? (string) $map['yahoo'] : null;
        }
        $symbol = (string) $instrument['symbol'];
        $category = (string) ($instrument['category_code'] ?? '');

        return match ($instrument['market_code'] ?? '') {
            'NYSE', 'NASDAQ', 'NYSEARCA', 'USA' => str_replace('.', '-', $symbol),
            'BYMA' => in_array($category, ['stock_ar', 'cedear'], true) ? $symbol . '.BA' : null,
            'CRYPTO' => $category === 'stablecoin' && $symbol !== 'USDT' ? null : $symbol . '-USD',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $quotes = $this->quoteMany([$instrument]);
        $q = $quotes[(int) $instrument['id']] ?? null;
        if ($q === null) {
            $this->fail('Yahoo Finance no tiene cotización para ' . $instrument['symbol']);
        }

        return $q;
    }

    public function quoteMany(array $instruments): array
    {
        $bySymbol = [];
        foreach ($instruments as $i) {
            $s = self::symbolFor($i);
            if ($s !== null) {
                $bySymbol[$s][] = (int) $i['id'];
            }
        }
        $out = [];
        foreach (array_chunk(array_keys($bySymbol), self::BATCH) as $chunk) {
            foreach ($this->spark($chunk) as $symbol => $meta) {
                $q = self::fromMeta($meta);
                if ($q === null) {
                    continue;
                }
                foreach ($bySymbol[$symbol] ?? [] as $id) {
                    $out[$id] = $q;
                }
            }
        }

        return $out;
    }

    /**
     * Metadatos de hasta 20 símbolos: precio, cierre anterior, nombre y tipo.
     *
     * @param list<string> $symbols
     * @return array<string,array<string,mixed>>
     */
    public function spark(array $symbols): array
    {
        if ($symbols === []) {
            return [];
        }
        $d = $this->http->getJson(self::BASE . '/v7/finance/spark', [
            'symbols' => implode(',', $symbols), 'range' => '1d', 'interval' => '1d',
        ], [], $this->code());
        if (!empty($d['spark']['error'])) {
            $this->fail('Yahoo Finance: ' . ($d['spark']['error']['description'] ?? 'error'));
        }
        $out = [];
        foreach ($d['spark']['result'] ?? [] as $r) {
            $meta = $r['response'][0]['meta'] ?? null;
            if (is_array($meta) && isset($r['symbol'])) {
                $out[(string) $r['symbol']] = $meta;
            }
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     * @param array<string, mixed> $m
     */
    private static function fromMeta(array $m): ?array
    {
        $price = self::f($m['regularMarketPrice'] ?? null);
        if ($price === null || $price <= 0) {
            return null;
        }
        $prev = null;
        if (isset($m['regularMarketChangePercent']) && is_numeric($m['regularMarketChangePercent']) && (float) $m['regularMarketChangePercent'] > -100) {
            $prev = $price / (1 + (float) $m['regularMarketChangePercent'] / 100);
        }
        $prev ??= self::f($m['chartPreviousClose'] ?? null) ?? self::f($m['previousClose'] ?? null);
        $ts = (int) ($m['regularMarketTime'] ?? time());

        return [
            'price' => $price,
            'prev_close' => $prev,
            'open' => null,
            'high' => self::f($m['regularMarketDayHigh'] ?? null),
            'low' => self::f($m['regularMarketDayLow'] ?? null),
            'volume' => self::f($m['regularMarketVolume'] ?? null),
            'quoted_at' => gmdate('Y-m-d H:i:s', $ts > 0 ? $ts : time()),
            // Yahoo publica las bolsas no estadounidenses con unos 15-20 minutos de demora.
            'is_delayed' => !in_array($m['exchangeName'] ?? '', ['NMS', 'NYQ', 'NGM', 'NCM', 'PCX', 'CCC', 'BTS'], true),
        ];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $symbol = self::symbolFor($instrument);
        if ($symbol === null) {
            return [];
        }
        $d = $this->http->getJson(self::BASE . '/v8/finance/chart/' . rawurlencode($symbol), [
            'period1' => strtotime($from . ' 00:00:00 UTC'),
            'period2' => strtotime($to . ' 23:59:59 UTC'),
            'interval' => '1d',
            'events' => 'div,split',
        ], [], $this->code());
        $r = $d['chart']['result'][0] ?? null;
        if (!is_array($r)) {
            $this->fail('Yahoo Finance: ' . ($d['chart']['error']['description'] ?? "sin historia para $symbol"));
        }
        $offset = (int) ($r['meta']['gmtoffset'] ?? 0);
        $q = $r['indicators']['quote'][0] ?? [];
        $adj = $r['indicators']['adjclose'][0]['adjclose'] ?? [];

        $bars = [];
        foreach ($r['timestamp'] ?? [] as $k => $ts) {
            $close = self::f($q['close'][$k] ?? null);
            if ($close === null || $close <= 0) {
                continue;
            }
            $date = gmdate('Y-m-d', (int) $ts + $offset);
            $bars[$date] = [
                'date' => $date,
                'open' => self::f($q['open'][$k] ?? null),
                'high' => self::f($q['high'][$k] ?? null),
                'low' => self::f($q['low'][$k] ?? null),
                'close' => $close,
                'adj_close' => self::f($adj[$k] ?? null) ?? $close,
                'volume' => self::f($q['volume'][$k] ?? null),
            ];
        }

        return array_values($bars);
    }
}
