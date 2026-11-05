<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Instrumentos financieros (acciones, bonos, ETFs, cripto...) con su última cotización.
 */
final class Instrument
{
    /** SELECT base con mercado, categoría y cotización. */
    public const SELECT = '
        SELECT i.*, m.code AS market_code, m.name AS market_name, m.timezone AS market_timezone,
               c.code AS category_code, c.name AS category_name, c.asset_class, c.default_beta,
               q.price, q.prev_close, q.open_price, q.day_high, q.day_low, q.volume, q.market_cap,
               q.change_abs, q.change_pct, q.bid, q.ask, q.bid_size, q.ask_size, q.trades,
               q.source AS quote_source,
               q.is_delayed AS quote_delayed, q.quoted_at, q.fetched_at
        FROM instruments i
        JOIN markets m ON m.id = i.market_id
        JOIN asset_categories c ON c.id = i.category_id
        LEFT JOIN quotes q ON q.instrument_id = i.id';

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::first(self::SELECT . ' WHERE i.id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findBySymbol(string $symbol, ?string $marketCode = null): ?array
    {
        if ($marketCode !== null) {
            return Database::first(self::SELECT . ' WHERE i.symbol = ? AND m.code = ?', [$symbol, $marketCode]);
        }

        return Database::first(self::SELECT . ' WHERE i.symbol = ? ORDER BY i.is_active DESC, i.id LIMIT 1', [$symbol]);
    }

    /**
     * @param list<int> $ids
     * @return array<int,array<string,mixed>> indexado por id
     */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $rows = Database::all(self::SELECT . ' WHERE i.id IN (' . Database::placeholders(count($ids)) . ')', $ids);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r;
        }

