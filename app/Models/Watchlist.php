<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\HttpException;
use App\Support\Database;

/** Listas de seguimiento personalizadas. */
final class Watchlist
{
    /** @return list<array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return Database::all(
            'SELECT w.*, (SELECT COUNT(*) FROM watchlist_items wi WHERE wi.watchlist_id = w.id) AS items
             FROM watchlists w WHERE w.user_id = ? ORDER BY w.id',
            [$userId],
        );
    }

    /** @return array<string,mixed> */
    public static function findForUser(int $id, int $userId): array
    {
        $w = Database::first('SELECT * FROM watchlists WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($w === null) {
            throw new HttpException(404, 'La lista no existe.');
        }

        return $w;
    }

    /** @return list<array<string,mixed>> */
    public static function instruments(int $watchlistId): array
    {
        return Database::all(
            str_replace('FROM instruments i', 'FROM watchlist_items wi JOIN instruments i ON i.id = wi.instrument_id', Instrument::SELECT)
                . ' WHERE wi.watchlist_id = ? ORDER BY wi.added_at DESC',
            [$watchlistId],
        );
    }

    /** @return list<int> ids de instrumentos en cualquier lista del usuario */
    public static function watchedIds(int $userId): array
    {
        return array_map('intval', array_column(Database::all(
            'SELECT DISTINCT wi.instrument_id FROM watchlist_items wi JOIN watchlists w ON w.id = wi.watchlist_id WHERE w.user_id = ?',
            [$userId],
        ), 'instrument_id'));
    }

    public static function create(int $userId, string $name): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new BusinessRuleException('El nombre de la lista debe tener entre 1 y 80 caracteres.');
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM watchlists WHERE user_id = ?', [$userId]) >= 10) {
            throw new BusinessRuleException('Podés tener hasta 10 listas.');
        }

        return Database::insert('watchlists', ['user_id' => $userId, 'name' => $name]);
    }

    public static function toggle(int $watchlistId, int $instrumentId): bool
    {
        $exists = Database::scalar('SELECT 1 FROM watchlist_items WHERE watchlist_id = ? AND instrument_id = ?', [$watchlistId, $instrumentId]);
        if ($exists) {
            Database::delete('watchlist_items', ['watchlist_id' => $watchlistId, 'instrument_id' => $instrumentId]);

            return false;
        }
        if (Instrument::find($instrumentId) === null) {
            throw new HttpException(404, 'Instrumento inexistente.');
        }
        Database::insert('watchlist_items', ['watchlist_id' => $watchlistId, 'instrument_id' => $instrumentId]);

        return true;
    }

    public static function delete(int $id): void
    {
        Database::delete('watchlists', ['id' => $id]);
    }
}
