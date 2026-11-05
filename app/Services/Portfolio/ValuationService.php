<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Models\Instrument;
use App\Models\Portfolio;
use App\Services\MarketData\CurrencyConverter;
use App\Services\MarketData\MarketDataService;
use App\Support\Database;
use App\Support\Labels;
use Throwable;

/**
 * Valuación de carteras a precios de mercado: posiciones, resultados,
 * rentabilidad y distribución por activo, categoría, sector, país y moneda.
 */
class ValuationService
{
    public function __construct(
        private ?MarketDataService $market = null,
        private ?CurrencyConverter $fx = null,
    ) {
        $this->market ??= new MarketDataService();
        $this->fx ??= new CurrencyConverter($this->market);
    }

    /**
     * @param array<string,mixed> $portfolio fila de portfolios
     * @return array<string,mixed>
     */
    public function value(array $portfolio, bool $refreshQuotes = true): array
    {
        $pid = (int) $portfolio['id'];
        $base = (string) $portfolio['base_currency'];
        $rows = Portfolio::positions($pid);

        $positions = [];
        $marketValue = 0.0;
        $costBasis = 0.0;
        $dayChange = 0.0;

        // Todas las cotizaciones de la cartera se piden juntas (un panel o un lote por proveedor).
        $freshRows = $refreshQuotes ? $this->market->refreshQuotes($rows) : $rows;
        foreach ($rows as $n => $row) {
            if ($refreshQuotes) {
                $fresh = $freshRows[$n] ?? $row;
                foreach (['price', 'prev_close', 'change_pct', 'change_abs', 'quote_source', 'quote_delayed', 'quoted_at', 'fetched_at', 'day_high', 'day_low', 'volume', 'bid', 'ask'] as $k) {
                    $row[$k] = $fresh[$k] ?? ($row[$k] ?? null);
                }
            }
            $qty = (float) $row['quantity'];
            $unit = Instrument::quoteUnit($row);
            try {
                $fx = $this->fx->rate($row['currency'], $base);
            } catch (Throwable) {
                $fx = null;
            }
            $price = $row['price'] !== null ? (float) $row['price'] : null;
            $mv = ($price !== null && $fx !== null) ? $qty * $price / $unit * $fx : null;
            $cost = $qty * (float) $row['avg_cost_base'];
            $unrealized = $mv !== null ? $mv - $cost : null;
            $dc = ($price !== null && $fx !== null && $row['prev_close'] !== null)
                ? $qty * ($price - (float) $row['prev_close']) / $unit * $fx : 0.0;

            $status = MarketDataService::dataStatus($row);

            $positions[] = $row + [
                'fx_rate' => $fx,
                'market_value' => $mv,
                'cost_basis' => $cost,
                'unrealized_pnl' => $unrealized,
                'unrealized_pct' => $cost > 0 && $unrealized !== null ? $unrealized / $cost * 100 : null,
                'day_change_value' => $dc,
                'data_status' => $status,
            ];
            $marketValue += $mv ?? $cost;
            $costBasis += $cost;
            $dayChange += $dc;
        }

        $cash = (float) $portfolio['cash_balance'];
        $total = $marketValue + $cash;
        foreach ($positions as &$p) {
            $p['weight'] = $total > 0 ? (($p['market_value'] ?? $p['cost_basis']) / $total * 100) : 0;
        }
        unset($p);
        usort($positions, fn($a, $b) => ($b['market_value'] ?? 0) <=> ($a['market_value'] ?? 0));

        $flows = Database::first(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN gross_amount WHEN type = 'withdrawal' THEN -gross_amount ELSE 0 END), 0) AS contributions,
                COALESCE(SUM(commission), 0) AS commissions,
                COALESCE(SUM(CASE WHEN type = 'dividend' THEN cash_effect ELSE 0 END), 0) AS dividends,
                COALESCE(SUM(CASE WHEN type = 'sell' THEN realized_pnl ELSE 0 END), 0) AS realized
             FROM transactions WHERE portfolio_id = ?",
            [$pid],
        );
        $contributions = (float) $flows['contributions'];
        $totalPnl = $total - $contributions;
        $prevTotal = $total - $dayChange;

        return [
            'portfolio' => $portfolio,
            'currency' => $base,
            'positions' => $positions,
            'cash' => $cash,
            'market_value' => $marketValue,
            'total_value' => $total,
            'cost_basis' => $costBasis,
            'unrealized_pnl' => $marketValue - $costBasis,
            'realized_pnl' => (float) $flows['realized'],
            'commissions' => (float) $flows['commissions'],
            'dividends' => (float) $flows['dividends'],
            'net_contributions' => $contributions,
            'total_pnl' => $totalPnl,
            'total_return_pct' => $contributions > 0 ? $totalPnl / $contributions * 100 : 0.0,
            'day_change' => $dayChange,
            'day_change_pct' => $prevTotal > 0 ? $dayChange / $prevTotal * 100 : 0.0,
            'allocation' => $this->allocation($positions, $cash, $total),
        ];
    }

    /**
     * Agrupa la cartera por distintos criterios.
     *
     * @param list<array<string,mixed>> $positions
     * @return array<string,list<array{key:string,label:string,value:float,weight:float,items:list<string>}>>
     */
    public function allocation(array $positions, float $cash, float $total): array
    {
        $groups = ['asset' => [], 'category' => [], 'class' => [], 'sector' => [], 'country' => [], 'currency' => []];
        $add = function (string $dim, string $key, string $label, float $value, string $symbol) use (&$groups) {
            $groups[$dim][$key] ??= ['key' => $key, 'label' => $label, 'value' => 0.0, 'items' => []];
            $groups[$dim][$key]['value'] += $value;
            if (!in_array($symbol, $groups[$dim][$key]['items'], true)) {
                $groups[$dim][$key]['items'][] = $symbol;
            }
        };

        foreach ($positions as $p) {
            $v = (float) ($p['market_value'] ?? $p['cost_basis']);
            $s = (string) $p['symbol'];
            $add('asset', (string) $p['id'], $s, $v, $s);
            $add('category', (string) $p['category_code'], (string) $p['category_name'], $v, $s);
            $add('class', (string) $p['asset_class'], Labels::assetClass($p['asset_class']), $v, $s);
            $add('sector', (string) ($p['sector'] ?? '—'), (string) ($p['sector'] ?? 'Sin sector'), $v, $s);
            $add('country', (string) ($p['country'] ?? '—'), Labels::country($p['country']), $v, $s);
            $cur = CurrencyConverter::normalize((string) $p['currency']);
            $add('currency', $cur, $cur, $v, $s);
        }
        if ($cash > 0.004) {
            foreach (array_keys($groups) as $dim) {
                $key = $dim === 'currency' ? '__cash_ccy' : '__cash';
                $add($dim, $key, 'Efectivo', $cash, 'Efectivo');
            }
        }

        foreach ($groups as $dim => $items) {
            $list = array_values($items);
            foreach ($list as &$g) {
                $g['weight'] = $total > 0 ? $g['value'] / $total * 100 : 0.0;
            }
            unset($g);
            usort($list, fn($a, $b) => $b['value'] <=> $a['value']);
            $groups[$dim] = $list;
        }

        return $groups;
    }

    /**
     * Patrimonio consolidado del usuario en su moneda principal.
     *
     * @return array<string,mixed>
     */
    public function aggregate(int $userId, string $currency, ?int $onlyPortfolioId = null): array
    {
        $portfolios = Portfolio::forUser($userId);
        if ($onlyPortfolioId !== null) {
            $portfolios = array_values(array_filter($portfolios, fn($p) => (int) $p['id'] === $onlyPortfolioId));
        }

        $out = [
            'currency' => $currency, 'total_value' => 0.0, 'cash' => 0.0, 'market_value' => 0.0, 'cost_basis' => 0.0,
            'net_contributions' => 0.0, 'total_pnl' => 0.0, 'day_change' => 0.0, 'realized_pnl' => 0.0,
            'portfolios' => [], 'positions' => [],
        ];
        $allPositions = [];

        foreach ($portfolios as $p) {
            $v = $this->value($p);
            $rate = $this->fx->rate($p['base_currency'], $currency);
            foreach (['total_value', 'cash', 'market_value', 'cost_basis', 'net_contributions', 'total_pnl', 'day_change', 'realized_pnl'] as $k) {
                $out[$k] += $v[$k] * $rate;
            }
            $out['portfolios'][] = [
                'id' => (int) $p['id'], 'name' => $p['name'], 'color' => $p['color'], 'currency' => $p['base_currency'],
                'total_value' => $v['total_value'], 'total_value_converted' => $v['total_value'] * $rate,
                'total_return_pct' => $v['total_return_pct'], 'day_change_pct' => $v['day_change_pct'],
            ];
            foreach ($v['positions'] as $pos) {
                $pos['market_value'] = ($pos['market_value'] ?? $pos['cost_basis']) * $rate;
                $pos['cost_basis'] *= $rate;
                $pos['unrealized_pnl'] = $pos['market_value'] - $pos['cost_basis'];
                $pos['day_change_value'] *= $rate;
                $pos['portfolio_name'] = $p['name'];
                $allPositions[] = $pos;
            }
        }

        // Consolida la misma especie presente en varias carteras
        $merged = [];
        foreach ($allPositions as $pos) {
            $id = (int) $pos['id'];
            if (!isset($merged[$id])) {
                $merged[$id] = $pos;
                continue;
            }
            foreach (['quantity', 'market_value', 'cost_basis', 'unrealized_pnl', 'day_change_value'] as $k) {
                $merged[$id][$k] += $pos[$k];
            }
        }
        $merged = array_values($merged);
        foreach ($merged as &$m) {
            $m['weight'] = $out['total_value'] > 0 ? $m['market_value'] / $out['total_value'] * 100 : 0;
            $m['unrealized_pct'] = $m['cost_basis'] > 0 ? $m['unrealized_pnl'] / $m['cost_basis'] * 100 : null;
        }
        unset($m);
        usort($merged, fn($a, $b) => $b['market_value'] <=> $a['market_value']);

        $out['positions'] = $merged;
        $out['total_return_pct'] = $out['net_contributions'] > 0 ? $out['total_pnl'] / $out['net_contributions'] * 100 : 0.0;
        $prev = $out['total_value'] - $out['day_change'];
        $out['day_change_pct'] = $prev > 0 ? $out['day_change'] / $prev * 100 : 0.0;
        $out['allocation'] = $this->allocation($merged, $out['cash'], $out['total_value']);

        return $out;
    }
}
