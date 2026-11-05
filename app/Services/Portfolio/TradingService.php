<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Instrument;
use App\Services\MarketData\CurrencyConverter;
use App\Services\MarketData\MarketDataService;
use App\Support\Database;

/**
 * Motor de operaciones virtuales (compra/venta con dinero ficticio).
 *
 * Cada operación, dentro de una transacción de base de datos:
 *   1. valida cantidad, fondos y tenencia;
 *   2. calcula importe, comisión y tipo de cambio;
 *   3. actualiza efectivo, posición (precio promedio) y resultado realizado;
 *   4. registra la orden y el movimiento contable (trazabilidad).
 */
class TradingService
{
    private const EPSILON = 1e-9;

    public function __construct(
        private ?MarketDataService $market = null,
        private ?CurrencyConverter $fx = null,
    ) {
        $this->market ??= new MarketDataService();
        $this->fx ??= new CurrencyConverter($this->market);
    }

    /**
     * @param 'buy'|'sell' $side
     * @param array{origin?:string,executed_at?:string,price?:float,fx_rate?:float,user_id?:int,notes?:string} $options
     *        price/fx_rate/executed_at solo se usan internamente (importaciones desde exchanges).
     * @return array<string,mixed> resumen de la operación
     */
    public function execute(int $portfolioId, int $instrumentId, string $side, float $quantity, array $options = []): array
    {
        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new BusinessRuleException('Tipo de operación inválido.');
        }

