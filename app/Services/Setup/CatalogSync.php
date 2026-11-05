<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Services\MarketData\ApiUsage;
use App\Services\MarketData\Cache;
use App\Services\MarketData\Providers\Data912Provider;
use App\Support\Database;
use App\Support\Logger;
use Throwable;

/**
 * Suma al catálogo TODO lo que cotiza: el panel completo de BYMA (acciones,
 * CEDEARs, bonos, letras y obligaciones negociables) y las acciones y ADRs de EE. UU.,
 * a partir de los paneles públicos de data912.
 *
 * En BYMA cada especie tiene hasta tres tickers: en pesos (AL30), en dólares MEP
 * (AL30D) y en dólares cable (AL30C). Se guardan la versión en pesos y, para la
 * renta fija, también la versión en dólares MEP.
 *
 * Los instrumentos que dejan de cotizar (bonos vencidos, empresas que se retiran)
 * se desactivan, salvo que alguien los tenga en cartera.
 */
final class CatalogSync
{
    /** panel => [mercado, categoría, país] */
    private const PANELS = [
        'arg_stocks' => ['BYMA', 'stock_ar', 'AR'],
        'arg_cedears' => ['BYMA', 'cedear', null],
        'arg_bonds' => ['BYMA', 'bond_sov', 'AR'],
        'arg_notes' => ['BYMA', 'letra', 'AR'],
        'arg_corp' => ['BYMA', 'on', 'AR'],
        'usa_stocks' => ['USA', 'stock_us', 'US'],
        'usa_adrs' => ['USA', 'adr', null],
    ];

    /** @var array<string,list<string>> mercado => tickers vistos en sus paneles */
    private array $seen = [];
    /** @var array<string,bool> mercado => algún panel falló */
    private array $failed = [];

    private const MONTHS = ['E' => 1, 'F' => 2, 'M' => 3, 'A' => 4, 'Y' => 5, 'J' => 6, 'L' => 7, 'G' => 8, 'S' => 9, 'O' => 10, 'N' => 11, 'D' => 12];

    public function __construct(private ?Data912Provider $data912 = null)
    {
        $this->data912 ??= new Data912Provider();
    }

    /**
     * Sincroniza si pasó el intervalo configurado (por defecto, una vez por día).
     *
     * @return array<string, int>|null
     */
    public function runIfDue(): ?array
    {
        $hours = (int) config('market.catalog_sync_hours', 24);
        if (Cache::get('catalog-sync') !== null) {
            return null;
        }
        Cache::put('catalog-sync', 1, $hours * 3600);

        return $this->run();
    }

    /**
     * @return array<string,int> panel => instrumentos nuevos
     */
    public function run(): array
    {
        $markets = array_column(Database::all('SELECT id, code FROM markets'), 'id', 'code');
        $categories = array_column(Database::all('SELECT id, code FROM asset_categories'), 'id', 'code');
        $result = [];

        foreach (self::PANELS as $panel => [$market, $category, $country]) {
            if (!isset($markets[$market], $categories[$category])) {
                continue;
            }
            try {
                ApiUsage::acquire('data912');
                $rows = $this->data912->panel($panel);
                ApiUsage::success('data912');
            } catch (Throwable $e) {
                ApiUsage::failure('data912', $e->getMessage());
                Logger::warning('No se pudo sincronizar el panel', ['panel' => $panel, 'error' => $e->getMessage()]);
                $this->failed[$market] = true;
                continue;
            }
            $items = $this->select($panel, $rows);
            $result[$panel] = $this->store($items, (int) $markets[$market], $market, $categories, $country);
        }

        foreach ($this->seen as $market => $symbols) {
            if (empty($this->failed[$market])) {
                $this->deactivateMissing((int) $markets[$market], $symbols);
            }
        }

        return $result;
    }

