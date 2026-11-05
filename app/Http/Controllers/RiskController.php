<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Services\Analytics\RiskAnalyzer;
use App\Services\MarketData\MarketDataService;
use App\Services\Portfolio\PortfolioHistoryService;
use App\Services\Portfolio\ValuationService;

/**
 * Análisis de riesgo de una cartera (o de todas) contra un índice de referencia elegido por el usuario.
 */
final class RiskController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        $market = new MarketDataService();
        $portfolios = Portfolio::forUser($userId);
        $selected = $request->query('cartera') ? (int) $request->query('cartera') : null;
        $portfolio = $selected ? Portfolio::findForUser($selected, $userId) : null;

        $valuation = new ValuationService($market);
        $v = $portfolio ? $valuation->value($portfolio) : $valuation->aggregate($userId, $this->currency());
        $benchmarks = Instrument::benchmarks();
        $benchId = (int) $request->query('benchmark', 0);
        $bench = null;
        foreach ($benchmarks as $b) {
            if ((int) $b['id'] === $benchId) {
                $bench = $b;
            }
        }
        $bench ??= Instrument::findBySymbol($v['currency'] === 'ARS' ? 'MERV' : 'SPX', 'INDEX');
        $lookback = in_array((int) $request->query('dias', 365), [90, 180, 365, 730], true) ? (int) $request->query('dias', 365) : 365;
        $risk = (new RiskAnalyzer($market))->analyze($v, $bench, $this->riskFree(), $lookback);

        // Desempeño real (TWR) de la cartera desde que existe
        $curve = (new PortfolioHistoryService($market))->userCurve($userId, $v['currency'], $selected);
        $performance = count($curve) > 2 ? PortfolioHistoryService::performance($curve, $this->riskFree()) : null;

        $sectors = array_values(array_unique(array_filter(array_map(fn($p) => $p['sector'], $v['positions']))));
        sort($sectors);

        return $this->view('risk/index', [
            'pageTitle' => 'Análisis de riesgo',
            'pageScripts' => ['risk'],
            'portfolios' => $portfolios,
            'selected' => $selected,
            'v' => $v,
            'risk' => $risk,
            'benchmarks' => $benchmarks,
            'bench' => $bench,
            'lookback' => $lookback,
            'performance' => $performance,
            'sectors' => $sectors,
            'targetRisk' => $portfolio ? (int) $portfolio['target_risk'] : null,
        ]);
    }
}
