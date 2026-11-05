<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Exceptions\BusinessRuleException;
use App\Models\Alert;
use App\Models\Instrument;
use App\Models\Notification;
use App\Models\Portfolio;
use App\Services\MarketData\Cache;
use App\Services\MarketData\MarketDataService;
use App\Services\Portfolio\PortfolioHistoryService;
use App\Services\Portfolio\ValuationService;
use App\Support\Database;
use App\Support\Logger;
use App\Support\Validator;
use Throwable;

/**
 * Alta y evaluación de alertas. La evaluación se dispara al navegar (como máximo
 * una vez por minuto por usuario) y desde la tarea programada "php artisan galpex:alerts".
 */
class AlertService
{
    public function __construct(private ?MarketDataService $market = null)
    {
        $this->market ??= new MarketDataService();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(int $userId, array $input): int
    {
        $data = Validator::make($input, [
            'type' => 'required|in:' . implode(',', array_keys(Alert::TYPES)),
            'instrument_id' => 'nullable|integer',
            'portfolio_id' => 'nullable|integer',
            'threshold' => 'required|numeric',
            'note' => 'nullable|string|max:160',
            'repeat_alert' => 'nullable|in:0,1',
        ]);
        $type = $data['type'];
        $needsInstrument = in_array($type, ['price_above', 'price_below', 'change_pct'], true);
        $needsPortfolio = in_array($type, ['portfolio_drawdown', 'position_return', 'allocation_drift'], true);

        if ($needsInstrument && (empty($data['instrument_id']) || Instrument::find((int) $data['instrument_id']) === null)) {
            throw new BusinessRuleException('Elegí el instrumento para esta alerta.');
        }
        if ($needsPortfolio) {
            if (empty($data['portfolio_id'])) {
                throw new BusinessRuleException('Elegí la cartera para esta alerta.');
            }
            Portfolio::findForUser((int) $data['portfolio_id'], $userId);
        }
        if (in_array($type, ['price_above', 'price_below', 'change_pct', 'portfolio_drawdown', 'allocation_drift'], true) && $data['threshold'] <= 0) {
            throw new BusinessRuleException('El valor de la alerta debe ser mayor que cero.');
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM alerts WHERE user_id = ? AND is_active = 1', [$userId]) >= 50) {
            throw new BusinessRuleException('Podés tener hasta 50 alertas activas.');
        }

        return Database::insert('alerts', [
            'user_id' => $userId,
            'type' => $type,
            'instrument_id' => $needsInstrument ? (int) $data['instrument_id'] : ($type === 'position_return' && !empty($data['instrument_id']) ? (int) $data['instrument_id'] : null),
            'portfolio_id' => $needsPortfolio ? (int) $data['portfolio_id'] : null,
            'threshold' => $data['threshold'],
            'note' => $data['note'],
            'repeat_alert' => (int) ($data['repeat_alert'] ?? 0),
        ]);
    }

    /** Evalúa con un límite de frecuencia por usuario. Devuelve cuántas se dispararon. */
    public function evaluateThrottled(int $userId, int $seconds = 60): int
    {
        $key = "alerts-eval:$userId";
        if (Cache::get($key) !== null) {
            return 0;
        }
        Cache::put($key, 1, $seconds);

        return $this->evaluateForUser($userId);
    }

    public function evaluateForUser(int $userId): int
    {
        $alerts = Database::all('SELECT * FROM alerts WHERE user_id = ? AND is_active = 1', [$userId]);
        $fired = 0;
        $valuations = [];

        foreach ($alerts as $a) {
            // Alertas repetitivas: como máximo una vez por día
            if ($a['triggered_at'] !== null && (time() - strtotime($a['triggered_at'] . ' UTC')) < 86400) {
                continue;
            }
            try {
                [$hit, $value, $message] = $this->check($a, $valuations);
            } catch (Throwable $e) {
                Logger::warning('No se pudo evaluar alerta', ['alert' => $a['id'], 'error' => $e->getMessage()]);
                continue;
            }
            Database::update('alerts', ['observed_value' => $value, 'last_checked_at' => now_utc()], ['id' => (int) $a['id']]);
            if (!$hit) {
                continue;
            }
            $fired++;
            Database::update('alerts', [
                'triggered_at' => now_utc(),
                'is_active' => (int) $a['repeat_alert'] === 1 ? 1 : 0,
            ], ['id' => (int) $a['id']]);
            $link = $a['instrument_id'] ? '/mercados/instrumento/' . $a['instrument_id'] : ($a['portfolio_id'] ? '/carteras/' . $a['portfolio_id'] : '/alertas');
            Notification::create($userId, $message, $a['note'], $link, 'alert', (int) $a['id']);
        }

        return $fired;
    }

    /**
     * @param array<int,array<string,mixed>> $valuations caché de valuaciones por cartera
     * @return array{0:bool,1:?float,2:string}
     * @param array<string, mixed> $a
     */
    private function check(array $a, array &$valuations): array
    {
        $threshold = (float) $a['threshold'];

        if (in_array($a['type'], ['price_above', 'price_below', 'change_pct'], true)) {
            $inst = Instrument::find((int) $a['instrument_id']);
            $inst = $this->market->refreshQuote($inst);
            if ($inst['price'] === null) {
                return [false, null, ''];
            }
            $price = (float) $inst['price'];
            $sym = $inst['symbol'];
            $suffix = '';

            return match ($a['type']) {
                'price_above' => [$price >= $threshold, $price, "$sym superó " . money($threshold, $inst['currency']) . ': cotiza ' . money($price, $inst['currency']) . $suffix],
                'price_below' => [$price <= $threshold, $price, "$sym bajó de " . money($threshold, $inst['currency']) . ': cotiza ' . money($price, $inst['currency']) . $suffix],
                default => [abs((float) $inst['change_pct']) >= $threshold, (float) $inst['change_pct'], "$sym varía " . pct((float) $inst['change_pct']) . ' en el día' . $suffix],
            };
        }

        $pid = (int) $a['portfolio_id'];
        $portfolio = Database::first('SELECT * FROM portfolios WHERE id = ?', [$pid]);
        if ($portfolio === null) {
            return [false, null, ''];
        }
        $valuations[$pid] ??= (new ValuationService($this->market))->value($portfolio);
        $v = $valuations[$pid];

        switch ($a['type']) {
            case 'portfolio_drawdown':
                $curve = (new PortfolioHistoryService($this->market))->equityCurve($portfolio);
                $twr = PortfolioHistoryService::twrIndex($curve);
                if ($twr === []) {
                    return [false, null, ''];
                }
                $dd = (end($twr) / max($twr) - 1) * 100;

                return [-$dd >= $threshold, $dd, sprintf('La cartera %s cae %s desde su máximo', $portfolio['name'], pct($dd))];

            case 'position_return':
                foreach ($v['positions'] as $p) {
                    if ($a['instrument_id'] && (int) $p['id'] !== (int) $a['instrument_id']) {
                        continue;
                    }
                    $r = $p['unrealized_pct'];
                    if ($r !== null && (($threshold >= 0 && $r >= $threshold) || ($threshold < 0 && $r <= $threshold))) {
                        return [true, $r, sprintf('%s rinde %s en %s', $p['symbol'], pct($r), $portfolio['name'])];
                    }
                }

                return [false, null, ''];

            case 'allocation_drift':
                $targets = Portfolio::targets($pid);
                if ($targets === []) {
                    return [false, null, ''];
                }
                $current = [];
                foreach ($v['positions'] as $p) {
                    $current[(int) $p['id']] = $p['weight'];
                }
                $max = 0.0;
                $worst = '';
                foreach ($targets as $t) {
                    $d = abs(($current[(int) $t['instrument_id']] ?? 0) - (float) $t['target_pct']);
                    if ($d > $max) {
                        $max = $d;
                        $worst = $t['symbol'];
                    }
                }

                return [$max >= $threshold, $max, sprintf('%s se desvía %s pp del objetivo en %s', $worst, num($max, 1), $portfolio['name'])];
        }

        return [false, null, ''];
    }
}