        try {
            return Database::transaction(fn() => $this->doExecute($portfolioId, $instrumentId, $side, $quantity, $options));
        } catch (BusinessRuleException $e) {
            // La orden rechazada también queda registrada.
            Database::insert('orders', [
                'portfolio_id' => $portfolioId,
                'instrument_id' => $instrumentId,
                'side' => $side,
                'quantity' => max(0, $quantity),
                'status' => 'rejected',
                'reject_reason' => mb_substr($e->getMessage(), 0, 250),
                'origin' => $options['origin'] ?? 'manual',
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function doExecute(int $portfolioId, int $instrumentId, string $side, float $quantity, array $options): array
    {
        $portfolio = Database::first('SELECT * FROM portfolios WHERE id = ? FOR UPDATE', [$portfolioId]);
        if ($portfolio === null || (int) $portfolio['is_archived'] === 1) {
            throw new BusinessRuleException('La cartera no está disponible para operar.');
        }
        $instrument = Instrument::find($instrumentId);
        if ($instrument === null || (int) $instrument['is_active'] !== 1) {
            throw new BusinessRuleException('El instrumento no está disponible.');
        }
        if (in_array($instrument['category_code'], ['index'], true)) {
            throw new BusinessRuleException('Los índices no se pueden comprar directamente. Usá un ETF que los replique.');
        }

        // Cantidad
        if (!is_finite($quantity) || $quantity <= 0) {
            throw new BusinessRuleException('La cantidad debe ser mayor que cero.');
        }
        if (!Instrument::isFractional($instrument) && abs($quantity - round($quantity)) > self::EPSILON) {
            throw new BusinessRuleException("{$instrument['symbol']} se opera en unidades enteras.");
        }
        $quantity = Instrument::isFractional($instrument) ? round($quantity, 8) : round($quantity);

        // Precio y tipo de cambio: siempre la última cotización real (salvo importaciones con precio propio)
        if (isset($options['price'])) {
            $price = (float) $options['price'];
            $source = (string) ($options['price_source'] ?? 'history');
        } else {
            $instrument = $this->market->refreshQuote($instrument);
            if ($instrument['price'] === null) {
                throw new BusinessRuleException("No hay cotización disponible para {$instrument['symbol']}. Probá más tarde.");
            }
            $price = (float) $instrument['price'];
            $source = (string) $instrument['quote_source'];
        }
        if ($price <= 0) {
            throw new BusinessRuleException('Precio inválido.');
        }
        $fxRate = (float) ($options['fx_rate'] ?? $this->fx->rate($instrument['currency'], $portfolio['base_currency']));
        $unit = Instrument::quoteUnit($instrument);

        $gross = round($quantity * $price / $unit * $fxRate, 2);
        if ($gross < 0.01) {
            throw new BusinessRuleException('El importe de la operación es demasiado chico.');
        }
        $commissionRate = CommissionSchedule::rateFor($instrument);
        $commission = CommissionSchedule::amount($gross, $commissionRate);
        $cash = (float) $portfolio['cash_balance'];

        $position = Database::first(
            'SELECT * FROM portfolio_positions WHERE portfolio_id = ? AND instrument_id = ? FOR UPDATE',
            [$portfolioId, $instrumentId],
        );
        $heldQty = $position ? (float) $position['quantity'] : 0.0;
        $realized = null;

        if ($side === 'buy') {
            $total = $gross + $commission;
            if ($total > $cash + 0.005) {
                throw new BusinessRuleException(sprintf(
                    'Fondos insuficientes: la compra requiere %s y tenés %s disponibles.',
                    money($total, $portfolio['base_currency']),
                    money($cash, $portfolio['base_currency']),
                ));
            }
            $cashEffect = -$total;
            $newQty = $heldQty + $quantity;
            $avgCost = $position ? ((float) $position['avg_cost'] * $heldQty + $price * $quantity) / $newQty : $price;
            $avgCostBase = $position
                ? ((float) $position['avg_cost_base'] * $heldQty + $total) / $newQty
                : $total / $quantity;
            $realizedTotal = $position ? (float) $position['realized_pnl'] : 0.0;
        } else {
            if ($quantity > $heldQty + self::EPSILON) {
                throw new BusinessRuleException(sprintf(
                    'No podés vender %s %s: tu tenencia es de %s.',
                    num($quantity, Instrument::isFractional($instrument) ? 4 : 0),
                    $instrument['symbol'],
                    num($heldQty, Instrument::isFractional($instrument) ? 4 : 0),
                ));
            }
            $proceeds = $gross - $commission;
            $cashEffect = $proceeds;
            $costOfSold = (float) $position['avg_cost_base'] * $quantity;
            $realized = round($proceeds - $costOfSold, 2);
            $newQty = max(0.0, $heldQty - $quantity);
            if ($newQty < self::EPSILON) {
                $newQty = 0.0;
            }
            $avgCost = (float) $position['avg_cost'];
            $avgCostBase = (float) $position['avg_cost_base'];
            $realizedTotal = (float) $position['realized_pnl'] + $realized;
        }

        $cashAfter = round($cash + $cashEffect, 2);
        $executedAt = $options['executed_at'] ?? now_utc();

        Database::update('portfolios', ['cash_balance' => $cashAfter], ['id' => $portfolioId]);

        if ($position) {
            Database::update('portfolio_positions', [
                'quantity' => $newQty,
                'avg_cost' => $avgCost,
                'avg_cost_base' => $avgCostBase,
                'realized_pnl' => round($realizedTotal, 2),
            ], ['id' => (int) $position['id']]);
        } else {
            Database::insert('portfolio_positions', [
                'portfolio_id' => $portfolioId,
                'instrument_id' => $instrumentId,
                'quantity' => $newQty,
                'avg_cost' => $avgCost,
                'avg_cost_base' => $avgCostBase,
                'realized_pnl' => 0,
                'opened_at' => $executedAt,
            ]);
        }

        $orderId = Database::insert('orders', [
            'portfolio_id' => $portfolioId,
            'instrument_id' => $instrumentId,
            'side' => $side,
            'quantity' => $quantity,
            'status' => 'filled',
            'origin' => $options['origin'] ?? 'manual',
            'created_at' => $executedAt,
        ]);

        $txId = Database::insert('transactions', [
            'portfolio_id' => $portfolioId,
            'order_id' => $orderId,
            'instrument_id' => $instrumentId,
            'type' => $side,
            'quantity' => $quantity,
            'price' => $price,
            'fx_rate' => $fxRate,
            'gross_amount' => $gross,
            'commission' => $commission,
            'cash_effect' => round($cashEffect, 2),
            'realized_pnl' => $realized,
            'cash_after' => $cashAfter,
            'price_source' => $source,
            'notes' => $options['notes'] ?? null,
            'executed_at' => $executedAt,
        ]);

        if (!isset($options['executed_at'])) {
            AuditLog::record('trade.' . $side, [
                'portfolio_id' => $portfolioId, 'symbol' => $instrument['symbol'], 'quantity' => $quantity, 'price' => $price,
            ], $options['user_id'] ?? (int) $portfolio['user_id']);
        }

        return [
            'transaction_id' => $txId,
            'order_id' => $orderId,
            'side' => $side,
            'symbol' => $instrument['symbol'],
            'quantity' => $quantity,
            'price' => $price,
            'currency' => $instrument['currency'],
            'fx_rate' => $fxRate,
            'gross' => $gross,
            'commission' => $commission,
            'commission_rate' => $commissionRate,
            'cash_after' => $cashAfter,
            'realized_pnl' => $realized,
            'base_currency' => $portfolio['base_currency'],
        ];
    }

    /** Ingreso o retiro de efectivo virtual. */
    public function moveCash(int $portfolioId, string $type, float $amount, ?string $executedAt = null, ?string $notes = null): float
    {
        if (!in_array($type, ['deposit', 'withdrawal'], true)) {
            throw new BusinessRuleException('Movimiento inválido.');
        }
        if (!is_finite($amount) || $amount <= 0) {
            throw new BusinessRuleException('El monto debe ser mayor que cero.');
        }
        $amount = round($amount, 2);

        return Database::transaction(function () use ($portfolioId, $type, $amount, $executedAt, $notes) {
            $p = Database::first('SELECT * FROM portfolios WHERE id = ? FOR UPDATE', [$portfolioId]);
            if ($p === null) {
                throw new BusinessRuleException('La cartera no existe.');
            }
            $cash = (float) $p['cash_balance'];
            if ($type === 'withdrawal' && $amount > $cash + 0.005) {
                throw new BusinessRuleException('No podés retirar más que el efectivo disponible (' . money($cash, $p['base_currency']) . ').');
            }
            $effect = $type === 'deposit' ? $amount : -$amount;
            $after = round($cash + $effect, 2);
            Database::update('portfolios', ['cash_balance' => $after], ['id' => $portfolioId]);
            Database::insert('transactions', [
                'portfolio_id' => $portfolioId,
                'type' => $type,
                'gross_amount' => $amount,
                'cash_effect' => $effect,
                'cash_after' => $after,
                'notes' => $notes,
                'executed_at' => $executedAt ?? now_utc(),
            ]);

            return $after;
        });
    }

    /**
     * Cantidad máxima comprable con un monto (en moneda base), descontando la comisión
     * que corresponde al tipo de instrumento.
     * @param array<string, mixed> $instrument
     */
    public function quantityForAmount(array $instrument, float $amountBase, string $baseCurrency): float
    {
        $commissionRate = CommissionSchedule::rateFor($instrument);
        $price = (float) $instrument['price'];
        if ($price <= 0) {
            return 0.0;
        }
        $fxRate = $this->fx->rate($instrument['currency'], $baseCurrency);
        $unitCost = $price / Instrument::quoteUnit($instrument) * $fxRate * (1 + $commissionRate);
        $qty = $amountBase / $unitCost;

        return Instrument::isFractional($instrument) ? floor($qty * 1e6) / 1e6 : floor($qty);
    }

    public function converter(): CurrencyConverter
    {
        return $this->fx;
    }

    public function market(): MarketDataService
    {
        return $this->market;
    }
}
