<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Services\Analytics\FinancialMath;
use App\Services\Analytics\RiskAnalyzer;
use App\Services\Analytics\ScenarioSimulator;
use App\Services\MarketData\MarketDataService;
use App\Services\Portfolio\ValuationService;
use App\Support\Validator;
use DateTimeImmutable;

/**
 * API JSON de análisis: escenarios de estrés y comparación de rentabilidad contra índices (base 100).
 */
final class AnalyticsApiController extends Controller
{
    /** Ejecuta un escenario de estrés sobre una cartera o el consolidado. */
    public function scenario(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'type' => 'required|in:market_drop,rate_hike,devaluation,sector_drop,asset_drop',
            'shock' => 'required|numeric|between:0,1000',
            'currency' => 'nullable|in:ARS,USD,BRL,EUR',
            'sector' => 'nullable|string|max:60',
            'instrument_id' => 'nullable|integer',
            'portfolio_id' => 'nullable|integer',
        ]);
        $market = new MarketDataService();
        $valuation = new ValuationService($market);
        if (!empty($data['portfolio_id'])) {
            $v = $valuation->value(Portfolio::findForUser((int) $data['portfolio_id'], $this->userId()));
        } else {
            $v = $valuation->aggregate($this->userId(), $this->currency());
        }

        // Betas estimadas con la historia real de precios contra el benchmark de la moneda
        $bench = Instrument::findBySymbol($v['currency'] === 'ARS' ? 'MERV' : 'SPX', 'INDEX');
        $risk = (new RiskAnalyzer($market))->analyze($v, $bench, $this->riskFree());
        $betas = [];
        foreach ($risk['positions'] as $p) {
            if ($p['beta'] !== null) {
                $betas[$p['id']] = $p['beta'];
            }
        }

        $result = (new ScenarioSimulator())->run($v['positions'], $v['cash'], $v['currency'], [
            'type' => $data['type'], 'shock' => $data['shock'], 'currency' => $data['currency'] ?? 'ARS',
            'sector' => $data['sector'], 'instrument_id' => $data['instrument_id'],
        ], $betas);

        return $this->json($result + ['currency' => $v['currency']]);
    }

    /** Compara rentabilidad base 100 de varios instrumentos (benchmarks). */
    public function compare(Request $request): Response
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids', '')))), 0, 6);
        if ($ids === []) {
            throw new BusinessRuleException('Elegí al menos un instrumento.');
        }
        $days = ['1M' => 31, '3M' => 92, '6M' => 183, '1A' => 366, '3A' => 1100][strtoupper((string) $request->query('rango', '1A'))] ?? 366;
        $from = (new DateTimeImmutable("-$days days"))->format('Y-m-d');
        $market = new MarketDataService();
        $out = [];
        foreach (Instrument::findMany($ids) as $inst) {
            $closes = $market->closes($inst, $from, date('Y-m-d'));
            if (count($closes) < 2) {
                continue;
            }
            $first = reset($closes);
            $returns = FinancialMath::returns($closes);
            $out[] = [
                'id' => (int) $inst['id'], 'symbol' => $inst['symbol'], 'name' => $inst['name'],
                'points' => array_map(fn($d, $v) => ['x' => $d, 'y' => $v / $first * 100], array_keys($closes), $closes),
                'return' => end($closes) / $first - 1,
                'volatility' => FinancialMath::annualizedVolatility($returns, $inst['market_code'] === 'CRYPTO' ? 365 : 252),
                'max_drawdown' => FinancialMath::maxDrawdown($closes)['drawdown'],
            ];
        }

        return $this->json(['series' => $out]);
    }
}
