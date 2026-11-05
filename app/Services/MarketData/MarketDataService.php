<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Exceptions\ProviderException;
use App\Models\Instrument;
use App\Services\MarketData\Providers\BatchQuoteProvider;
use App\Services\MarketData\Providers\MarketDataProvider;
use App\Services\MarketData\Providers\YahooFinanceProvider;
use App\Support\Database;
use App\Support\Logger;
use DateTimeImmutable;
use Throwable;

/**
 * Orquestador de datos de mercado.
 *
 *  - Elige proveedores según el mercado del instrumento (config/market.php).
 *  - Pide las cotizaciones en lote (un panel de BYMA, 20 símbolos de Yahoo por consulta).
 *  - Respeta límites de solicitudes, cachea y registra la última actualización.
 *  - Si ningún proveedor responde, conserva el último precio real conocido
 *    (la interfaz lo marca como desactualizado). Nunca genera precios.
 */
class MarketDataService
{
    /** Orden global de consulta; cada mercado usa la subsecuencia que figura en su ruta. */
    private const ORDER = ['data912', 'dolar', 'byma', 'binance', 'yahoo', 'finnhub', 'twelvedata', 'alphavantage'];

    /** @var array<string,MarketDataProvider> */
    private array $providers = [];
    private int $callBudget;

    /**
     * @param array<string, \App\Services\MarketData\Providers\MarketDataProvider>|null $providers
     */
    public function __construct(?array $providers = null, int $callBudget = 12)
    {
        if ($providers !== null) {
            foreach ($providers as $p) {
                $this->providers[$p->code()] = $p;
            }
        } else {
            foreach ((array) config('market.registry', []) as $code => $class) {
                $this->providers[$code] = new $class();
            }
        }
        $this->callBudget = $callBudget;
    }

    /** @return array<string,MarketDataProvider> */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Nombres de los proveedores activos (para mostrar las fuentes).
     *
     * @return list<string>
     */
    public function activeProviderNames(): array
    {
        $names = [];
        foreach ($this->providers as $p) {
            if ($p->isConfigured()) {
                $names[] = $p->name();
            }
        }

        return $names;
    }

    /**
     * @param 'quote'|'history'|'profile' $capability
     * @return list<MarketDataProvider>
     * @param array<string, mixed> $instrument
     */
    public function chainFor(array $instrument, string $capability): array
    {
        $key = $capability === 'history' ? 'market.history_routing.' : 'market.routing.';
        $codes = (array) config($key . ($instrument['market_code'] ?? ''), []);
        if ($capability === 'profile') {
            $codes = ['finnhub', 'alphavantage'];
        }
        $chain = [];
        foreach ($codes as $code) {
            $p = $this->providers[$code] ?? null;
            if ($p && $p->isConfigured() && $p->supports($instrument, $capability)) {
                $chain[] = $p;
            }
        }

        return $chain;
    }

    /* ───────────────────── Disyuntor por proveedor ───────────────────── */

    /** Un proveedor que falló por conexión se saltea 5 minutos para no demorar las páginas. */
    private function isDown(string $code): bool
    {
        return Cache::get("provider-down:$code") !== null;
    }

    private function markDown(string $code, Throwable $e): void
    {
        if ($e instanceof ProviderException && !$e->isRateLimited() && str_contains($e->getMessage(), 'Sin conexión')) {
            Cache::put("provider-down:$code", 1, 300);
        }
    }

    /* ───────────────────────── Cotizaciones ───────────────────────── */

    /**
     * Devuelve el instrumento con su cotización actualizada (si hacía falta).
     *
     * @return array<string,mixed>
     * @param array<string, mixed> $instrument
     */
    public function refreshQuote(array $instrument, bool $force = false): array
    {
        return $this->refreshQuotes([$instrument], $force)[0] ?? $instrument;
    }

