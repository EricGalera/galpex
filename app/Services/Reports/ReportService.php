<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\MarketData\MarketDataService;
use App\Services\Portfolio\PortfolioHistoryService;
use App\Services\Portfolio\ValuationService;
use App\Support\Database;
use App\Support\Labels;

/**
 * Informes: rentabilidad por período, por activo y por categoría, resultados
 * realizados y no realizados, comisiones, dividendos e historial completo.
 */
class ReportService
{
    public function __construct(private ?MarketDataService $market = null)
    {
        $this->market ??= new MarketDataService();
    }

    /**
     * @return array<string,mixed>
     */
    public function build(int $userId, string $currency, ?int $portfolioId = null, ?string $from = null, ?string $to = null): array
    {
        $valuationService = new ValuationService($this->market);
        $historyService = new PortfolioHistoryService($this->market);

        $summary = $valuationService->aggregate($userId, $currency, $portfolioId);
        $curve = $historyService->userCurve($userId, $currency, $portfolioId);
        $from ??= $curve[0]['date'] ?? date('Y-m-d');
        $to ??= date('Y-m-d');
        $curveInRange = array_values(array_filter($curve, fn($p) => $p['date'] >= $from && $p['date'] <= $to));

        return [
            'currency' => $currency,
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'curve' => $curveInRange,
            'periods' => $this->periodReturns($curve, $from, $to),
            'by_asset' => $this->byAsset($userId, $summary, $portfolioId, $currency),
            'by_category' => $this->byCategory($summary),
            'transactions' => $this->transactions($userId, $portfolioId, $from, $to),
            'totals' => $this->totals($userId, $portfolioId, $from, $to, $currency),
        ];
    }

    /**
     * Rentabilidad mensual por tiempo (TWR) a partir de la curva patrimonial.
     *
     * @return list<array{period:string,start:float,end:float,flows:float,return:float}>
     * @param list<array<string, mixed>> $curve
     */
    public function periodReturns(array $curve, string $from, string $to): array
    {
        $twr = PortfolioHistoryService::twrIndex($curve);
        $byMonth = [];
        $values = [];
        foreach ($curve as $p) {
            $values[$p['date']] = $p;
        }
        $prevLevel = null;
        $prevValue = null;
        foreach ($twr as $date => $level) {
            $month = substr($date, 0, 7);
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = ['period' => $month, 'start_level' => $prevLevel ?? $level, 'start' => $prevValue ?? $values[$date]['value'], 'flows' => 0.0];
            }
            $byMonth[$month]['end_level'] = $level;
            $byMonth[$month]['end'] = $values[$date]['value'];
            $byMonth[$month]['flows'] += $values[$date]['flow'];
            $prevLevel = $level;
            $prevValue = $values[$date]['value'];
        }
        $out = [];
        foreach ($byMonth as $m) {
            if ($m['period'] < substr($from, 0, 7) || $m['period'] > substr($to, 0, 7)) {
                continue;
            }
            $out[] = [
                'period' => $m['period'],
                'start' => $m['start'],
                'end' => $m['end'],
                'flows' => $m['flows'],
                'return' => $m['start_level'] > 0 ? ($m['end_level'] / $m['start_level'] - 1) * 100 : 0.0,
            ];
        }

