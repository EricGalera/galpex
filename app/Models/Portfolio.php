<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\HttpException;
use App\Support\Database;

/**
 * Carteras de inversión. Todo acceso pasa por el dueño (user_id) para que
 * cada usuario vea exclusivamente sus propios datos.
 */
final class Portfolio
{
    public const OBJECTIVES = [
        'preservation' => 'Preservar capital',
        'income' => 'Generar ingresos',
        'growth' => 'Crecimiento',
        'balanced' => 'Balanceada',
        'speculation' => 'Especulativa',
        'custom' => 'Personalizada',
    ];

    public const COLORS = ['#E3A53B', '#5FA8B8', '#9D8BD9', '#C9805B', '#8FA66A', '#B86B8A'];

    /** @return list<array<string,mixed>> */
    public static function forUser(int $userId, bool $includeArchived = false): array
    {
        return Database::all(
            'SELECT * FROM portfolios WHERE user_id = ?' . ($includeArchived ? '' : ' AND is_archived = 0') . ' ORDER BY created_at, id',
            [$userId],
        );
    }

    /**
     * Busca una cartera verificando que pertenezca al usuario.
     *
     * @return array<string,mixed>
     * @throws HttpException 404 si no existe o es de otro usuario
     */
    public static function findForUser(int $id, int $userId): array
    {
        $p = Database::first('SELECT * FROM portfolios WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($p === null) {
            throw new HttpException(404, 'La cartera no existe o no te pertenece.');
        }

        return $p;
    }

    /** @return array<string,mixed>|null */
    public static function lockForUpdate(int $id): ?array
    {
        return Database::first('SELECT * FROM portfolios WHERE id = ? FOR UPDATE', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function targets(int $portfolioId): array
    {
        return Database::all(
            'SELECT t.instrument_id, t.target_pct, i.symbol, i.name FROM portfolio_targets t
             JOIN instruments i ON i.id = t.instrument_id WHERE t.portfolio_id = ? ORDER BY t.target_pct DESC',
            [$portfolioId],
        );
    }

    /** @param array<int,float> $targets instrument_id => % */
    public static function saveTargets(int $portfolioId, array $targets): void
    {
        Database::transaction(function () use ($portfolioId, $targets) {
            Database::query('DELETE FROM portfolio_targets WHERE portfolio_id = ?', [$portfolioId]);
            foreach ($targets as $instrumentId => $pct) {
                if ($pct > 0) {
                    Database::insert('portfolio_targets', [
                        'portfolio_id' => $portfolioId, 'instrument_id' => (int) $instrumentId, 'target_pct' => round($pct, 4),
                    ]);
                }
            }
        });
    }

    /** @return list<int> */
    public static function benchmarkIds(int $portfolioId): array
    {
        return array_map('intval', array_column(
            Database::all('SELECT instrument_id FROM portfolio_benchmarks WHERE portfolio_id = ?', [$portfolioId]),
            'instrument_id',
        ));
    }

    /** @return list<array<string,mixed>> posiciones abiertas con datos del instrumento y cotización */
    public static function positions(int $portfolioId, bool $includeClosed = false): array
    {
        $sql = str_replace(
            'SELECT i.*,',
            'SELECT i.*, pp.id AS position_id, pp.quantity, pp.avg_cost, pp.avg_cost_base, pp.realized_pnl, pp.opened_at,',
            Instrument::SELECT,
        );
        $sql = str_replace('FROM instruments i', 'FROM portfolio_positions pp JOIN instruments i ON i.id = pp.instrument_id', $sql);

        return Database::all(
            $sql . ' WHERE pp.portfolio_id = ?' . ($includeClosed ? '' : ' AND pp.quantity > 0') . ' ORDER BY i.symbol',
            [$portfolioId],
        );
    }

    /**
     * Tenencias de un instrumento en todas las carteras del usuario (ficha del instrumento).
     *
     * @return list<array<string, mixed>>
     */
    public static function holdingsOf(int $userId, int $instrumentId): array
    {
        return Database::all(
            'SELECT pp.*, p.name AS portfolio_name, p.base_currency, p.id AS portfolio_id FROM portfolio_positions pp
             JOIN portfolios p ON p.id = pp.portfolio_id WHERE p.user_id = ? AND pp.instrument_id = ? AND pp.quantity > 0',
            [$userId, $instrumentId],
        );
    }

    /**
     * Acciones, fondos y cripto que el usuario tiene en alguna cartera (para buscar sus noticias).
     *
     * @return list<array<string, mixed>>
     */
    public static function heldCompanies(int $userId): array
    {
        return Database::all(
            "SELECT DISTINCT i.symbol, i.name, c.code AS category_code FROM portfolio_positions pp
             JOIN portfolios p ON p.id = pp.portfolio_id JOIN instruments i ON i.id = pp.instrument_id
             JOIN asset_categories c ON c.id = i.category_id
             WHERE p.user_id = ? AND pp.quantity > 0 AND c.asset_class IN ('equity','fund','crypto')
             ORDER BY i.symbol",
            [$userId],
        );
    }

    /** Cantidad que tiene una cartera de un instrumento (0 si no tiene). */
    public static function heldQuantity(int $portfolioId, int $instrumentId): float
    {
        return (float) (Database::scalar(
            'SELECT quantity FROM portfolio_positions WHERE portfolio_id = ? AND instrument_id = ?',
            [$portfolioId, $instrumentId],
        ) ?? 0);
    }
}