    /**
     * Actualiza varias cotizaciones con la menor cantidad posible de llamadas.
     *
     * @param list<array<string,mixed>> $instruments
     * @return list<array<string,mixed>> mismos instrumentos, en el mismo orden
     */
    public function refreshQuotes(array $instruments, bool $force = false): array
    {
        $ttl = (int) config('market.quote_ttl', 60);
        $pending = [];
        foreach ($instruments as $i) {
            $fresh = !empty($i['fetched_at']) && $i['price'] !== null
                && (time() - strtotime($i['fetched_at'] . ' UTC')) < $ttl;
            if ($force || !$fresh) {
                $pending[(int) $i['id']] = $i;
            }
        }
        if ($pending === []) {
            return $instruments;
        }

        $updated = [];
        foreach (self::ORDER as $code) {
            $provider = $this->providers[$code] ?? null;
            if ($pending === [] || $provider === null || !$provider->isConfigured() || $this->isDown($code)) {
                continue;
            }
            $candidates = array_values(array_filter(
                $pending,
                fn($i) => in_array($provider, $this->chainFor($i, 'quote'), true),
            ));
            if ($candidates === []) {
                continue;
            }

            $quotes = $this->fetchQuotes($provider, $candidates);
            if ($quotes === []) {
                continue;
            }
            // Un solo commit para todo el lote (cientos de filas en un panel de BYMA).
            Database::transaction(function () use ($quotes, $code, &$pending, &$updated) {
                foreach ($quotes as $id => $q) {
                    if (!isset($pending[$id])) {
                        continue;
                    }
                    try {
                        $this->storeQuote($id, $q, $code, $pending[$id]);
                        $updated[$id] = true;
                        unset($pending[$id]);
                    } catch (Throwable $e) {
                        Logger::warning('No se pudo guardar la cotización', ['provider' => $code, 'id' => $id, 'error' => $e->getMessage()]);
                    }
                }
            });
        }

        if ($updated === []) {
            return $instruments;
        }
        $fresh = Instrument::findMany(array_keys($updated));

        return array_map(fn($i) => $fresh[(int) $i['id']] ?? $i, $instruments);
    }