        return array_reverse($out);
    }

    /**
     * @return list<array<string,mixed>>
     * @param array<string, mixed> $summary
     */
    private function byAsset(int $userId, array $summary, ?int $portfolioId, string $currency): array
    {
        $fx = new \App\Services\MarketData\CurrencyConverter($this->market);
        $params = [$userId];
        $filter = '';
        if ($portfolioId !== null) {
            $filter = ' AND p.id = ?';
            $params[] = $portfolioId;
        }
        $rows = Database::all(
            "SELECT t.instrument_id, i.symbol, i.name, p.base_currency,
                    SUM(CASE WHEN t.type = 'sell' THEN t.realized_pnl ELSE 0 END) AS realized,
                    SUM(t.commission) AS commissions,
                    SUM(CASE WHEN t.type = 'dividend' THEN t.cash_effect ELSE 0 END) AS dividends,
                    SUM(CASE WHEN t.type = 'buy' THEN t.gross_amount + t.commission ELSE 0 END) AS bought
             FROM transactions t JOIN portfolios p ON p.id = t.portfolio_id JOIN instruments i ON i.id = t.instrument_id
             WHERE p.user_id = ? $filter AND t.instrument_id IS NOT NULL
             GROUP BY t.instrument_id, i.symbol, i.name, p.base_currency",
            $params,
        );
        $assets = [];
        foreach ($rows as $r) {
            $id = (int) $r['instrument_id'];
            $rate = $fx->rate($r['base_currency'], $currency);
            $assets[$id] ??= ['id' => $id, 'symbol' => $r['symbol'], 'name' => $r['name'], 'realized' => 0.0, 'unrealized' => 0.0,
                'commissions' => 0.0, 'dividends' => 0.0, 'bought' => 0.0, 'market_value' => 0.0];
            $assets[$id]['realized'] += (float) $r['realized'] * $rate;
            $assets[$id]['commissions'] += (float) $r['commissions'] * $rate;
            $assets[$id]['dividends'] += (float) $r['dividends'] * $rate;
            $assets[$id]['bought'] += (float) $r['bought'] * $rate;
        }
        foreach ($summary['positions'] as $p) {
            $id = (int) $p['id'];
            if (isset($assets[$id])) {
                $assets[$id]['unrealized'] = (float) $p['unrealized_pnl'];
                $assets[$id]['market_value'] = (float) $p['market_value'];
            }
        }
        foreach ($assets as &$a) {
            $a['total'] = $a['realized'] + $a['unrealized'] + $a['dividends'];
            $a['return_pct'] = $a['bought'] > 0 ? $a['total'] / $a['bought'] * 100 : null;
        }
        unset($a);
        $assets = array_values($assets);
        usort($assets, fn($a, $b) => $b['total'] <=> $a['total']);

        return $assets;
    }

    /**
     * @return list<array<string,mixed>>
     * @param array<string, mixed> $summary
     */
    private function byCategory(array $summary): array
    {
        $cats = [];
        foreach ($summary['positions'] as $p) {
            $k = (string) $p['category_name'];
            $cats[$k] ??= ['category' => $k, 'market_value' => 0.0, 'cost' => 0.0, 'unrealized' => 0.0, 'day_change' => 0.0];
            $cats[$k]['market_value'] += (float) $p['market_value'];
            $cats[$k]['cost'] += (float) $p['cost_basis'];
            $cats[$k]['unrealized'] += (float) $p['unrealized_pnl'];
            $cats[$k]['day_change'] += (float) $p['day_change_value'];
        }
        foreach ($cats as &$c) {
            $c['return_pct'] = $c['cost'] > 0 ? $c['unrealized'] / $c['cost'] * 100 : null;
            $c['weight'] = $summary['total_value'] > 0 ? $c['market_value'] / $summary['total_value'] * 100 : 0;
        }
        unset($c);
        $cats = array_values($cats);
        usort($cats, fn($a, $b) => $b['market_value'] <=> $a['market_value']);

        return $cats;
    }

    /** @return list<array<string,mixed>> */
    public function transactions(int $userId, ?int $portfolioId, string $from, string $to): array
    {
        $params = [$userId, $from . ' 00:00:00', $to . ' 23:59:59'];
        $filter = '';
        if ($portfolioId !== null) {
            $filter = ' AND p.id = ?';
            $params[] = $portfolioId;
        }

        return Database::all(
            "SELECT t.*, p.name AS portfolio_name, p.base_currency, i.symbol, i.name AS instrument_name, i.currency AS instrument_currency
             FROM transactions t JOIN portfolios p ON p.id = t.portfolio_id LEFT JOIN instruments i ON i.id = t.instrument_id
             WHERE p.user_id = ? AND t.executed_at BETWEEN ? AND ? $filter
             ORDER BY t.executed_at DESC, t.id DESC LIMIT 2000",
            $params,
        );
    }

    /** @return array<string,float> */
    private function totals(int $userId, ?int $portfolioId, string $from, string $to, string $currency): array
    {
        $fx = new \App\Services\MarketData\CurrencyConverter($this->market);
        $out = ['realized' => 0.0, 'commissions' => 0.0, 'dividends' => 0.0, 'deposits' => 0.0, 'withdrawals' => 0.0, 'buys' => 0.0, 'sells' => 0.0, 'operations' => 0];
        foreach ($this->transactions($userId, $portfolioId, $from, $to) as $t) {
            $rate = $fx->rate($t['base_currency'], $currency);
            $out['commissions'] += (float) $t['commission'] * $rate;
            switch ($t['type']) {
                case 'sell':
                    $out['realized'] += (float) $t['realized_pnl'] * $rate;
                    $out['sells'] += (float) $t['gross_amount'] * $rate;
                    $out['operations']++;
                    break;
                case 'buy':
                    $out['buys'] += (float) $t['gross_amount'] * $rate;
                    $out['operations']++;
                    break;
                case 'dividend':
                    $out['dividends'] += (float) $t['cash_effect'] * $rate;
                    break;
                case 'deposit':
                    $out['deposits'] += (float) $t['gross_amount'] * $rate;
                    break;
                case 'withdrawal':
                    $out['withdrawals'] += (float) $t['gross_amount'] * $rate;
                    break;
            }
        }

        return $out;
    }

    /**
     * Filas para exportar el historial a CSV.
     *
     * @return array{0: list<string>, 1: list<list<scalar|null>>}
     * @param list<array<string, mixed>> $transactions
     */
    public function transactionsCsv(array $transactions): array
    {
        $header = ['Fecha', 'Cartera', 'Tipo', 'Símbolo', 'Cantidad', 'Precio', 'Moneda instrumento', 'Tipo de cambio', 'Importe bruto', 'Comisión', 'Efecto en efectivo', 'Resultado realizado', 'Moneda cartera', 'Origen del precio'];
        $rows = [];
        foreach ($transactions as $t) {
            $rows[] = [
                local_date($t['executed_at'], 'Y-m-d H:i'), $t['portfolio_name'], Labels::txType($t['type']), $t['symbol'] ?? '',
                $t['quantity'] !== null ? num($t['quantity'], 4) : '', $t['price'] !== null ? num($t['price'], 4) : '',
                $t['instrument_currency'] ?? '', num($t['fx_rate'], 4), num($t['gross_amount']), num($t['commission']),
                num($t['cash_effect']), $t['realized_pnl'] !== null ? num($t['realized_pnl']) : '', $t['base_currency'],
                $t['price_source'] ?? '',
            ];
        }

        return [$header, $rows];
    }
}