        return $out;
    }

    /**
     * Búsqueda con filtros por texto, categoría, mercado, país y sector.
     *
     * @param array{q?:string,category?:string,market?:string,country?:string,sector?:string,asset_class?:string} $filters
     * @return list<array<string,mixed>>
     */
    public static function search(array $filters, int $limit = 200, string $order = 'category', int $offset = 0): array
    {
        [$where, $params] = self::filters($filters);

        $orderBy = match ($order) {
            'symbol' => 'i.symbol',
            'change' => 'q.change_pct DESC',
            'volume' => '(q.volume IS NULL), q.volume * COALESCE(q.price, 0) / i.quote_unit DESC, i.symbol',
            // Ticker exacto, después tickers que empiezan con lo tipeado, nombres que empiezan igual
            // y por último coincidencias en cualquier parte; dentro de cada grupo, lo más operado.
            'relevance' => 'CASE WHEN i.symbol = ? THEN 0 WHEN i.symbol LIKE ? THEN 1 WHEN i.name LIKE ? THEN 2 ELSE 3 END,
                (q.price IS NULL), (q.volume IS NULL), q.volume * COALESCE(q.price, 0) / i.quote_unit DESC, CHAR_LENGTH(i.symbol), i.symbol',
            default => 'c.sort_order, i.symbol',
        };
        if ($order === 'relevance') {
            $q = str_replace(['%', '_'], ['\%', '\_'], strtoupper(trim((string) ($filters['q'] ?? ''))));
            array_push($params, $q, $q . '%', $q . '%');
        }

        return Database::all(
            self::SELECT . ' WHERE ' . implode(' AND ', $where) . " ORDER BY $orderBy LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params,
        );
    }

    /**
     * Cantidad de instrumentos que cumplen los filtros (para paginar).
     *
     * @param array<string, mixed> $filters
     */
    public static function count(array $filters): int
    {
        [$where, $params] = self::filters($filters);

        return (int) Database::scalar(
            'SELECT COUNT(*) FROM instruments i JOIN markets m ON m.id = i.market_id JOIN asset_categories c ON c.id = i.category_id
             WHERE ' . implode(' AND ', $where),
            $params,
        );
    }

    /**
     * @return array{0:list<string>,1:list<mixed>}
     * @param array<string, mixed> $filters
     */
    private static function filters(array $filters): array
    {
        $where = ['i.is_active = 1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(i.symbol LIKE ? OR i.name LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']) . '%';
            array_push($params, $like, $like);
        }
        foreach (['category' => 'c.code', 'market' => 'm.code', 'country' => 'i.country', 'sector' => 'i.sector', 'asset_class' => 'c.asset_class'] as $key => $col) {
            if (!empty($filters[$key])) {
                $values = (array) $filters[$key];
                $where[] = "$col IN (" . Database::placeholders(count($values)) . ')';
                array_push($params, ...array_values($values));
            }
        }


        return [$where, $params];
    }

    /** @return list<array<string,mixed>> */
    public static function benchmarks(): array
    {
        return Database::all(self::SELECT . ' WHERE i.is_benchmark = 1 ORDER BY i.id');
    }

    /**
     * Categorías con al menos un instrumento activo.
     *
     * @return list<array<string,mixed>>
     */
    public static function categories(): array
    {
        return Database::all(
            'SELECT c.*, COUNT(i.id) AS instruments FROM asset_categories c JOIN instruments i ON i.category_id = c.id AND i.is_active = 1
             GROUP BY c.id ORDER BY c.sort_order',
        );
    }

    /**
     * Mercados con al menos un instrumento activo.
     *
     * @return list<array<string,mixed>>
     */
    public static function markets(): array
    {
        return Database::all(
            'SELECT m.* FROM markets m WHERE EXISTS (SELECT 1 FROM instruments i WHERE i.market_id = m.id AND i.is_active = 1) ORDER BY m.id',
        );
    }

    /** @return list<string> */
    public static function sectors(): array
    {
        return array_column(Database::all('SELECT DISTINCT sector FROM instruments WHERE sector IS NOT NULL AND is_active = 1 ORDER BY sector'), 'sector');
    }

    /** @return list<string> */
    public static function countries(): array
    {
        return array_column(Database::all('SELECT DISTINCT country FROM instruments WHERE country IS NOT NULL AND is_active = 1 ORDER BY country'), 'country');
    }

    /**
     * Etiqueta legible del instrumento para listados: "GGAL · BYMA".
     *
     * @param array<string, mixed> $i
     */
    public static function label(array $i): string
    {
        return $i['symbol'] . ' (' . $i['market_code'] . ')';
    }

    /**
     * @param array<string, mixed> $i
     */
    public static function isFractional(array $i): bool
    {
        return (int) ($i['fractional'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed> $i
     */
    public static function quoteUnit(array $i): float
    {
        $u = (float) ($i['quote_unit'] ?? 1);

        return $u > 0 ? $u : 1.0;
    }

    /**
     * Mayores variaciones del día entre los instrumentos destacados (acciones, ETFs y cripto)
     * con precio de las últimas 6 horas, para no mostrar especies sin liquidez.
     *
     * @return list<array<string, mixed>>
     */
    public static function topMovers(int $limit = 6): array
    {
        return Database::all(
            self::SELECT . " WHERE i.is_active = 1 AND i.description IS NOT NULL AND q.change_pct IS NOT NULL
               AND c.asset_class IN ('equity','fund','crypto') AND c.code <> 'stablecoin' AND q.fetched_at > ?
             ORDER BY ABS(q.change_pct) DESC LIMIT " . max(1, $limit),
            [gmdate('Y-m-d H:i:s', time() - 6 * 3600)],
        );
    }

    /**
     * Cotizaciones guardadas por proveedor (panel de administración).
     *
     * @return list<array<string, mixed>>
     */
    public static function quoteStats(): array
    {
        return Database::all('SELECT source, COUNT(*) AS n, MAX(fetched_at) AS last FROM quotes GROUP BY source ORDER BY n DESC');
    }

    /**
     * Tamaño y rango de la historia de precios guardada (panel de administración).
     *
     * @return array<string, mixed>|null
     */
    public static function historyStats(): ?array
    {
        return Database::first('SELECT COUNT(*) AS rows_count, MIN(price_date) AS first_date, MAX(price_date) AS last_date FROM price_history');
    }
}
