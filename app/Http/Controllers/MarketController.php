<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Models\Watchlist;
use App\Services\Analytics\FinancialMath;
use App\Services\MarketData\MarketDataService;
use App\Services\News\NewsService;
use App\Services\Setup\CatalogSync;
use App\Support\Labels;
use DateTimeImmutable;

/**
 * Mercados: pizarra de cotizaciones por panel (con puntas de compra y venta) y ficha de cada instrumento.
 */
final class MarketController extends Controller
{
    /** Pestañas de la pizarra: clave => [título, categorías, descripción breve]. */
    public const GROUPS = [
        'acciones' => ['Acciones', ['stock_ar'], 'Acciones argentinas en BYMA, en pesos.'],
        'cedears' => ['CEDEARs', ['cedear'], 'Certificados de acciones y ETFs del exterior que cotizan en pesos en BYMA.'],
        'bonos' => ['Bonos', ['bond_sov'], 'Bonos del Tesoro y provinciales. Precio cada 100 de valor nominal; los terminados en D cotizan en dólares.'],
        'letras' => ['Letras', ['letra'], 'Letras del Tesoro a tasa fija (LECAP) y otras letras de corto plazo.'],
        'on' => ['ONs', ['on'], 'Obligaciones negociables (deuda de empresas). Las terminadas en O cotizan en pesos; en D, en dólares.'],
        'eeuu' => ['EE. UU.', ['stock_us', 'adr', 'stock_intl'], 'Acciones y ADRs que cotizan en Nueva York, en dólares.'],
        'etf' => ['ETFs', ['etf_index', 'etf_sector', 'etf_intl', 'etf_bond', 'etf', 'bond_corp'], 'Fondos cotizantes de EE. UU.'],
        'indices' => ['Índices', ['index'], 'Índices de referencia. No se compran directamente.'],
        'dolar' => ['Dólar', ['fx'], 'Cotizaciones del dólar en Argentina y otras monedas.'],
        'cripto' => ['Cripto', ['crypto', 'stablecoin'], 'Criptomonedas contra el dólar, las 24 horas.'],
    ];

    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $market = new MarketDataService();

        // Una vez por día se suma al catálogo todo lo que cotiza en BYMA y EE. UU.
        try {
            (new CatalogSync())->runIfDue();
        } catch (\Throwable) {
            // sin conexión: se usa el catálogo existente
        }

        $q = trim((string) $request->query('q', ''));
        $group = (string) $request->query('panel', 'acciones');
        $group = array_key_exists($group, self::GROUPS) ? $group : 'acciones';
        $filters = $q !== '' ? ['q' => $q] : ['category' => self::GROUPS[$group][1]];
        $page = max(1, (int) $request->query('pagina', 1));
        $total = Instrument::count($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        // Los paneles de BYMA y la cripto se actualizan enteros en una sola llamada,
        // así el orden por volumen refleja la rueda de hoy.
        if ($q === '' && in_array($group, ['acciones', 'cedears', 'bonos', 'letras', 'on', 'dolar', 'cripto'], true)) {
            $market->refreshQuotes(Instrument::search($filters, 1500));
        }

        $order = $q !== '' ? 'relevance' : (in_array($group, ['indices', 'dolar'], true) ? 'category' : 'volume');
        $instruments = Instrument::search($filters, self::PER_PAGE, $order, ($page - 1) * self::PER_PAGE);

        // Nombres pendientes (instrumentos sumados desde el panel) y cotizaciones de la página
        if ($market->completeNames($instruments) > 0) {
            $instruments = Instrument::search($filters, self::PER_PAGE, $order, ($page - 1) * self::PER_PAGE);
        }
        $instruments = $market->refreshQuotes($instruments);

        $userId = $this->userId();
        $watchlists = Watchlist::forUser($userId);
        $activeList = (int) $request->query('lista', $watchlists[0]['id'] ?? 0);
        $watchItems = [];
        if ($activeList) {
            Watchlist::findForUser($activeList, $userId);
            $watchItems = $market->refreshQuotes(Watchlist::instruments($activeList));
        }

        $counts = [];
        foreach (self::GROUPS as $k => $g) {
            $counts[$k] = Instrument::count(['category' => $g[1]]);
        }

        return $this->view('markets/index', [
            'pageTitle' => 'Mercados',
            'pageScripts' => ['markets', 'trade'],
            'instruments' => $instruments,
            'portfolios' => Portfolio::forUser($userId),
            'tradable' => !in_array($group, ['indices', 'dolar'], true),
            'q' => $q,
            'group' => $group,
            'groups' => self::GROUPS,
            'counts' => $counts,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'watchlists' => $watchlists,
            'activeList' => $activeList,
            'watchItems' => $watchItems,
            'watchedIds' => Watchlist::watchedIds($userId),
        ]);
    }

