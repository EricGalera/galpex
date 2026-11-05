<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\HttpException;
use App\Models\Instrument;
use App\Models\Portfolio;
use App\Services\Portfolio\PortfolioService;
use App\Services\Portfolio\TradingService;
use App\Support\Database;
use App\Support\Validator;

/**
 * Orquesta el flujo del asesor: cuestionario → perfil → propuestas → cartera.
 */
class AdvisorService
{
    public function __construct(
        private RiskProfiler $profiler = new RiskProfiler(),
        private ?PortfolioBuilder $builder = null,
        private ?TradingService $trading = null,
    ) {
        $this->builder ??= new PortfolioBuilder();
        $this->trading ??= new TradingService();
    }

    /**
     * Evalúa el cuestionario, guarda el perfil y sus propuestas. Devuelve el id del perfil.
     *
     * @param array<string, mixed> $input
     */
    public function submit(int $userId, array $input): int
    {
        $profile = $this->profiler->evaluate($input);
        $proposals = $this->builder->proposals($profile);
        if ($proposals === []) {
            throw new BusinessRuleException('Con esas restricciones no quedan instrumentos para armar una propuesta. Probá quitar alguna.');
        }

        return Database::transaction(function () use ($userId, $profile, $proposals) {
            $profileId = Database::insert('investment_profiles', [
                'user_id' => $userId,
                'answers' => json_encode($profile['answers'] + ['allocation' => $profile['allocation']], JSON_UNESCAPED_UNICODE),
                'capital' => $profile['capital'],
                'currency' => $profile['currency'],
                'horizon_years' => $profile['horizon_years'],
                'risk_tolerance' => $profile['risk_tolerance'],
                'risk_capacity' => $profile['risk_capacity'],
                'risk_score' => $profile['risk_score'],
                'liquidity_need' => $profile['liquidity_need'],
                'income_preference' => $profile['income_preference'],
            ]);
            foreach ($proposals as $p) {
                Database::insert('portfolio_recommendations', [
                    'user_id' => $userId,
                    'profile_id' => $profileId,
                    'strategy_code' => $p['code'],
                    'name' => $p['name'],
                    'allocation' => json_encode($p['allocation'], JSON_UNESCAPED_UNICODE),
                    'analysis' => json_encode([
                        'analysis' => $p['analysis'], 'class_mix' => $p['class_mix'], 'max_drawdown' => $p['max_drawdown'],
                        'stats_window' => $p['stats_window'],
                    ], JSON_UNESCAPED_UNICODE),
                    'fit_score' => $p['fit_score'],
                    'expected_return' => $p['expected_return'],
                    'expected_volatility' => $p['expected_volatility'],
                ]);
            }

            return $profileId;
        });
    }

    /** @return array{profile:array<string,mixed>,proposals:list<array<string,mixed>>} */
    public function load(int $userId, int $profileId): array
    {
        $profile = Database::first('SELECT * FROM investment_profiles WHERE id = ? AND user_id = ?', [$profileId, $userId]);
        if ($profile === null) {
            throw new HttpException(404, 'El perfil no existe.');
        }
        $profile['answers'] = json_decode((string) $profile['answers'], true) ?: [];
        $profile['label'] = RiskProfiler::label((int) $profile['risk_score']);

        $rows = Database::all('SELECT * FROM portfolio_recommendations WHERE profile_id = ? ORDER BY fit_score DESC, id', [$profileId]);
        $proposals = [];
        foreach ($rows as $r) {
            $extra = json_decode((string) $r['analysis'], true) ?: [];
            $proposals[] = [
                'id' => (int) $r['id'],
                'code' => $r['strategy_code'],
                'name' => $r['name'],
                'fit_score' => (int) $r['fit_score'],
                'allocation' => json_decode((string) $r['allocation'], true) ?: [],
                'expected_return' => $r['expected_return'] !== null ? (float) $r['expected_return'] : null,
                'expected_volatility' => $r['expected_volatility'] !== null ? (float) $r['expected_volatility'] : null,
                'accepted_portfolio_id' => $r['accepted_portfolio_id'],
            ] + $extra;
        }

        return ['profile' => $profile, 'proposals' => $proposals];
    }