    /**
     * Desactiva lo que ya no cotiza (solo instrumentos sumados por la sincronización,
     * que no tienen descripción propia) y reactiva lo que volvió a cotizar.
     *
     * @param list<string> $symbols
     */
    private function deactivateMissing(int $marketId, array $symbols): void
    {
        $seen = array_flip($symbols);
        $active = Database::all(
            'SELECT i.id, i.symbol FROM instruments i
             WHERE i.market_id = ? AND i.description IS NULL AND i.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM portfolio_positions pp WHERE pp.instrument_id = i.id AND pp.quantity > 0)',
            [$marketId],
        );
        $gone = array_map(fn($r) => (int) $r['id'], array_values(array_filter($active, fn($r) => !isset($seen[$r['symbol']]))));
        foreach (array_chunk($gone, 500) as $ids) {
            Database::query('UPDATE instruments SET is_active = 0 WHERE id IN (' . Database::placeholders(count($ids)) . ')', $ids);
        }
        foreach (array_chunk($symbols, 500) as $chunk) {
            Database::query(
                'UPDATE instruments SET is_active = 1 WHERE market_id = ? AND is_active = 0 AND description IS NULL AND symbol IN (' . Database::placeholders(count($chunk)) . ')',
                array_merge([$marketId], $chunk),
            );
        }
    }

    /**
     * Elige qué tickers del panel van al catálogo y en qué moneda.
     *
     * @param array<string,array<string,float>> $rows
     * @return list<array{symbol:string,currency:string,category:string,name:string,needs_name:int,unit:int}>
     */
    private function select(string $panel, array $rows): array
    {
        $fixedIncome = in_array($panel, ['arg_bonds', 'arg_notes', 'arg_corp'], true);
        $usa = str_starts_with($panel, 'usa_');
        $out = [];

        foreach ($rows as $symbol => $r) {
            $symbol = (string) $symbol;
            if ($symbol === '' || strlen($symbol) > 20) {
                continue;
            }
            $currency = $usa ? 'USD' : 'ARS';

            if (!$usa) {
                $variant = $this->variant($symbol, $rows, $panel);
                if ($variant === 'cable') {
                    continue;
                }
                if ($variant === 'mep') {
                    if (!$fixedIncome) {
                        continue; // acciones y CEDEARs: alcanza con la especie en pesos
                    }
                    $currency = 'USD';
                } elseif ($variant === 'ambiguous') {
                    continue;
                }
            }

            $category = self::PANELS[$panel][1];
            if ($panel === 'arg_bonds' && preg_match('/^S\d{2}[EFMAYJLGSOND]\d$/', $symbol)) {
                $category = 'letra';
            }
            [$name, $needsName] = $this->name($symbol, $panel, $category, $currency);
            $out[] = [
                'symbol' => $symbol,
                'currency' => $currency,
                'category' => $category,
                'name' => $name,
                'needs_name' => $needsName,
                'unit' => $fixedIncome ? 100 : 1,
            ];
        }

        return $out;
    }

    /**
     * Clasifica un ticker de BYMA: 'base' (pesos), 'mep' (dólares), 'cable' o 'ambiguous'.
     * Una especie en dólares vale cientos de veces menos que la misma en pesos.
     * @param array<string, array<string, mixed>> $rows
     */
    private function variant(string $symbol, array $rows, string $panel): string
    {
        $last = substr($symbol, -1);
        if (!in_array($last, ['D', 'C'], true)) {
            return 'base';
        }
        $stem = rtrim(substr($symbol, 0, -1), '.');
        $counterparts = [$stem, $stem . 'O'];
        foreach ($counterparts as $c) {
            if ($c === $symbol || !isset($rows[$c])) {
                continue;
            }
            $ars = $rows[$c]['c'] ?: $rows[$c]['bid'] ?: $rows[$c]['ask'];
            $usd = $rows[$symbol]['c'] ?: $rows[$symbol]['bid'] ?: $rows[$symbol]['ask'];
            if ($ars > 0 && $usd > 0 && $ars / $usd > 50) {
                return $last === 'D' ? 'mep' : 'cable';
            }
        }
        // Sin contraparte en pesos: en acciones y CEDEARs es un ticker propio (AMD, C...);
        // en renta fija no se puede saber la moneda con certeza.
        return in_array($panel, ['arg_bonds', 'arg_notes', 'arg_corp'], true) ? 'ambiguous' : 'base';
    }

    /** @return array{0:string,1:int} nombre y si hay que completarlo desde Yahoo */
    private function name(string $symbol, string $panel, string $category, string $currency): array
    {
        $usd = $currency === 'USD' ? ' (dólares)' : '';
        if ($category === 'letra') {
            if (preg_match('/^S(\d{2})([EFMAYJLGSOND])(\d)D?$/', $symbol, $m)) {
                $year = 2020 + (int) $m[3];
                $year += $year < (int) date('Y') - 2 ? 10 : 0;

                return [sprintf('LECAP vto. %s/%02d/%d%s', $m[1], self::MONTHS[$m[2]], $year, $usd), 0];
            }

            return ["Letra $symbol$usd", 0];
        }

        return match ($panel) {
            'arg_bonds' => ["Bono $symbol$usd", 0],
            'arg_corp' => ["Obligación negociable $symbol$usd", 0],
            'arg_cedears' => ["CEDEAR $symbol", 1],
            default => [$symbol, 1],
        };
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array<string,int|string> $categories
     */
    private function store(array $items, int $marketId, string $market, array $categories, ?string $country): int
    {
        // En EE. UU. un mismo ticker puede estar ya cargado en NYSE, Nasdaq o NYSE Arca.
        $existing = [];
        if ($market === 'USA') {
            $existing = array_flip(array_column(Database::all(
                "SELECT i.symbol FROM instruments i JOIN markets m ON m.id = i.market_id WHERE m.code IN ('NYSE','NASDAQ','NYSEARCA','USA')",
            ), 'symbol'));
        } else {
            $existing = array_flip(array_column(Database::all('SELECT symbol FROM instruments WHERE market_id = ?', [$marketId]), 'symbol'));
        }

        $new = 0;
        $seen = [];
        foreach (array_chunk($items, 400) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $it) {
                $seen[] = $it['symbol'];
                if (isset($existing[$it['symbol']])) {
                    continue;
                }
                $values[] = '(?, ?, ?, ?, ?, ?, 0, ?, 1, ?)';
                array_push(
                    $params,
                    $it['symbol'],
                    $it['name'],
                    $marketId,
                    (int) $categories[$it['category']],
                    $it['currency'],
                    $it['unit'],
                    $country,
                    $it['needs_name'],
                );
                $existing[$it['symbol']] = true;
                $new++;
            }
            if ($values !== []) {
                Database::query(
                    'INSERT IGNORE INTO instruments (symbol, name, market_id, category_id, currency, quote_unit, fractional, country, is_active, needs_name)
                     VALUES ' . implode(', ', $values),
                    $params,
                );
            }
        }

        $this->seen[$market] = array_merge($this->seen[$market] ?? [], $seen);

        return $new;
    }
}
