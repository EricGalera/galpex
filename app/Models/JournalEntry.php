<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\HttpException;
use App\Support\Database;
use App\Support\Validator;

/** Diario de inversiones: el porqué de cada decisión. */
final class JournalEntry
{
    public const TYPES = ['buy' => 'Compra', 'sell' => 'Venta', 'idea' => 'Idea', 'review' => 'Revisión'];

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'entry_type' => 'required|in:buy,sell,idea,review',
            'instrument_id' => 'nullable|integer',
            'transaction_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:2000',
            'objective' => 'nullable|string|max:255',
            'expectation' => 'nullable|string|max:255',
            'risk_taken' => 'nullable|string|max:255',
            'outcome' => 'nullable|string|max:2000',
            'observations' => 'nullable|string|max:2000',
            'rating' => 'nullable|integer|between:1,5',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     * @param array<string, mixed> $filters
     */
    public static function forUser(int $userId, array $filters = []): array
    {
        $where = ['j.user_id = ?'];
        $params = [$userId];
        if (!empty($filters['type']) && isset(self::TYPES[$filters['type']])) {
            $where[] = 'j.entry_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(j.reason LIKE ? OR j.observations LIKE ? OR i.symbol LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        return Database::all(
            'SELECT j.*, i.symbol, i.name AS instrument_name, t.price AS tx_price, t.quantity AS tx_quantity,
                    t.realized_pnl AS tx_realized, t.executed_at AS tx_date, p.base_currency AS tx_currency,
                    q.price AS current_price, i.currency AS instrument_currency
             FROM investment_journal j
             LEFT JOIN instruments i ON i.id = j.instrument_id
             LEFT JOIN transactions t ON t.id = j.transaction_id
             LEFT JOIN portfolios p ON p.id = t.portfolio_id
             LEFT JOIN quotes q ON q.instrument_id = j.instrument_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY j.created_at DESC, j.id DESC LIMIT 300',
            $params,
        );
    }

    /** @return array<string,mixed> */
    public static function findForUser(int $id, int $userId): array
    {
        $e = Database::first('SELECT * FROM investment_journal WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($e === null) {
            throw new HttpException(404, 'La entrada no existe.');
        }

        return $e;
    }

    /**
     * @return array<string,mixed> datos validados con transacción verificada
     * @param array<string, mixed> $input
     */
    public static function validated(int $userId, array $input): array
    {
        $data = Validator::make($input, self::rules());
        if (!empty($data['transaction_id'])) {
            $tx = Database::first(
                'SELECT t.id, t.instrument_id FROM transactions t JOIN portfolios p ON p.id = t.portfolio_id WHERE t.id = ? AND p.user_id = ?',
                [$data['transaction_id'], $userId],
            );
            if ($tx === null) {
                $data['transaction_id'] = null;
            } elseif (empty($data['instrument_id'])) {
                $data['instrument_id'] = $tx['instrument_id'];
            }
        }
        if (!empty($data['instrument_id']) && Instrument::find((int) $data['instrument_id']) === null) {
            $data['instrument_id'] = null;
        }

        return $data;
    }

    /**
     * Autoevaluación del usuario: cantidad de entradas, calificación promedio, compras y ventas.
     *
     * @return array<string, mixed>|null
     */
    public static function stats(int $userId): ?array
    {
        return Database::first(
            "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating,
                    SUM(CASE WHEN entry_type = 'buy' THEN 1 ELSE 0 END) AS buys,
                    SUM(CASE WHEN entry_type = 'sell' THEN 1 ELSE 0 END) AS sells
             FROM investment_journal WHERE user_id = ?",
            [$userId],
        );
    }

    /** @param array<string, mixed> $data campos ya validados con validated() */
    public static function create(int $userId, array $data): int
    {
        return Database::insert('investment_journal', ['user_id' => $userId] + $data);
    }

    /** @param array<string, mixed> $data campos ya validados con validated() */
    public static function updateEntry(int $id, array $data): void
    {
        Database::update('investment_journal', $data, ['id' => $id]);
    }

    public static function deleteEntry(int $id): void
    {
        Database::delete('investment_journal', ['id' => $id]);
    }
}
