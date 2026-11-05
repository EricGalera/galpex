<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Services\MarketData\MarketDataService;
use App\Services\Portfolio\CommissionSchedule;
use App\Services\Portfolio\PortfolioHistoryService;
use App\Services\Portfolio\PortfolioService;
use App\Services\Portfolio\RebalanceService;
use App\Services\Portfolio\TradingService;
use App\Services\Portfolio\ValuationService;
use App\Support\Validator;

/**
 * Carteras: alta, edición, archivo, operaciones de compra y venta, ingresos y retiros, objetivos y rebalanceo.
 */
final class PortfolioController extends Controller
{
    private MarketDataService $market;
    private TradingService $trading;

    public function __construct()
    {
        $this->market = new MarketDataService();
        $this->trading = new TradingService($this->market);
    }

    public function index(Request $request): Response
    {
        $userId = $this->userId();
        $valuation = new ValuationService($this->market);
        $history = new PortfolioHistoryService($this->market);
        $cards = [];
        foreach (Portfolio::forUser($userId) as $p) {
            $v = $valuation->value($p);
            $curve = PortfolioHistoryService::slice($history->equityCurve($p), '3M');
            $cards[] = ['p' => $p, 'v' => $v, 'spark' => array_column($curve, 'value')];
        }
        $archived = array_values(array_filter(Portfolio::forUser($userId, true), fn($p) => (int) $p['is_archived'] === 1));

        return $this->view('portfolios/index', [
            'pageTitle' => 'Carteras',
            'cards' => $cards,
            'archived' => $archived,
            'objectives' => Portfolio::OBJECTIVES,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('portfolios/form', [
            'pageTitle' => 'Nueva cartera',
            'portfolio' => null,
            'objectives' => Portfolio::OBJECTIVES,
            'currencies' => config('app.currencies'),
            'commissions' => CommissionSchedule::table(),
            'benchmarks' => Instrument::benchmarks(),
            'selectedBenchmarks' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $id = (new PortfolioService($this->trading))->create($this->userId(), $request->all());

        return $this->redirect('/carteras/' . $id, 'Cartera creada con su capital inicial en efectivo. Ya podés comprar activos.');
    }

    public function show(Request $request): Response
    {
        $userId = $this->userId();
        $p = Portfolio::findForUser((int) $request->param('id'), $userId);
        $v = (new ValuationService($this->market))->value($p);
        $targets = Portfolio::targets((int) $p['id']);
        $targetMap = array_column($targets, 'target_pct', 'instrument_id');

        $txFilter = (string) $request->query('tipo', '');
        $transactions = Transaction::forPortfolio((int) $p['id'], $txFilter !== '' ? $txFilter : null);
        $rejected = Transaction::rejectedOrders((int) $p['id']);
        $closed = array_values(array_filter(Portfolio::positions((int) $p['id'], true), fn($r) => (float) $r['quantity'] <= 0));

        return $this->view('portfolios/show', [
            'pageTitle' => $p['name'],
            'pageScripts' => ['widgets', 'trade', 'portfolio'],
            'p' => $p,
            'v' => $v,
            'targets' => $targets,
            'targetMap' => $targetMap,
            'transactions' => $transactions,
            'txFilter' => $txFilter,
            'rejected' => $rejected,
            'closed' => $closed,
            'benchmarks' => Instrument::findMany(Portfolio::benchmarkIds((int) $p['id'])),
            'objectives' => Portfolio::OBJECTIVES,
            'portfolios' => [$p],
        ]);
    }

    public function edit(Request $request): Response
    {
        $p = Portfolio::findForUser((int) $request->param('id'), $this->userId());

        return $this->view('portfolios/form', [
            'pageTitle' => 'Editar cartera',
            'portfolio' => $p,
            'objectives' => Portfolio::OBJECTIVES,
            'currencies' => config('app.currencies'),
            'commissions' => CommissionSchedule::table(),
            'benchmarks' => Instrument::benchmarks(),
            'selectedBenchmarks' => Portfolio::benchmarkIds((int) $p['id']),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        (new PortfolioService($this->trading))->update($this->userId(), $id, $request->all() + ['benchmarks' => []]);

        return $this->redirect('/carteras/' . $id, 'Cambios guardados.');
    }

    public function archive(Request $request): Response
    {
        (new PortfolioService($this->trading))->archive($this->userId(), (int) $request->param('id'));

        return $this->redirect('/carteras', 'Cartera archivada.');
    }

    public function trade(Request $request): Response
    {
        $p = Portfolio::findForUser((int) $request->param('id'), $this->userId());
        $data = Validator::make($request->all(), [
            'instrument_id' => 'required|integer',
            'side' => 'required|in:buy,sell',
            'quantity' => 'required|numeric|positive|max:1000000000000',
        ]);
        $r = $this->trading->execute((int) $p['id'], (int) $data['instrument_id'], $data['side'], (float) $data['quantity'], ['user_id' => $this->userId()]);
        $verb = $r['side'] === 'buy' ? 'Compraste' : 'Vendiste';
        $msg = sprintf(
            '%s %s %s a %s. Comisión %s. Efectivo disponible: %s.',
            $verb,
            num($r['quantity'], $r['quantity'] == floor($r['quantity']) ? 0 : 6),
            $r['symbol'],
            money($r['price'], $r['currency']),
            money($r['commission'], $r['base_currency'], 2),
            money($r['cash_after'], $r['base_currency'], 2),
        );
        if ($r['realized_pnl'] !== null) {
            $msg .= ' Resultado realizado: ' . signed_money($r['realized_pnl'], $r['base_currency']) . '.';
        }
        session()->flash('journal_tx', $r['transaction_id']);

        return $this->redirect('/carteras/' . $p['id'], $msg);
    }

    public function cash(Request $request): Response
    {
        $p = Portfolio::findForUser((int) $request->param('id'), $this->userId());
        $data = Validator::make($request->all(), [
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|positive|max:1000000000000',
            'notes' => 'nullable|string|max:255',
        ]);
        $after = $this->trading->moveCash((int) $p['id'], $data['type'], (float) $data['amount'], null, $data['notes']);

        return $this->redirect('/carteras/' . $p['id'], ($data['type'] === 'deposit' ? 'Ingreso registrado.' : 'Retiro registrado.') . ' Efectivo disponible: ' . money($after, $p['base_currency'], 2) . '.');
    }

    public function targets(Request $request): Response
    {
        $id = (int) $request->param('id');
        $targets = $request->input('targets', []);
        if (!is_array($targets)) {
            throw new BusinessRuleException('Datos inválidos.');
        }
        $extraId = (int) $request->input('new_instrument_id', 0);
        $extraPct = $request->input('new_target_pct', '');
        $extraPct = is_scalar($extraPct) ? (string) $extraPct : '';
        if ($extraId > 0 && $extraPct !== '') {
            $targets[$extraId] = $extraPct;
        }
        (new PortfolioService($this->trading))->saveTargets($this->userId(), $id, $targets);

        return $this->redirect('/carteras/' . $id . '#objetivos', 'Distribución objetivo guardada.');
    }

    public function rebalance(Request $request): Response
    {
        $p = Portfolio::findForUser((int) $request->param('id'), $this->userId());
        $v = (new ValuationService($this->market))->value($p);
        $targets = Portfolio::targets((int) $p['id']);
        $map = [];
        foreach ($targets as $t) {
            $map[(int) $t['instrument_id']] = (float) $t['target_pct'];
        }
        $instruments = [];
        foreach (Instrument::findMany(array_keys($map)) as $id => $inst) {
            $inst = $this->market->refreshQuote($inst);
            $inst['fx_rate'] = $this->trading->converter()->rate($inst['currency'], $p['base_currency']);
            $instruments[$id] = $inst;
        }
        $plan = $targets ? (new RebalanceService($this->trading))->plan($v, $map, $instruments) : null;

        return $this->view('portfolios/rebalance', [
            'pageTitle' => 'Rebalanceo · ' . $p['name'],
            'pageScripts' => ['rebalance'],
            'p' => $p,
            'v' => $v,
            'plan' => $plan,
        ]);
    }

    public function executeRebalance(Request $request): Response
    {
        $id = (int) $request->param('id');
        $trades = $request->input('trades', []);
        $done = (new RebalanceService($this->trading))->execute($this->userId(), $id, is_array($trades) ? $trades : []);

        return $this->redirect('/carteras/' . $id, 'Rebalanceo ejecutado: ' . count($done) . (count($done) === 1 ? ' operación.' : ' operaciones.'));
    }
}
