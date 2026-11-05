<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

use App\Services\MarketData\Cache;

/**
 * data912 — panel completo de BYMA (acciones, CEDEARs, bonos, letras y ONs) con
 * puntas de compra y venta, y acciones de EE. UU. No requiere clave.
 * Docs: https://data912.com/docs
 *
 * Cada panel se descarga entero en una sola llamada y se reutiliza un minuto,
 * así una tabla de 300 instrumentos no hace 300 consultas.
 */
final class Data912Provider extends AbstractProvider implements BatchQuoteProvider
{
    private const BASE = 'https://data912.com';

    public const PANELS = ['arg_stocks', 'arg_cedears', 'arg_bonds', 'arg_notes', 'arg_corp', 'usa_stocks', 'usa_adrs'];

    /** Panel => tipo de historia disponible en /historical/{tipo}/{símbolo}. */
    private const HISTORY = ['arg_stocks' => 'stocks', 'arg_cedears' => 'cedears', 'arg_bonds' => 'bonds'];

    /** @var array<string,array<string,array<string,float>>> */
    private static array $memo = [];

    public function code(): string
    {
        return 'data912';
    }

    public function name(): string
    {
        return 'data912 (BYMA)';
    }

    public function isConfigured(): bool
    {
        return (bool) config('market.sources.data912.enabled', true);
    }

    public function supports(array $instrument, string $capability): bool
    {
        $ref = self::ref($instrument);
        if ($ref === null) {
            return false;
        }
        if ($capability === 'quote') {
            return true;
        }
        if ($capability === 'history') {
            return isset(self::HISTORY[$ref[0]]);
        }

        return false;
    }

    /**
     * Panel y símbolo del instrumento en data912: ['arg_cedears', 'AAPL'] o null.
     *
     * @return array{0:string,1:string}|null
     * @param array<string, mixed> $instrument
     */
    public static function ref(array $instrument): ?array
    {
        $map = json_decode((string) ($instrument['provider_symbols'] ?? ''), true);
        if (is_array($map) && isset($map['data912'])) {
            $parts = explode(':', (string) $map['data912'], 2);

            return [$parts[0], $parts[1] ?? (string) $instrument['symbol']];
        }
        $symbol = (string) $instrument['symbol'];

        return match ($instrument['market_code'] ?? '') {
            'BYMA' => match ($instrument['category_code'] ?? '') {
                'stock_ar' => ['arg_stocks', $symbol],
                'cedear' => ['arg_cedears', $symbol],
                'bond_sov', 'bond_prov' => ['arg_bonds', $symbol],
                'letra' => ['arg_notes', $symbol],
                'on' => ['arg_corp', $symbol],
                default => null,
            },
            'NYSE', 'NASDAQ', 'NYSEARCA', 'USA' => [($instrument['category_code'] ?? '') === 'adr' ? 'usa_adrs' : 'usa_stocks', $symbol],
            default => null,
        };
    }

    /**
     * Panel completo: símbolo => [c, pct, bid, ask, q_bid, q_ask, v, ops].
     *
     * @return array<string,array<string,float>>
     */
    public function panel(string $panel): array
    {
        if (!in_array($panel, self::PANELS, true)) {
            return [];
        }
        if (isset(self::$memo[$panel])) {
            return self::$memo[$panel];
        }
        $keys = ['c', 'pct', 'bid', 'ask', 'qb', 'qa', 'v', 'ops'];
        $cached = Cache::get("data912:$panel");
        if (is_array($cached)) {
            return self::$memo[$panel] = array_map(fn($r) => array_combine($keys, $r), $cached);
        }
        $rows = $this->http->getJson(self::BASE . '/live/' . $panel, [], [], $this->code());
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r) || !isset($r['symbol'])) {
                continue;
            }
            $out[(string) $r['symbol']] = [
                'c' => (float) ($r['c'] ?? 0),
                'pct' => (float) ($r['pct_change'] ?? 0),
                'bid' => (float) ($r['px_bid'] ?? 0),
                'ask' => (float) ($r['px_ask'] ?? 0),
                'qb' => (float) ($r['q_bid'] ?? 0),
                'qa' => (float) ($r['q_ask'] ?? 0),
                'v' => (float) ($r['v'] ?? 0),
                'ops' => (float) ($r['q_op'] ?? 0),
            ];
        }
        if ($out === []) {
            $this->fail("data912 devolvió el panel $panel vacío.");
        }
        // En la caché se guarda sin nombres de campo: el panel de EE. UU. tiene más de 3000 filas
        Cache::put("data912:$panel", array_map('array_values', $out), 60);

        return self::$memo[$panel] = $out;
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $q = $this->quoteMany([$instrument])[(int) $instrument['id']] ?? null;
        if ($q === null) {
            $this->fail('data912 no tiene cotización para ' . $instrument['symbol']);
        }

        return $q;
    }

    public function quoteMany(array $instruments): array
    {
        $out = [];
        foreach ($instruments as $i) {
            $ref = self::ref($i);
            if ($ref === null) {
                continue;
            }
            [$panel, $symbol] = $ref;
            $row = $this->panel($panel)[$symbol] ?? null;
            $q = $row ? self::fromRow($row) : null;
            if ($q !== null) {
                $out[(int) $i['id']] = $q;
            }
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     * @param array<string, mixed> $r
     */
    private static function fromRow(array $r): ?array
    {
        $price = $r['c'] > 0 ? $r['c'] : null;
        if ($price === null) {
            return null;
        }
        $prev = $r['pct'] > -100 ? $price / (1 + $r['pct'] / 100) : null;

        return [
            'price' => $price,
            'prev_close' => $prev,
            'open' => null,
            'high' => null,
            'low' => null,
            'volume' => $r['v'] > 0 ? $r['v'] : null,
            'bid' => $r['bid'] > 0 ? $r['bid'] : null,
            'ask' => $r['ask'] > 0 ? $r['ask'] : null,
            'bid_size' => $r['qb'] > 0 ? $r['qb'] : null,
            'ask_size' => $r['qa'] > 0 ? $r['qa'] : null,
            'trades' => $r['ops'] > 0 ? (int) $r['ops'] : null,
            'quoted_at' => gmdate('Y-m-d H:i:s'),
            // BYMA publica sin costo con 20 minutos de demora.
            'is_delayed' => true,
        ];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $ref = self::ref($instrument);
        if ($ref === null) {
            return [];
        }
        [$panel, $symbol] = $ref;
        if (!isset(self::HISTORY[$panel])) {
            return [];
        }

        return array_values($this->series(self::HISTORY[$panel], $symbol, $from, $to));
    }

    /** @return array<string,array<string,mixed>> fecha => barra */
    private function series(string $type, string $symbol, string $from, string $to): array
    {
        $rows = $this->http->getJson(self::BASE . "/historical/$type/" . rawurlencode($symbol), [], [], $this->code());
        $bars = [];
        foreach ($rows as $r) {
            $date = substr((string) ($r['date'] ?? ''), 0, 10);
            $close = self::f($r['c'] ?? null);
            if ($date < $from || $date > $to || $close === null || $close <= 0) {
                continue;
            }
            $bars[$date] = [
                'date' => $date,
                'open' => self::f($r['o'] ?? null),
                'high' => self::f($r['h'] ?? null),
                'low' => self::f($r['l'] ?? null),
                'close' => $close,
                'adj_close' => $close,
                'volume' => self::f($r['v'] ?? null),
            ];
        }
        ksort($bars);

        return $bars;
    }
}
