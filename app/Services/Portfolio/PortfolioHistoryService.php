<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Models\Instrument;
use App\Models\Portfolio;
use App\Services\Analytics\FinancialMath;
use App\Services\MarketData\CurrencyConverter;
use App\Services\MarketData\MarketDataService;
use App\Support\Database;
use DateTimeImmutable;

/**
 * Reconstruye la evolución patrimonial día por día a partir del libro de
 * transacciones y de los precios históricos. No guarda "fotos": se recalcula,
 * por lo que siempre es consistente con las operaciones registradas.
 */
class PortfolioHistoryService
{
    public function __construct(
        private ?MarketDataService $market = null,
        private ?CurrencyConverter $fx = null,
    ) {
        $this->market ??= new MarketDataService();
        $this->fx ??= new CurrencyConverter($this->market);
    }

    /**
     * @return list<array{date:string,value:float,contributions:float,cash:float,flow:float}>
     * @param array<string, mixed> $portfolio
     */
    public function equityCurve(array $portfolio, ?string $targetCurrency = null): array
    {
        $pid = (int) $portfolio['id'];
        $base = (string) $portfolio['base_currency'];
        $target = $targetCurrency ?? $base;

        $txs = Database::all(
            'SELECT type, instrument_id, quantity, price, cash_after, gross_amount, DATE(executed_at) AS d
             FROM transactions WHERE portfolio_id = ? ORDER BY executed_at, id',
            [$pid],
        );
        if ($txs === []) {
            return [];
        }

        $start = (string) $txs[0]['d'];
        $today = date('Y-m-d');
        $instrumentIds = array_values(array_unique(array_filter(array_map(fn($t) => (int) $t['instrument_id'], $txs))));
        $instruments = Instrument::findMany($instrumentIds);

        // Cierres históricos de cada instrumento (+ precio actual para hoy)
        $closes = [];
        $from = (new DateTimeImmutable($start))->modify('-10 days')->format('Y-m-d');
        foreach ($instruments as $id => $inst) {
            $closes[$id] = $this->market->closes($inst, $from, $today);
            $live = $this->market->refreshQuote($inst);
            if ($live['price'] !== null) {
                $closes[$id][$today] = (float) $live['price'];
            }
        }

        // Agrupa transacciones por día
        $byDay = [];
        foreach ($txs as $t) {
            $byDay[$t['d']][] = $t;
        }

        $holdings = [];
        $lastPrice = [];
        $cash = 0.0;
        $contrib = 0.0;
        $curve = [];
        $cursor = array_fill_keys(array_keys($closes), null);

        for ($d = new DateTimeImmutable($start); $d->format('Y-m-d') <= $today; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            $flow = 0.0;
            foreach ($byDay[$date] ?? [] as $t) {
                $cash = (float) $t['cash_after'];
                $iid = (int) $t['instrument_id'];
                switch ($t['type']) {
                    case 'deposit':
                        $contrib += (float) $t['gross_amount'];
                        $flow += (float) $t['gross_amount'];
                        break;
                    case 'withdrawal':
                        $contrib -= (float) $t['gross_amount'];
                        $flow -= (float) $t['gross_amount'];
                        break;
                    case 'buy':
                        $holdings[$iid] = ($holdings[$iid] ?? 0) + (float) $t['quantity'];
                        $lastPrice[$iid] ??= (float) $t['price'];
                        break;
                    case 'sell':
                        $holdings[$iid] = max(0, ($holdings[$iid] ?? 0) - (float) $t['quantity']);
                        break;
                }
            }

            $value = $cash;
            foreach ($holdings as $iid => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                if (isset($closes[$iid][$date])) {
                    $lastPrice[$iid] = $closes[$iid][$date];
                }
                $inst = $instruments[$iid];
                $value += $qty * ($lastPrice[$iid] ?? 0) / Instrument::quoteUnit($inst)
                    * $this->fx->rateOn($inst['currency'], $base, $date);
            }
            // Mantiene actualizados los últimos precios aunque hoy no haya tenencia
            foreach ($closes as $iid => $series) {
                if (isset($series[$date])) {
                    $lastPrice[$iid] = $series[$date];
                }
            }

            $rate = $target === $base ? 1.0 : $this->fx->rateOn($base, $target, $date);
            $curve[] = [
                'date' => $date,
                'value' => round($value * $rate, 2),
                'contributions' => round($contrib * $rate, 2),
                'cash' => round($cash * $rate, 2),
                'flow' => round($flow * $rate, 2),
            ];
        }
        unset($cursor);

        return $curve;
    }

    /**
     * Curva consolidada de todas las carteras del usuario (o de una sola).
     *
     * @return list<array{date:string,value:float,contributions:float,cash:float,flow:float}>
     */
    public function userCurve(int $userId, string $currency, ?int $portfolioId = null): array
    {
        $portfolios = $portfolioId !== null
            ? [Portfolio::findForUser($portfolioId, $userId)]
            : Portfolio::forUser($userId);

        $sum = [];
        foreach ($portfolios as $p) {
            foreach ($this->equityCurve($p, $currency) as $pt) {
                $d = $pt['date'];
                $sum[$d] ??= ['date' => $d, 'value' => 0.0, 'contributions' => 0.0, 'cash' => 0.0, 'flow' => 0.0];
                foreach (['value', 'contributions', 'cash', 'flow'] as $k) {
                    $sum[$d][$k] += $pt[$k];
                }
            }
        }
        ksort($sum);

        // Las carteras que empiezan más tarde no tienen puntos previos: se arrastra el último valor.
        return array_values($sum);
    }

    /**
     * Rentabilidad ponderada por tiempo (TWR): elimina el efecto de ingresos y retiros.
     *
     * @param list<array{date:string,value:float,flow:float}> $curve
     * @return array<string,float> fecha => índice base 100
     */
    public static function twrIndex(array $curve): array
    {
        $index = [];
        $level = 100.0;
        $prev = null;
        foreach ($curve as $pt) {
            if ($prev !== null && $prev > 0) {
                $r = ($pt['value'] - $pt['flow']) / $prev - 1;
                $level *= (1 + $r);
            }
            $index[$pt['date']] = $level;
            $prev = $pt['value'];
        }

        return $index;
    }

    /**
     * Recorta la curva a un rango: 1S, 1M, 3M, 6M, 1A, MAX.
     *
     * @param list<array<string, mixed>> $curve
     * @return list<array<string, mixed>>
     */
    public static function slice(array $curve, string $range): array
    {
        $days = match (strtoupper($range)) {
            '1S' => 7, '1M' => 31, '3M' => 92, '6M' => 183, '1A' => 366, 'YTD' => (int) date('z') + 1, default => null,
        };
        if ($days === null) {
            return $curve;
        }
        $from = (new DateTimeImmutable("-$days days"))->format('Y-m-d');

        return array_values(array_filter($curve, fn($p) => $p['date'] >= $from));
    }

    /**
     * Métricas de desempeño de una curva.
     *
     * @return array<string, float|null>
     * @param list<array<string, mixed>> $curve
     */
    public static function performance(array $curve, float $riskFree): array
    {
        $twr = self::twrIndex($curve);
        $returns = FinancialMath::returns($twr);
        $dd = FinancialMath::maxDrawdown($twr);

        return [
            'twr' => $twr === [] ? 0.0 : end($twr) / 100 - 1,
            'volatility' => FinancialMath::annualizedVolatility($returns, 365),
            'max_drawdown' => $dd['drawdown'],
            'sharpe' => FinancialMath::sharpe($returns, $riskFree, 365),
        ];
    }
}
