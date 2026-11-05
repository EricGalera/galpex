<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\HttpException;
use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Services\Analytics\FinancialMath;
use App\Services\MarketData\MarketDataService;
use App\Services\Portfolio\CommissionSchedule;
use App\Services\Portfolio\PortfolioHistoryService;
use App\Services\Portfolio\TradingService;
use App\Services\Portfolio\ValuationService;
use App\Support\Validator;

/**
 * API JSON de carteras: evolución del patrimonio, distribución y previsualización de una orden antes de confirmarla.
 */
final class PortfolioApiController extends Controller
{
    /** Evolución patrimonial consolidada o de una cartera. */
    public function equity(Request $request): Response
    {
        $userId = $this->userId();
        $portfolioId = $request->query('cartera') ? (int) $request->query('cartera') : null;
        $currency = $this->currency();
        if ($portfolioId !== null) {
            $currency = (string) Portfolio::findForUser($portfolioId, $userId)['base_currency'];
            if ($request->query('moneda') === 'principal') {
                $currency = $this->currency();
            }
        }
        $curve = (new PortfolioHistoryService())->userCurve($userId, $currency, $portfolioId);
        $range = (string) $request->query('rango', '3M');
        $points = PortfolioHistoryService::slice($curve, $range);

        $twr = PortfolioHistoryService::twrIndex($points);
        $summary = ['twr' => 0.0, 'change' => 0.0, 'max_drawdown' => 0.0];
        if (count($points) > 1) {
            $summary = [
                'twr' => end($twr) / reset($twr) - 1,
                'change' => end($points)['value'] - $points[0]['value'],
                'max_drawdown' => FinancialMath::maxDrawdown($twr)['drawdown'],
            ];
        }

        return $this->json(['currency' => $currency, 'points' => $points, 'summary' => $summary]);
    }

    public function allocation(Request $request): Response
    {
        $p = Portfolio::findForUser((int) $request->param('id'), $this->userId());
        $v = (new ValuationService())->value($p);

        return $this->json(['currency' => $v['currency'], 'allocation' => $v['allocation']]);
    }

    /** Previsualiza una orden: importe, comisión, tipo de cambio y validaciones. */
    public function preview(Request $request): Response
    {
        $p = Portfolio::findForUser((int) $request->param('id'), $this->userId());
        $inst = Instrument::find((int) $request->query('instrumento', 0));
        if ($inst === null) {
            throw new HttpException(404, 'Elegí un instrumento.');
        }
        $market = new MarketDataService();
        $trading = new TradingService($market);
        $inst = $market->refreshQuote($inst);
        if ($inst['price'] === null) {
            throw new BusinessRuleException('No hay cotización disponible para ' . $inst['symbol'] . '.');
        }
        $side = $request->query('lado') === 'sell' ? 'sell' : 'buy';
        $qtyRaw = (string) $request->query('cantidad', '');
        $amountRaw = (string) $request->query('monto', '');
        $fx = $trading->converter()->rate($inst['currency'], $p['base_currency']);
        $unit = Instrument::quoteUnit($inst);
        $rate = CommissionSchedule::rateFor($inst);

        if ($qtyRaw === '' && $amountRaw !== '') {
            $qty = $trading->quantityForAmount($inst, (float) Validator::normalizeNumber($amountRaw), $p['base_currency']);
        } else {
            $qty = (float) Validator::normalizeNumber($qtyRaw ?: '0');
        }
        $gross = round($qty * (float) $inst['price'] / $unit * $fx, 2);
        $commission = CommissionSchedule::amount($gross, $rate);
        $held = Portfolio::heldQuantity((int) $p['id'], (int) $inst['id']);
        $total = $side === 'buy' ? $gross + $commission : $gross - $commission;

        $problems = [];
        if ($qty <= 0) {
            $problems[] = 'Ingresá una cantidad mayor que cero.';
        }
        if (!Instrument::isFractional($inst) && abs($qty - round($qty)) > 1e-9) {
            $problems[] = $inst['symbol'] . ' se opera en unidades enteras.';
        }
        if ($side === 'buy' && $total > (float) $p['cash_balance']) {
            $problems[] = 'Fondos insuficientes: tenés ' . money($p['cash_balance'], $p['base_currency']) . '.';
        }
        if ($side === 'sell' && $qty > $held + 1e-9) {
            $problems[] = 'Tu tenencia es de ' . num($held, Instrument::isFractional($inst) ? 6 : 0) . '.';
        }

        return $this->json([
            'instrument' => MarketApiController::present($inst),
            'quantity' => $qty,
            'held' => $held,
            'price' => (float) $inst['price'],
            'unit' => $unit,
            'fx_rate' => $fx,
            'gross' => $gross,
            'commission' => $commission,
            'commission_rate' => $rate,
            'total' => $total,
            'cash' => (float) $p['cash_balance'],
            'currency' => $p['base_currency'],
            'fractional' => Instrument::isFractional($inst),
            'max_buy' => $trading->quantityForAmount($inst, (float) $p['cash_balance'], $p['base_currency']),
            'problems' => $problems,
        ]);
    }
}
