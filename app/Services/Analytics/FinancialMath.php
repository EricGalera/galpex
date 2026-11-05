<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Funciones financieras puras (sin base de datos), verificables con tests.
 * Los retornos se expresan como fracciones (0,05 = 5 %).
 */
final class FinancialMath
{
    public const TRADING_DAYS = 252;

    /**
     * Retornos simples entre precios consecutivos.
     *
     * @param list<float>|array<string,float> $prices
     * @return list<float>
     */
    public static function returns(array $prices): array
    {
        $p = array_values($prices);
        $out = [];
        for ($i = 1, $n = count($p); $i < $n; $i++) {
            if ($p[$i - 1] > 0) {
                $out[] = $p[$i] / $p[$i - 1] - 1;
            }
        }

        return $out;
    }

    /** @param list<float> $values */
    public static function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /**
     * Desviación estándar muestral (n − 1).
     *
     * @param list<float> $values
     */
    public static function stdev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $m = self::mean($values);
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += ($v - $m) ** 2;
        }

        return sqrt($sum / ($n - 1));
    }

    /**
     * Volatilidad anualizada: σ_diaria · √252.
     *
     * @param list<float> $returns
     */
    public static function annualizedVolatility(array $returns, int $periodsPerYear = self::TRADING_DAYS): float
    {
        return self::stdev($returns) * sqrt($periodsPerYear);
    }

    /**
     * Rentabilidad acumulada: Π(1 + r) − 1.
     *
     * @param list<float> $returns
     */
    public static function cumulativeReturn(array $returns): float
    {
        $acc = 1.0;
        foreach ($returns as $r) {
            $acc *= (1 + $r);
        }

        return $acc - 1;
    }

    /**
     * Rentabilidad anualizada geométrica a partir de retornos periódicos.
     *
     * @param list<float> $returns
     */
    public static function annualizedReturn(array $returns, int $periodsPerYear = self::TRADING_DAYS): float
    {
        $n = count($returns);
        if ($n === 0) {
            return 0.0;
        }
        $growth = 1 + self::cumulativeReturn($returns);
        if ($growth <= 0) {
            return -1.0;
        }

        return $growth ** ($periodsPerYear / $n) - 1;
    }

    /** Tasa de crecimiento anual compuesta entre dos valores. */
    public static function cagr(float $start, float $end, float $years): float
    {
        if ($start <= 0 || $years <= 0 || $end <= 0) {
            return 0.0;
        }

        return ($end / $start) ** (1 / $years) - 1;
    }

    /**
     * Sharpe Ratio = (rentabilidad anualizada − tasa libre de riesgo) / volatilidad anualizada.
     *
     * @param list<float> $returns
     */
    public static function sharpe(array $returns, float $riskFree = 0.0, int $periodsPerYear = self::TRADING_DAYS): ?float
    {
        $vol = self::annualizedVolatility($returns, $periodsPerYear);
        if ($vol <= 0) {
            return null;
        }

        return (self::annualizedReturn($returns, $periodsPerYear) - $riskFree) / $vol;
    }

    /**
     * Sortino Ratio: igual que Sharpe pero solo penaliza la volatilidad negativa
     * (desvío a la baja respecto de la tasa libre de riesgo del período).
     *
     * @param list<float> $returns
     */
    public static function sortino(array $returns, float $riskFree = 0.0, int $periodsPerYear = self::TRADING_DAYS): ?float
    {
        $n = count($returns);
        if ($n < 2) {
            return null;
        }
        $target = $riskFree / $periodsPerYear;
        $sum = 0.0;
        foreach ($returns as $r) {
            $d = min(0.0, $r - $target);
            $sum += $d * $d;
        }
        $downside = sqrt($sum / $n) * sqrt($periodsPerYear);
        if ($downside <= 0) {
            return null;
        }

        return (self::annualizedReturn($returns, $periodsPerYear) - $riskFree) / $downside;
    }

    /**
     * Máxima caída desde un pico (Maximum Drawdown).
     *
     * @param list<float>|array<string,float> $values serie de valores (no retornos)
     * @return array{drawdown:float,peak:int|string|null,trough:int|string|null} drawdown negativo (−0,25 = −25 %)
     */
    public static function maxDrawdown(array $values): array
    {
        $peak = null;
        $peakKey = null;
        $result = ['drawdown' => 0.0, 'peak' => null, 'trough' => null];
        foreach ($values as $key => $v) {
            if ($peak === null || $v > $peak) {
                $peak = $v;
                $peakKey = $key;
            }
            if ($peak > 0) {
                $dd = $v / $peak - 1;
                if ($dd < $result['drawdown']) {
                    $result = ['drawdown' => $dd, 'peak' => $peakKey, 'trough' => $key];
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string,float> $values
     * @return array<string,float> fecha => caída desde el máximo previo
     */
    public static function drawdownSeries(array $values): array
    {
        $peak = null;
        $out = [];
        foreach ($values as $k => $v) {
            $peak = $peak === null ? $v : max($peak, $v);
            $out[$k] = $peak > 0 ? $v / $peak - 1 : 0.0;
        }

        return $out;
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function covariance(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n < 2) {
            return 0.0;
        }
        $a = array_slice($a, 0, $n);
        $b = array_slice($b, 0, $n);
        $ma = self::mean($a);
        $mb = self::mean($b);
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += ($a[$i] - $ma) * ($b[$i] - $mb);
        }

        return $sum / ($n - 1);
    }

    /**
     * Coeficiente de correlación de Pearson (−1 a 1).
     *
     * @param array<int|string, float> $a
     * @param array<int|string, float> $b
     */
    public static function correlation(array $a, array $b): ?float
    {
        $sa = self::stdev($a);
        $sb = self::stdev($b);
        if ($sa <= 0 || $sb <= 0) {
            return null;
        }

        return max(-1.0, min(1.0, self::covariance($a, $b) / ($sa * $sb)));
    }

    /**
     * Beta = Cov(activo, benchmark) / Var(benchmark).
     *
     * @param array<int|string, float> $asset
     * @param array<int|string, float> $benchmark
     */
    public static function beta(array $asset, array $benchmark): ?float
    {
        $var = self::stdev($benchmark) ** 2;
        if ($var <= 0) {
            return null;
        }

        return self::covariance($asset, $benchmark) / $var;
    }

    /**
     * Índice de Herfindahl-Hirschman sobre pesos (0..1). 1 = todo en un activo.
     *
     * @param list<float> $weights en fracción o porcentaje (se normalizan)
     */
    public static function herfindahl(array $weights): float
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return 0.0;
        }
        $h = 0.0;
        foreach ($weights as $w) {
            $h += ($w / $total) ** 2;
        }

        return $h;
    }

    /**
     * Alinea varias series por fecha: toma la unión de fechas a partir de la fecha
     * en que TODAS tienen dato y completa huecos con el último valor conocido.
     *
     * @param array<string|int,array<string,float>> $series clave => (fecha => valor)
     * @return array{dates:list<string>,series:array<string|int,list<float>>}
     */
    public static function align(array $series): array
    {
        if ($series === []) {
            return ['dates' => [], 'series' => []];
        }
        $start = null;
        $all = [];
        foreach ($series as $s) {
            if ($s === []) {
                return ['dates' => [], 'series' => array_map(fn() => [], $series)];
            }
            $first = array_key_first($s);
            $start = $start === null ? $first : max($start, $first);
            foreach ($s as $d => $_) {
                $all[$d] = true;
            }
        }
        ksort($all);
        $dates = array_values(array_filter(array_keys($all), fn($d) => $d >= $start));

        $out = [];
        foreach ($series as $key => $s) {
            $last = null;
            // valor vigente al inicio
            foreach ($s as $d => $v) {
                if ($d <= $start) {
                    $last = $v;
                } else {
                    break;
                }
            }
            $values = [];
            foreach ($dates as $d) {
                if (isset($s[$d])) {
                    $last = $s[$d];
                }
                $values[] = (float) $last;
            }
            $out[$key] = $values;
        }

        return ['dates' => $dates, 'series' => $out];
    }

    /**
     * Matriz de covarianzas.
     *
     * @param list<list<float>> $returnsMatrix filas = activos
     * @param list<list<float>> $returnsMatrix
     * @return list<list<float>>
     */
    public static function covarianceMatrix(array $returnsMatrix): array
    {
        $n = count($returnsMatrix);
        $m = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i; $j < $n; $j++) {
                $c = self::covariance($returnsMatrix[$i], $returnsMatrix[$j]);
                $m[$i][$j] = $c;
                $m[$j][$i] = $c;
            }
        }

        return $m;
    }
}
