<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Exceptions\BusinessRuleException;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Support\Database;

/**
 * Rebalanceo: compara la distribución actual con la objetivo y propone
 * las operaciones necesarias para volver a los porcentajes deseados.
 */
class RebalanceService
{
    public function __construct(
        private ?TradingService $trading = null,
        private float $tolerance = 1.0, // puntos porcentuales para considerar "en objetivo"
    ) {
        $this->trading ??= new TradingService();
    }

    /**
     * Calcula el plan (función pura sobre la valuación y los objetivos).
     *
     * @param array<string,mixed> $valuation ValuationService::value()
     * @param array<int,float> $targets instrument_id => % objetivo
     * @param array<int,array<string,mixed>> $instruments instrumentos con precio (para objetivos sin posición)
     * @return array<string,mixed>
     */
    public function plan(array $valuation, array $targets, array $instruments): array
    {
        $total = (float) $valuation['total_value'];
        $rows = [];
        $byId = [];
        foreach ($valuation['positions'] as $p) {
            $byId[(int) $p['id']] = $p;
        }
        $ids = array_unique(array_merge(array_keys($byId), array_keys($targets)));

        $sells = 0.0;
        $buys = 0.0;
        $fees = 0.0;
        foreach ($ids as $id) {
            $p = $byId[$id] ?? null;
            $inst = $p ?? ($instruments[$id] ?? null);
            if ($inst === null || $inst['price'] === null) {
                continue;
            }
            $currentValue = (float) ($p['market_value'] ?? 0);
            $currentPct = $total > 0 ? $currentValue / $total * 100 : 0;
            $targetPct = (float) ($targets[$id] ?? 0);
            $targetValue = $total * $targetPct / 100;
            $fx = (float) ($p['fx_rate'] ?? $inst['fx_rate'] ?? 1);
            $unitValue = (float) $inst['price'] / Instrument::quoteUnit($inst) * $fx;
            $diff = $targetValue - $currentValue;

            $qty = $unitValue > 0 ? $diff / $unitValue : 0;
            $qty = Instrument::isFractional($inst)
                ? round($qty, 6)
                : ($qty > 0 ? floor($qty) : -min((float) ($p['quantity'] ?? 0), round(abs($qty))));
            if ($targetPct == 0.0 && $p) {
                $qty = -(float) $p['quantity']; // salir por completo
            }
            $tradeValue = $qty * $unitValue;
            $status = abs($currentPct - $targetPct) <= $this->tolerance ? 'ok' : ($currentPct > $targetPct ? 'over' : 'under');
            if ($status === 'ok') {
                $qty = 0;
                $tradeValue = 0;
            }

            if ($tradeValue < 0) {
                $sells += -$tradeValue;
            } else {
                $buys += $tradeValue;
            }
            $commissionRate = CommissionSchedule::rateFor($inst);
            $fees += abs($tradeValue) * $commissionRate;

            $rows[] = [
                'id' => (int) $id,
                'symbol' => $inst['symbol'],
                'name' => $inst['name'],
                'price' => (float) $inst['price'],
                'currency' => $inst['currency'],
                'fractional' => Instrument::isFractional($inst),
                'unit_value' => $unitValue,
                'current_value' => $currentValue,
                'current_pct' => $currentPct,
                'target_pct' => $targetPct,
                'target_value' => $targetValue,
                'difference' => $diff,
                'status' => $status,
                'quantity' => $qty,
                'trade_value' => $tradeValue,
                'commission_rate' => $commissionRate,
            ];
        }
        usort($rows, fn($a, $b) => $a['trade_value'] <=> $b['trade_value']); // ventas primero

        $cashAfter = (float) $valuation['cash'] + $sells - $buys - $fees;
        $final = [];
        foreach ($rows as $r) {
            $v = $r['current_value'] + $r['trade_value'];
            $final[] = ['symbol' => $r['symbol'], 'value' => $v, 'pct' => $total > 0 ? $v / $total * 100 : 0];
        }
        $final[] = ['symbol' => 'Efectivo', 'value' => max(0, $cashAfter), 'pct' => $total > 0 ? max(0, $cashAfter) / $total * 100 : 0];

        return [
            'rows' => $rows,
            'total_value' => $total,
            'cash' => (float) $valuation['cash'],
            'sells' => $sells,
            'buys' => $buys,
            'fees' => $fees,
            'cash_after' => $cashAfter,
            // Si el efectivo no alcanza, cuánto habría que ingresar
            'capital_required' => max(0, -$cashAfter),
            'final' => $final,
            'targets_sum' => array_sum($targets),
        ];
    }

    /**
     * Ejecuta las operaciones aceptadas (ventas primero para liberar efectivo).
     *
     * @param array<int|string,float|string> $trades instrument_id => cantidad con signo (+ compra, − venta)
     * @return list<array<string,mixed>>
     */
    public function execute(int $userId, int $portfolioId, array $trades): array
    {
        Portfolio::findForUser($portfolioId, $userId);
        $clean = [];
        foreach ($trades as $id => $qty) {
            if (!is_scalar($qty)) {
                continue;
            }
            $q = (float) \App\Support\Validator::normalizeNumber((string) $qty);
            if (!is_finite($q) || abs($q) > 1e12) {
                throw new BusinessRuleException('Cantidad inválida en el rebalanceo.');
            }
            if (abs($q) > 0) {
                $clean[(int) $id] = $q;
            }
        }
        if ($clean === []) {
            throw new BusinessRuleException('No hay operaciones para ejecutar.');
        }
        asort($clean); // negativas (ventas) primero

        return Database::transaction(function () use ($portfolioId, $clean, $userId) {
            $done = [];
            foreach ($clean as $id => $q) {
                $done[] = $this->trading->execute($portfolioId, $id, $q < 0 ? 'sell' : 'buy', abs($q), [
                    'origin' => 'rebalance', 'user_id' => $userId,
                ]);
            }

            return $done;
        });
    }
}