    /**
     * @param list<array<string,mixed>> $candidates
     * @return array<int,array<string,mixed>>
     */
    private function fetchQuotes(MarketDataProvider $provider, array $candidates): array
    {
        $code = $provider->code();
        $out = [];

        if ($this->callBudget <= 0) {
            return [];
        }
        if ($provider instanceof BatchQuoteProvider) {
            // Yahoo cotiza de a 20: se limita la cantidad de consultas por página.
            if ($code === 'yahoo') {
                $candidates = array_slice($candidates, 0, max(0, $this->callBudget) * 20);
            }
            if ($candidates === [] || !ApiUsage::acquire($code)) {
                return [];
            }
            $this->callBudget -= $code === 'yahoo' ? (int) ceil(count($candidates) / 20) : 1;
            try {
                $out = $provider->quoteMany($candidates);
                ApiUsage::success($code);

                return $out;
            } catch (Throwable $e) {
                ApiUsage::failure($code, $e->getMessage());
                $this->markDown($code, $e);
                Logger::warning('Proveedor falló', ['provider' => $code, 'error' => $e->getMessage()]);
                if (count($candidates) > 1 && !$this->isDown($code)) {
                    // Un símbolo inválido puede hacer fallar el lote: se reintenta de a uno.
                    $candidates = array_slice($candidates, 0, 5);
                } else {
                    return [];
                }
            }
        }

        foreach ($candidates as $i) {
            if ($this->callBudget <= 0 || !ApiUsage::acquire($code)) {
                break;
            }
            $this->callBudget--;
            try {
                $out[(int) $i['id']] = $provider->quote($i);
                ApiUsage::success($code);
            } catch (Throwable $e) {
                ApiUsage::failure($code, $e->getMessage());
                $this->markDown($code, $e);
                if ($this->isDown($code)) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $q
     * @param array<string,mixed>|null $instrument fila del instrumento (para guardar la barra del día si no hay historia)
     */
    public function storeQuote(int $instrumentId, array $q, string $source, ?array $instrument = null): void
    {
        $price = (float) $q['price'];
        $prev = isset($q['prev_close']) && $q['prev_close'] ? (float) $q['prev_close'] : null;
        $changeAbs = $prev ? $price - $prev : null;
        $changePct = $prev ? ($price / $prev - 1) * 100 : null;

        Database::query(
            'REPLACE INTO quotes (instrument_id, price, prev_close, open_price, day_high, day_low, volume, market_cap,
                change_abs, change_pct, bid, ask, bid_size, ask_size, trades, source, is_delayed, quoted_at, fetched_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $instrumentId, $price, $prev, $q['open'] ?? null, $q['high'] ?? null, $q['low'] ?? null,
                $q['volume'] ?? null, $q['market_cap'] ?? null, $changeAbs, $changePct,
                $q['bid'] ?? null, $q['ask'] ?? null, $q['bid_size'] ?? null, $q['ask_size'] ?? null, $q['trades'] ?? null,
                $source, !empty($q['is_delayed']) ? 1 : 0, $q['quoted_at'] ?? now_utc(), now_utc(),
            ],
        );

        // Instrumentos sin proveedor de historia (letras, ONs): se guarda el cierre real de cada día
        // para ir formando su serie propia.
        if ($instrument !== null && $this->chainFor($instrument, 'history') === []) {
            Database::query(
                'REPLACE INTO price_history (instrument_id, price_date, open_price, high_price, low_price, close_price, adj_close, volume, source)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)',
                [$instrumentId, date('Y-m-d'), $q['high'] ?? null, $q['low'] ?? null, $price, $price, $q['volume'] ?? null, $source],
            );
        }
    }

    /**
     * Estado de frescura de un instrumento para la interfaz.
     *
     * @param array<string, mixed> $instrument
     */
    public static function dataStatus(array $instrument): string
    {
        if ($instrument['price'] === null) {
            return 'none';
        }
        $age = time() - strtotime(($instrument['quoted_at'] ?? $instrument['fetched_at']) . ' UTC');
        if ($age > (int) config('market.stale_after', 345600)) {
            return 'stale';
        }

        return (int) $instrument['quote_delayed'] === 1 ? 'delayed' : 'live';
    }

    /* ───────────────────────── Historia ───────────────────────── */

    /**
     * Garantiza que price_history tenga datos hasta el último día hábil anterior a hoy.
     * Se intenta como máximo una vez cada 6 horas por instrumento.
     * @param array<string, mixed> $instrument
     */
    public function ensureHistory(array $instrument): void
    {
        $id = (int) $instrument['id'];
        $chain = $this->chainFor($instrument, 'history');
        if ($chain === []) {
            return;
        }
        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $years = (int) config('market.history_years', 5);
        $defaultFrom = (new DateTimeImmutable("-$years years"))->format('Y-m-d');

        $last = Database::scalar('SELECT MAX(price_date) FROM price_history WHERE instrument_id = ? AND price_date < ?', [$id, date('Y-m-d')]);
        if ($last !== null && $last >= $this->lastTradingDay($instrument)) {
            return;
        }

        $marker = "history-check:$id";
        if (Cache::get($marker) !== null) {
            return;
        }
        Cache::put($marker, 1, 6 * 3600);

        $from = $last === null ? $defaultFrom : (new DateTimeImmutable((string) $last))->modify('+1 day')->format('Y-m-d');
        foreach ($chain as $provider) {
            if ($this->callBudget <= 0 || $this->isDown($provider->code()) || !ApiUsage::acquire($provider->code())) {
                continue;
            }
            $this->callBudget--;
            try {
                $bars = $provider->history($instrument, $from, $yesterday);
                ApiUsage::success($provider->code());
                if ($bars === []) {
                    continue;
                }
                $this->storeBars($id, $bars, $provider->code());

                return;
            } catch (Throwable $e) {
                ApiUsage::failure($provider->code(), $e->getMessage());
                $this->markDown($provider->code(), $e);
            }
        }
    }

    /** @param list<array<string,mixed>> $bars */
    public function storeBars(int $instrumentId, array $bars, string $source): void
    {
        foreach (array_chunk($bars, 300) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $b) {
                $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                array_push(
                    $params,
                    $instrumentId,
                    $b['date'],
                    $b['open'] ?? null,
                    $b['high'] ?? null,
                    $b['low'] ?? null,
                    $b['close'],
                    $b['adj_close'] ?? $b['close'],
                    $b['volume'] ?? null,
                    $source,
                );
            }
            Database::query(
                'REPLACE INTO price_history (instrument_id, price_date, open_price, high_price, low_price, close_price, adj_close, volume, source)
                 VALUES ' . implode(', ', $values),
                $params,
            );
        }
    }

    /**
     * Serie de cierres (ajustados) entre dos fechas, ordenada.
     *
     * @return array<string,float> fecha => precio
     * @param array<string, mixed> $instrument
     */
    public function closes(array $instrument, string $from, string $to, bool $ensure = true): array
    {
        if ($ensure) {
            $this->ensureHistory($instrument);
        }
        $rows = Database::all(
            'SELECT price_date, COALESCE(adj_close, close_price) AS px FROM price_history
             WHERE instrument_id = ? AND price_date BETWEEN ? AND ? ORDER BY price_date',
            [(int) $instrument['id'], $from, $to],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['price_date']] = (float) $r['px'];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     * @param array<string, mixed> $instrument
     */
    public function bars(array $instrument, string $from, string $to): array
    {
        $this->ensureHistory($instrument);

        return Database::all(
            'SELECT price_date AS date, open_price AS open, high_price AS high, low_price AS low, close_price AS close,
                    adj_close, volume
             FROM price_history WHERE instrument_id = ? AND price_date BETWEEN ? AND ? ORDER BY price_date',
            [(int) $instrument['id'], $from, $to],
        );
    }

    /**
     * @param array<string, mixed> $instrument
     */
    private function lastTradingDay(array $instrument): string
    {
        $d = new DateTimeImmutable('yesterday');
        if (($instrument['market_code'] ?? '') !== 'CRYPTO') {
            while ((int) $d->format('N') > 5) {
                $d = $d->modify('-1 day');
            }
        }

        return $d->format('Y-m-d');
    }

    /**
     * Fundamentales (solo con Finnhub o Alpha Vantage configurados): se cachean 12 horas.
     *
     * @return array<string, mixed>
     * @param array<string, mixed> $instrument
     */
    public function profile(array $instrument): array
    {
        if ($this->chainFor($instrument, 'profile') === []) {
            return [];
        }

        return Cache::remember('profile:' . $instrument['id'], 12 * 3600, function () use ($instrument) {
            foreach ($this->chainFor($instrument, 'profile') as $p) {
                if (!ApiUsage::acquire($p->code())) {
                    continue;
                }
                try {
                    $data = $p->profile($instrument);
                    ApiUsage::success($p->code());
                    if ($data !== []) {
                        return $data + ['_source' => $p->name()];
                    }
                } catch (Throwable $e) {
                    ApiUsage::failure($p->code(), $e->getMessage());
                }
            }

            return [];
        }) ?? [];
    }

    /* ───────────────────────── Nombres ───────────────────────── */

    /**
     * Completa el nombre de los instrumentos que se sumaron al catálogo solo con su
     * símbolo (el panel de BYMA no informa nombres). Usa Yahoo Finance de a 20.
     *
     * @param list<array<string,mixed>> $instruments
     * @return int cantidad de nombres completados
     */
    public function completeNames(array $instruments, int $maxCalls = 2): int
    {
        $yahoo = $this->providers['yahoo'] ?? null;
        if (!$yahoo instanceof YahooFinanceProvider || !$yahoo->isConfigured() || $this->isDown('yahoo')) {
            return 0;
        }
        $bySymbol = [];
        foreach ($instruments as $i) {
            if ((int) ($i['needs_name'] ?? 0) !== 1) {
                continue;
            }
            $s = YahooFinanceProvider::symbolFor($i);
            if ($s !== null) {
                $bySymbol[$s][] = $i;
            } else {
                Database::query('UPDATE instruments SET needs_name = 0 WHERE id = ?', [(int) $i['id']]);
            }
        }
        $done = 0;
        foreach (array_slice(array_chunk(array_keys($bySymbol), 20), 0, $maxCalls) as $chunk) {
            if (!ApiUsage::acquire('yahoo')) {
                break;
            }
            try {
                $metas = $yahoo->spark($chunk);
                ApiUsage::success('yahoo');
            } catch (Throwable $e) {
                ApiUsage::failure('yahoo', $e->getMessage());
                $this->markDown('yahoo', $e);
                break;
            }
            foreach ($chunk as $symbol) {
                $meta = $metas[$symbol] ?? [];
                $name = trim((string) ($meta['longName'] ?? $meta['shortName'] ?? ''));
                foreach ($bySymbol[$symbol] as $i) {
                    $sets = ['needs_name = 0'];
                    $params = [];
                    if ($name !== '') {
                        $sets[] = 'name = ?';
                        $params[] = mb_substr($name, 0, 150);
                        $done++;
                    }
                    // Los ETFs de EE. UU. que llegan del panel general pasan a su categoría.
                    if (($meta['instrumentType'] ?? '') === 'ETF' && ($i['category_code'] ?? '') === 'stock_us') {
                        $sets[] = "category_id = (SELECT id FROM asset_categories WHERE code = 'etf')";
                    }
                    $params[] = (int) $i['id'];
                    Database::query('UPDATE instruments SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
                }
            }
        }

        return $done;
    }
}
