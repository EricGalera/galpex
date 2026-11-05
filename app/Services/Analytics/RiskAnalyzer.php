<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Instrument;
use App\Services\MarketData\CurrencyConverter;
use App\Services\MarketData\MarketDataService;
use DateTimeImmutable;

/**
 * Análisis cuantitativo de riesgo de una cartera.
 *
 * Método: simulación histórica con la COMPOSICIÓN ACTUAL. Se toman los precios
 * diarios del último año de cada activo (convertidos a la moneda base), se
 * calculan los retornos que habría tenido la cartera con los pesos de hoy y
 * sobre esa serie se miden volatilidad, Sharpe, Sortino, drawdown y beta.
 */
class RiskAnalyzer
{
    public function __construct(
        private ?MarketDataService $market = null,
        private ?CurrencyConverter $fx = null,
    ) {
        $this->market ??= new MarketDataService();
        $this->fx ??= new CurrencyConverter($this->market);
    }

    /**
     * @param array<string,mixed> $valuation resultado de ValuationService::value() o aggregate()
     * @return array<string,mixed>
     * @param array<string, mixed> $benchmark
     */
    public function analyze(array $valuation, ?array $benchmark, float $riskFree, int $lookbackDays = 365): array
    {
        $base = (string) $valuation['currency'];
        $total = (float) $valuation['total_value'];
        $positions = array_values(array_filter($valuation['positions'], fn($p) => ($p['market_value'] ?? 0) > 0));

        $result = [
            'currency' => $base,
            'lookback_days' => $lookbackDays,
            'positions' => [],
            'metrics' => null,
            'correlation' => ['labels' => [], 'matrix' => []],
            'concentration' => $this->concentration($valuation),
            'score' => null,
            'benchmark' => $benchmark ? ['id' => (int) $benchmark['id'], 'symbol' => $benchmark['symbol'], 'name' => $benchmark['name']] : null,
        ];
        if ($positions === [] || $total <= 0) {
            return $result;
        }

        $to = date('Y-m-d');
        $from = (new DateTimeImmutable("-$lookbackDays days"))->format('Y-m-d');

        // Series en moneda base
        $series = [];
        foreach ($positions as $p) {
            $series[(int) $p['id']] = $this->seriesInBase($p, $from, $to, $base);
        }
        $series = array_filter($series, fn($s) => count($s) > 20);
        // El índice entra en la comparación solo si tiene historia suficiente
        // (el MSCI Merval, por ejemplo, va sumando su cierre diario desde que se publica).
        if ($benchmark) {
            $bench = $this->market->closes($benchmark, $from, $to);
            if (count($bench) > 20) {
                $series['bench'] = $bench;
            } else {
                $result['benchmark_short'] = true;
            }
        }
        $aligned = FinancialMath::align($series);
        if (count($aligned['dates']) < 30) {
            $result['insufficient'] = true;

            return $result;
        }

        $returns = [];
        foreach ($aligned['series'] as $key => $values) {
            $returns[$key] = FinancialMath::returns($values);
        }
        $benchReturns = $returns['bench'] ?? null;
        unset($returns['bench']);
        if ($returns === []) {
            // Solo el índice tiene historia: no hay datos de la cartera para medir.
            $result['insufficient'] = true;

            return $result;
        }

        // Retornos diarios de la cartera con pesos actuales (efectivo con retorno 0)
        $n = count(reset($returns));
        $portfolioReturns = array_fill(0, $n, 0.0);
        $weights = [];
        foreach ($positions as $p) {
            $id = (int) $p['id'];
            if (!isset($returns[$id])) {
                continue;
            }
            $w = (float) $p['market_value'] / $total;
            $weights[$id] = $w;
            foreach ($returns[$id] as $t => $r) {
                $portfolioReturns[$t] += $w * $r;
            }
        }

        $index = [100.0];
        foreach ($portfolioReturns as $r) {
            $index[] = end($index) * (1 + $r);
        }
        $dd = FinancialMath::maxDrawdown($index);
        $vol = FinancialMath::annualizedVolatility($portfolioReturns);

        // Contribución de cada activo al riesgo total: w_i · Cov(r_i, r_p) / Var(r_p)
        $varP = FinancialMath::stdev($portfolioReturns) ** 2;
        foreach ($positions as $p) {
            $id = (int) $p['id'];
            $r = $returns[$id] ?? null;
            $result['positions'][] = [
                'id' => $id,
                'symbol' => $p['symbol'],
                'name' => $p['name'],
                'weight' => ($weights[$id] ?? (float) $p['market_value'] / $total) * 100,
                'volatility' => $r ? FinancialMath::annualizedVolatility($r) : null,
                'beta' => ($r && $benchReturns) ? FinancialMath::beta($r, $benchReturns) : null,
                'return_1y' => $r ? FinancialMath::cumulativeReturn($r) : null,
                'risk_contribution' => ($r && $varP > 0)
                    ? ($weights[$id] ?? 0) * FinancialMath::covariance($r, $portfolioReturns) / $varP * 100 : null,
                'max_drawdown' => $r ? FinancialMath::maxDrawdown($aligned['series'][$id])['drawdown'] : null,
            ];
        }
        usort($result['positions'], fn($a, $b) => ($b['risk_contribution'] ?? 0) <=> ($a['risk_contribution'] ?? 0));

        $result['metrics'] = [
            'volatility' => $vol,
            'stdev_daily' => FinancialMath::stdev($portfolioReturns),
            'return_period' => FinancialMath::cumulativeReturn($portfolioReturns),
            'return_annualized' => FinancialMath::annualizedReturn($portfolioReturns),
            'sharpe' => FinancialMath::sharpe($portfolioReturns, $riskFree),
            'sortino' => FinancialMath::sortino($portfolioReturns, $riskFree),
            'max_drawdown' => $dd['drawdown'],
            'beta' => $benchReturns ? FinancialMath::beta($portfolioReturns, $benchReturns) : null,
            'correlation_benchmark' => $benchReturns ? FinancialMath::correlation($portfolioReturns, $benchReturns) : null,
            'benchmark_volatility' => $benchReturns ? FinancialMath::annualizedVolatility($benchReturns) : null,
            'var_95_daily' => $this->historicalVaR($portfolioReturns, 0.95),
            'observations' => count($portfolioReturns),
            'from' => $aligned['dates'][0],
            'to' => end($aligned['dates']),
        ];

        // Correlaciones entre los 10 activos de mayor peso
        arsort($weights);
        $top = array_slice(array_keys($weights), 0, 10);
        $symbols = [];
        foreach ($positions as $p) {
            $symbols[(int) $p['id']] = $p['symbol'];
        }
        $result['correlation']['labels'] = array_map(fn($id) => $symbols[$id], $top);
        foreach ($top as $i => $a) {
            foreach ($top as $j => $b) {
                $result['correlation']['matrix'][$i][$j] = $a === $b ? 1.0 : FinancialMath::correlation($returns[$a], $returns[$b]);
            }
        }

        $result['score'] = self::riskScore($vol, $result['concentration']['hhi']);

        return $result;
    }

