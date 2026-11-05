<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\HttpException;
use App\Support\Database;

/**
 * Alertas configuradas por cada usuario, con el precio actual del instrumento.
 */
final class Alert
{
    public const TYPES = [
        'price_above' => 'El precio supera',
        'price_below' => 'El precio baja de',
        'change_pct' => 'La variación diaria supera (±%)',
        'portfolio_drawdown' => 'La cartera cae desde su máximo más de (%)',
        'position_return' => 'Una posición alcanza un rendimiento de (%)',
        'allocation_drift' => 'La distribución se desvía del objetivo más de (pp)',
    ];

    /** @return list<array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return Database::all(
            'SELECT a.*, i.symbol, i.name AS instrument_name, i.currency AS instrument_currency, q.price AS current_price,
                    q.change_pct AS current_change, p.name AS portfolio_name
             FROM alerts a
             LEFT JOIN instruments i ON i.id = a.instrument_id
             LEFT JOIN quotes q ON q.instrument_id = a.instrument_id
             LEFT JOIN portfolios p ON p.id = a.portfolio_id
             WHERE a.user_id = ? ORDER BY a.is_active DESC, a.id DESC',
            [$userId],
        );
    }

    /** @return array<string,mixed> */
    public static function findForUser(int $id, int $userId): array
    {
        $a = Database::first('SELECT * FROM alerts WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($a === null) {
            throw new HttpException(404, 'La alerta no existe.');
        }

        return $a;
    }

    /**
     * Pausa o reactiva una alerta (al reactivarla puede volver a dispararse).
     *
     * @param array<string, mixed> $alert fila obtenida con findForUser()
     */
    public static function toggle(array $alert): void
    {
        Database::update('alerts', ['is_active' => (int) $alert['is_active'] === 1 ? 0 : 1, 'triggered_at' => null], ['id' => (int) $alert['id']]);
    }

    public static function delete(int $id): void
    {
        Database::delete('alerts', ['id' => $id]);
    }
}
