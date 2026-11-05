<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Perfiles de inversor que generó el asesor para cada usuario.
 */
final class InvestmentProfile
{
    /**
     * Últimos perfiles del usuario con la cantidad de propuestas de cada uno.
     *
     * @return list<array<string, mixed>>
     */
    public static function historyForUser(int $userId, int $limit = 10): array
    {
        return Database::all(
            'SELECT p.*, (SELECT COUNT(*) FROM portfolio_recommendations r WHERE r.profile_id = p.id) AS proposals
             FROM investment_profiles p WHERE p.user_id = ? ORDER BY p.id DESC LIMIT ' . max(1, $limit),
            [$userId],
        );
    }
}
