<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Movimientos de las carteras: compras, ventas, ingresos, retiros y dividendos.
 * Siempre se consultan a través de carteras del usuario.
 */
final class Transaction
{
    /** Tipos de movimiento que se pueden filtrar en la pantalla de la cartera. */
    public const TYPES = ['buy', 'sell', 'deposit', 'withdrawal', 'dividend'];

    /**
     * Últimos movimientos de todas las carteras del usuario.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentForUser(int $userId, int $limit = 7): array
    {
        return Database::all(
            'SELECT t.*, i.symbol, p.name AS portfolio_name, p.base_currency
             FROM transactions t JOIN portfolios p ON p.id = t.portfolio_id LEFT JOIN instruments i ON i.id = t.instrument_id
             WHERE p.user_id = ? ORDER BY t.executed_at DESC, t.id DESC LIMIT ' . max(1, $limit),
            [$userId],
        );
    }

    /**
     * Últimas compras y ventas del usuario (para vincularlas con el diario).
     *
     * @return list<array<string, mixed>>
     */
    public static function recentTradesForUser(int $userId, int $limit = 40): array
    {
        return Database::all(
            "SELECT t.id, t.type, t.quantity, t.price, t.executed_at, i.symbol, i.id AS instrument_id, p.name AS portfolio_name
             FROM transactions t JOIN portfolios p ON p.id = t.portfolio_id JOIN instruments i ON i.id = t.instrument_id
             WHERE p.user_id = ? AND t.type IN ('buy','sell') ORDER BY t.executed_at DESC LIMIT " . max(1, $limit),
            [$userId],
        );
    }

    /**
     * Historial de una cartera, opcionalmente filtrado por tipo de movimiento.
     *
     * @return list<array<string, mixed>>
     */
    public static function forPortfolio(int $portfolioId, ?string $type = null, int $limit = 300): array
    {
        $where = '';
        $params = [$portfolioId];
        if ($type !== null && in_array($type, self::TYPES, true)) {
            $where = ' AND t.type = ?';
            $params[] = $type;
        }

        return Database::all(
            "SELECT t.*, i.symbol, i.name AS instrument_name, i.currency AS instrument_currency, o.origin
             FROM transactions t LEFT JOIN instruments i ON i.id = t.instrument_id LEFT JOIN orders o ON o.id = t.order_id
             WHERE t.portfolio_id = ? $where ORDER BY t.executed_at DESC, t.id DESC LIMIT " . max(1, $limit),
            $params,
        );
    }

    /**
     * Órdenes rechazadas de una cartera (por ejemplo, por fondos insuficientes).
     *
     * @return list<array<string, mixed>>
     */
    public static function rejectedOrders(int $portfolioId, int $limit = 10): array
    {
        return Database::all(
            "SELECT o.*, i.symbol FROM orders o JOIN instruments i ON i.id = o.instrument_id
             WHERE o.portfolio_id = ? AND o.status = 'rejected' ORDER BY o.id DESC LIMIT " . max(1, $limit),
            [$portfolioId],
        );
    }
}