    /** @return array<string,mixed>|null */
    public function latestProfile(int $userId): ?array
    {
        return Database::first('SELECT * FROM investment_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
    }

    /**
     * Crea una cartera con la propuesta (posiblemente modificada por el usuario)
     * y ejecuta las compras virtuales.
     *
     * @param array<string,mixed> $input name, capital, weights[instrument_id => %]
     * @return array{portfolio_id:int,executed:int,skipped:list<string>}
     */
    public function createPortfolio(int $userId, int $recommendationId, array $input): array
    {
        $rec = Database::first(
            'SELECT r.*, p.currency, p.horizon_years, p.risk_score FROM portfolio_recommendations r
             JOIN investment_profiles p ON p.id = r.profile_id WHERE r.id = ? AND r.user_id = ?',
            [$recommendationId, $userId],
        );
        if ($rec === null) {
            throw new HttpException(404, 'La propuesta no existe.');
        }
        $data = Validator::make($input, [
            'name' => 'required|string|max:80',
            'capital' => 'required|numeric|between:1,100000000000',
        ]);

        $weights = [];
        foreach ((array) ($input['weights'] ?? []) as $id => $w) {
            if (!is_scalar($w)) {
                continue;
            }
            $v = (float) Validator::normalizeNumber((string) $w);
            if ($v > 0) {
                $weights[(int) $id] = $v;
            }
        }
        if ($weights === []) {
            throw new BusinessRuleException('Elegí al menos un activo con peso mayor a cero.');
        }
        if (array_sum($weights) > 100.01) {
            throw new BusinessRuleException('Los pesos suman ' . num(array_sum($weights)) . '%: no pueden superar 100%. Lo que falte queda en efectivo.');
        }
        $instruments = Instrument::findMany(array_keys($weights));
        if (count($instruments) !== count($weights)) {
            throw new BusinessRuleException('Hay instrumentos inválidos en la propuesta.');
        }

        $risk = max(1, min(10, (int) round($rec['risk_score'] / 10)));
        $portfolioId = (new PortfolioService($this->trading))->create($userId, [
            'name' => $data['name'],
            'description' => 'Creada con el asesor: ' . $rec['name'],
            'objective' => match ($rec['strategy_code']) {
                'preservation' => 'preservation', 'income', 'fixed_income' => 'income', 'growth', 'equity', 'tech' => 'growth', 'custom' => 'custom', default => 'balanced',
            },
            'base_currency' => $rec['currency'],
            'initial_capital' => $data['capital'],
            'horizon_years' => (int) $rec['horizon_years'],
            'target_risk' => $risk,
        ]);
        $portfolio = Portfolio::findForUser($portfolioId, $userId);

        $executed = 0;
        $skipped = [];
        foreach ($weights as $id => $w) {
            $inst = $this->trading->market()->refreshQuote($instruments[$id]);
            $amount = (float) $data['capital'] * $w / 100;
            $qty = $inst['price'] !== null
                ? $this->trading->quantityForAmount($inst, $amount, $rec['currency'])
                : 0;
            if ($qty <= 0) {
                $skipped[] = $inst['symbol'];
                continue;
            }
            try {
                $this->trading->execute($portfolioId, $id, 'buy', $qty, ['origin' => 'advisor', 'user_id' => $userId]);
                $executed++;
            } catch (BusinessRuleException) {
                $skipped[] = $inst['symbol'];
            }
        }
        Portfolio::saveTargets($portfolioId, $weights);
        Database::update('portfolio_recommendations', ['accepted_portfolio_id' => $portfolioId], ['id' => $recommendationId]);

        return ['portfolio_id' => $portfolioId, 'executed' => $executed, 'skipped' => $skipped];
    }
}
