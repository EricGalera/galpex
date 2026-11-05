<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Alert;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Services\Alerts\AlertService;
use App\Services\Analytics\RiskAnalyzer;
use App\Services\MarketData\MarketDataService;
use App\Services\News\NewsService;
use App\Services\Portfolio\PortfolioHistoryService;
use App\Services\Portfolio\ValuationService;
use DateTimeImmutable;

/**
 * Inicio: patrimonio consolidado, riesgo, referencias del mercado, mayores variaciones, noticias y alertas.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        $currency = $this->currency();
        $market = new MarketDataService();
        (new AlertService($market))->evaluateThrottled($userId);

        $portfolios = Portfolio::forUser($userId);
        $selected = (int) $request->query('cartera', 0);
        $selected = in_array($selected, array_map(fn($p) => (int) $p['id'], $portfolios), true) ? $selected : null;

        $valuation = new ValuationService($market);
        $summary = $valuation->aggregate($userId, $currency, $selected);

        // Mini-curvas de cada cartera (últimos 30 días)
        $history = new PortfolioHistoryService($market);
        $cards = [];
        foreach ($summary['portfolios'] as $p) {
            $row = Portfolio::findForUser($p['id'], $userId);
            $curve = PortfolioHistoryService::slice($history->equityCurve($row), '1M');
            $cards[] = $p + ['spark' => array_column($curve, 'value')];
        }
        if ($selected !== null) {
            foreach (Portfolio::forUser($userId) as $p) {
                if ((int) $p['id'] !== $selected) {
                    $v = $valuation->value($p, false);
                    $cards[] = ['id' => (int) $p['id'], 'name' => $p['name'], 'color' => $p['color'], 'currency' => $p['base_currency'],
                        'total_value' => $v['total_value'], 'total_return_pct' => $v['total_return_pct'], 'day_change_pct' => $v['day_change_pct'], 'spark' => []];
                }
            }
        }

        // Riesgo general (contra el Merval si la moneda es pesos, contra el S&P 500 si es dólares)
        $benchmark = Instrument::findBySymbol($currency === 'ARS' ? 'MERV' : 'SPX', 'INDEX');
        $risk = (new RiskAnalyzer($market))->analyze($summary, $benchmark, $this->riskFree());

        // Mercado: índices de referencia con minigráfico de 1 mes
        $from = (new DateTimeImmutable('-35 days'))->format('Y-m-d');
        $indices = [];
        foreach ([['MERV', 'INDEX'], ['SPX', 'INDEX'], ['NDX', 'INDEX'], ['USD/ARS-OF', 'FX'], ['USD/ARS', 'FX'], ['USD/ARS-CCL', 'FX'], ['BTC', 'CRYPTO']] as [$s, $m]) {
            $i = Instrument::findBySymbol($s, $m);
            if ($i) {
                $indices[] = $i;
            }
        }
        foreach ($market->refreshQuotes($indices) as $k => $i) {
            $i['spark'] = array_values($market->closes($i, $from, date('Y-m-d')));
            if ($i['price'] !== null && end($i['spark']) !== (float) $i['price']) {
                $i['spark'][] = (float) $i['price'];
            }
            $indices[$k] = $i;
        }

        // Activos destacados: mayores variaciones del día entre acciones, ETFs y cripto
        // (solo entre los instrumentos destacados del catálogo y con precio del día, para no mostrar especies sin liquidez)
        $movers = Instrument::topMovers(6);
        $recent = Transaction::recentForUser($userId, 7);

        $alerts = array_slice(array_values(array_filter(Alert::forUser($userId), fn($a) => (int) $a['is_active'] === 1)), 0, 5);
        $news = (new NewsService())->market('mercados');

        return $this->view('dashboard/index', [
            'pageTitle' => 'Inicio',
            'pageScripts' => ['widgets'],
            'summary' => $summary,
            'currency' => $currency,
            'cards' => $cards,
            'portfolios' => $portfolios,
            'selected' => $selected,
            'risk' => $risk,
            'indices' => $indices,
            'movers' => $movers,
            'recent' => $recent,
            'alerts' => $alerts,
            'news' => array_slice($news['items'], 0, 4),
            'newsError' => $news['error'],
        ]);
    }
}