    /**
     * Serie de precios convertida a la moneda base día por día.
     *
     * @return array<string, float>
     * @param array<string, mixed> $instrument
     */
    public function seriesInBase(array $instrument, string $from, string $to, string $base): array
    {
        $closes = $this->market->closes($instrument, $from, $to);
        $unit = Instrument::quoteUnit($instrument);
        $out = [];
        foreach ($closes as $date => $px) {
            $out[$date] = $px / $unit * $this->fx->rateOn($instrument['currency'], $base, $date);
        }

        return $out;
    }

    /**
     * VaR histórico diario: pérdida que no se supera con el nivel de confianza dado.
     *
     * @param array<int|string, float> $returns
     */
    private function historicalVaR(array $returns, float $confidence): ?float
    {
        if (count($returns) < 30) {
            return null;
        }
        sort($returns);
        $idx = (int) floor((1 - $confidence) * count($returns));

        return $returns[$idx];
    }

    /**
     * @return array<string,mixed>
     * @param array<string, mixed> $valuation
     */
    private function concentration(array $valuation): array
    {
        $alloc = $valuation['allocation'];
        $assetWeights = array_map(fn($g) => $g['weight'], array_filter($alloc['asset'], fn($g) => $g['key'] !== '__cash'));
        $hhi = FinancialMath::herfindahl(array_values($assetWeights));
        $top = $alloc['asset'][0] ?? null;
        foreach ($alloc['asset'] as $g) {
            if ($g['key'] !== '__cash') {
                $top = $g;
                break;
            }
        }
        $sectors = array_values(array_filter($alloc['sector'], fn($g) => $g['key'] !== '__cash'));

        $warnings = [];
        if ($top && $top['weight'] > 25) {
            $warnings[] = sprintf('%s representa el %s del patrimonio: una caída de ese activo impacta fuerte en la cartera.', $top['label'], pct($top['weight'], 1, false));
        }
        if (isset($sectors[0]) && $sectors[0]['weight'] > 40) {
            $warnings[] = sprintf('El sector %s concentra el %s.', $sectors[0]['label'], pct($sectors[0]['weight'], 1, false));
        }
        $countries = array_values(array_filter($alloc['country'], fn($g) => $g['key'] !== '__cash'));
        if (isset($countries[0]) && $countries[0]['weight'] > 70) {
            $warnings[] = sprintf('El %s de la cartera depende de %s.', pct($countries[0]['weight'], 0, false), $countries[0]['label']);
        }

        return [
            'hhi' => $hhi,
            'effective_assets' => $hhi > 0 ? 1 / $hhi : 0,
            'top_asset' => $top,
            'top_sector' => $sectors[0] ?? null,
            'sectors' => $alloc['sector'],
            'countries' => $alloc['country'],
            'currencies' => $alloc['currency'],
            'warnings' => $warnings,
        ];
    }

    /**
     * Índice de riesgo 1–10: parte de la volatilidad anual (3 % ≈ 1, 30 % ≈ 10)
     * y suma un punto si la cartera está muy concentrada.
     *
     * @return array{value:float,label:string}
     */
    public static function riskScore(float $annualVol, float $hhi): array
    {
        $score = $annualVol * 100 / 3;
        if ($hhi > 0.25) {
            $score += 1;
        }
        $score = max(1.0, min(10.0, round($score, 1)));
        $label = match (true) {
            $score < 2.5 => 'Bajo',
            $score < 4.5 => 'Moderado bajo',
            $score < 6.5 => 'Moderado',
            $score < 8 => 'Alto',
            default => 'Muy alto',
        };

        return ['value' => $score, 'label' => $label];
    }
}
