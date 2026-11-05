<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Portfolio;
use App\Support\Database;
use App\Support\Validator;

/**
 * Alta, modificación y archivo de carteras.
 */
class PortfolioService
{
    public function __construct(private ?TradingService $trading = null)
    {
        $this->trading ??= new TradingService();
    }

    /**
     * Reglas de validación compartidas por alta y edición.
     *
     * @return array<string, string>
     */
    public static function rules(bool $creating): array
    {
        $rules = [
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'objective' => 'required|in:' . implode(',', array_keys(Portfolio::OBJECTIVES)),
            'horizon_years' => 'required|integer|between:1,40',
            'target_risk' => 'required|integer|between:1,10',
        ];
        if ($creating) {
            $rules['base_currency'] = 'required|in:' . implode(',', (array) config('app.currencies'));
            $rules['initial_capital'] = 'required|numeric|between:1,1000000000000';
        }

        return $rules;
    }

    /** @param array<string,mixed> $input */
    public function create(int $userId, array $input, ?string $createdAt = null): int
    {
        $data = Validator::make($input, self::rules(true));
        $count = (int) Database::scalar('SELECT COUNT(*) FROM portfolios WHERE user_id = ? AND is_archived = 0', [$userId]);
        if ($count >= 20) {
            throw new BusinessRuleException('Llegaste al máximo de 20 carteras activas. Archivá alguna para crear otra.');
        }

        return Database::transaction(function () use ($userId, $data, $count, $createdAt) {
            $id = Database::insert('portfolios', [
                'user_id' => $userId,
                'name' => $data['name'],
                'description' => $data['description'],
                'objective' => $data['objective'],
                'base_currency' => $data['base_currency'],
                'initial_capital' => round((float) $data['initial_capital'], 2),
                'cash_balance' => 0,
                'horizon_years' => $data['horizon_years'],
                'target_risk' => $data['target_risk'],
                'color' => Portfolio::COLORS[$count % count(Portfolio::COLORS)],
                'created_at' => $createdAt ?? now_utc(),
            ]);
            $this->trading->moveCash($id, 'deposit', (float) $data['initial_capital'], $createdAt, 'Capital inicial');

            // Benchmark por defecto según moneda
            $benchmark = Database::scalar(
                "SELECT i.id FROM instruments i JOIN markets m ON m.id = i.market_id WHERE i.symbol = ? AND m.code = 'INDEX'",
                [$data['base_currency'] === 'ARS' ? 'MERV' : 'SPX'],
            );
            if ($benchmark) {
                Database::insert('portfolio_benchmarks', ['portfolio_id' => $id, 'instrument_id' => (int) $benchmark]);
            }

            $hasDefault = Database::scalar('SELECT default_portfolio_id FROM user_profiles WHERE user_id = ?', [$userId]);
            if (!$hasDefault) {
                Database::update('user_profiles', ['default_portfolio_id' => $id], ['user_id' => $userId]);
            }
            AuditLog::record('portfolio.created', ['portfolio_id' => $id, 'name' => $data['name']], $userId);

            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function update(int $userId, int $portfolioId, array $input): void
    {
        Portfolio::findForUser($portfolioId, $userId);
        $data = Validator::make($input, self::rules(false));
        $fields = [
            'name' => $data['name'],
            'description' => $data['description'],
            'objective' => $data['objective'],
            'horizon_years' => $data['horizon_years'],
            'target_risk' => $data['target_risk'],
        ];
        Database::update('portfolios', $fields, ['id' => $portfolioId]);

        if (isset($input['benchmarks']) && is_array($input['benchmarks'])) {
            Database::query('DELETE FROM portfolio_benchmarks WHERE portfolio_id = ?', [$portfolioId]);
            foreach (array_slice(array_unique(array_map('intval', $input['benchmarks'])), 0, 4) as $bid) {
                $ok = Database::scalar('SELECT id FROM instruments WHERE id = ? AND is_benchmark = 1', [$bid]);
                if ($ok) {
                    Database::insert('portfolio_benchmarks', ['portfolio_id' => $portfolioId, 'instrument_id' => $bid]);
                }
            }
        }
    }

    public function archive(int $userId, int $portfolioId): void
    {
        $p = Portfolio::findForUser($portfolioId, $userId);
        $open = (int) Database::scalar('SELECT COUNT(*) FROM portfolio_positions WHERE portfolio_id = ? AND quantity > 0', [$portfolioId]);
        if ($open > 0) {
            throw new BusinessRuleException('Vendé todas las posiciones antes de archivar la cartera.');
        }
        Database::update('portfolios', ['is_archived' => 1], ['id' => $portfolioId]);
        Database::query('UPDATE user_profiles SET default_portfolio_id = NULL WHERE user_id = ? AND default_portfolio_id = ?', [$userId, $portfolioId]);
        AuditLog::record('portfolio.archived', ['portfolio_id' => $portfolioId, 'name' => $p['name']], $userId);
    }

    /**
     * Guarda la distribución objetivo. Los porcentajes deben sumar como máximo 100
     * (el resto queda como efectivo objetivo).
     *
     * @param array<int|string,mixed> $targets
     */
    public function saveTargets(int $userId, int $portfolioId, array $targets): void
    {
        Portfolio::findForUser($portfolioId, $userId);
        $clean = [];
        foreach ($targets as $instrumentId => $pct) {
            if (!is_scalar($pct)) {
                throw new BusinessRuleException('Datos inválidos.');
            }
            $v = is_string($pct) ? (float) Validator::normalizeNumber($pct) : (float) $pct;
            if (!is_finite($v) || $v < 0 || $v > 100) {
                throw new BusinessRuleException('Cada porcentaje objetivo debe estar entre 0 y 100.');
            }
            if ($v > 0) {
                $clean[(int) $instrumentId] = $v;
            }
        }
        if (array_sum($clean) > 100.0001) {
            throw new BusinessRuleException('Los porcentajes objetivo suman ' . num(array_sum($clean)) . '%: no pueden superar 100%.');
        }
        Portfolio::saveTargets($portfolioId, $clean);
    }
}