    public function show(Request $request): Response
    {
        $inst = Instrument::find((int) $request->param('id'));
        if ($inst === null) {
            throw new HttpException(404, 'El instrumento no existe.');
        }
        $market = new MarketDataService();
        $market->completeNames([$inst], 1);
        $inst = $market->refreshQuote(Instrument::find((int) $inst['id']) ?? $inst);
        $userId = $this->userId();

        // Indicadores sobre 1 año de historia
        $from = (new DateTimeImmutable('-1 year'))->format('Y-m-d');
        $closes = $market->closes($inst, $from, date('Y-m-d'));
        $indicators = null;
        if (count($closes) > 20) {
            $ppy = $inst['market_code'] === 'CRYPTO' ? 365 : 252;
            $returns = FinancialMath::returns($closes);
            $bench = Instrument::findBySymbol($inst['currency'] === 'ARS' ? 'MERV' : 'SPX', 'INDEX');
            $beta = null;
            if ($bench && (int) $bench['id'] !== (int) $inst['id'] && !in_array($inst['asset_class'], ['fx'], true)) {
                $aligned = FinancialMath::align([0 => $closes, 1 => $market->closes($bench, $from, date('Y-m-d'))]);
                if (count($aligned['dates']) > 20) {
                    $beta = FinancialMath::beta(FinancialMath::returns($aligned['series'][0]), FinancialMath::returns($aligned['series'][1]));
                }
            }
            $sma50 = array_slice($closes, -50);
            $sma200 = array_slice($closes, -200);
            $indicators = [
                'return_1y' => end($closes) / reset($closes) - 1,
                'volatility' => FinancialMath::annualizedVolatility($returns, $ppy),
                'max_drawdown' => FinancialMath::maxDrawdown($closes)['drawdown'],
                'sharpe' => FinancialMath::sharpe($returns, $this->riskFree(), $ppy),
                'beta' => $beta,
                'benchmark' => $bench ? \App\Support\Labels::tapeName($bench) : null,
                'high_52' => max($closes),
                'low_52' => min($closes),
                'sma50' => count($sma50) === 50 ? array_sum($sma50) / 50 : null,
                'sma200' => count($sma200) === 200 ? array_sum($sma200) / 200 : null,
            ];
        }

        $positions = Portfolio::holdingsOf($userId, (int) $inst['id']);

        $news = ['items' => [], 'error' => null];
        if (in_array($inst['asset_class'], ['equity', 'fund', 'crypto'], true) && (int) ($inst['needs_name'] ?? 0) === 0 && $inst['name'] !== $inst['symbol']) {
            $news = (new NewsService())->about((string) $inst['name'], (string) $inst['symbol']);
        }

        return $this->view('markets/show', [
            'pageTitle' => $inst['symbol'] . ' · ' . $inst['name'],
            'pageScripts' => ['instrument', 'trade'],
            'i' => $inst,
            'indicators' => $indicators,
            'profile' => $market->profile($inst),
            'positions' => $positions,
            'portfolios' => Portfolio::forUser($userId),
            'watchlists' => Watchlist::forUser($userId),
            'watched' => in_array((int) $inst['id'], Watchlist::watchedIds($userId), true),
            'benchmarks' => Instrument::benchmarks(),
            'news' => array_slice($news['items'], 0, 6),
            'countryLabel' => Labels::country($inst['country']),
        ]);
    }
}
