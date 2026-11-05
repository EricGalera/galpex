<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Exceptions\BusinessRuleException;
use App\Services\MarketData\CurrencyConverter;

/**
 * Simulador de escenarios de estrés. Aplica shocks a cada posición y estima
 * el nuevo valor de la cartera. Todos los supuestos son SIMPLIFICADOS y se
 * informan junto con el resultado.
 */
final class ScenarioSimulator
{
    /** Duración estimada por categoría cuando el instrumento no la informa. */
    private const DEFAULT_DURATION = [
        'etf_bond' => 6.0, 'bond_sov' => 4.5, 'bond_corp' => 5.0, 'on' => 3.0, 'fci' => 0.3,
    ];

    /**
     * Sensibilidad de la renta variable a +1 punto porcentual de tasa (supuesto).
     */
    private const EQUITY_RATE_SENSITIVITY = [
        'Tecnología' => -0.045, 'Comunicaciones' => -0.035, 'Consumo discrecional' => -0.035,
        'Servicios públicos' => -0.03, 'Financiero' => 0.01, 'default' => -0.025, 'crypto' => -0.06,
    ];

    /**
     * @param list<array<string,mixed>> $positions posiciones valuadas (market_value en moneda base)
     * @param array<int,float> $betas instrument_id => beta estimada (si falta se usa la de la categoría)
     * @param array{type:string,shock:float,currency?:string,sector?:string,instrument_id?:int} $scenario
     *        shock en porcentaje (20 = 20 %), o en puntos básicos para tasas (100 = +1 %)
     * @return array<string,mixed>
     */
    public function run(array $positions, float $cash, string $baseCurrency, array $scenario, array $betas = []): array
    {
        $type = $scenario['type'];
        $shock = (float) $scenario['shock'];
        if ($shock < 0 || $shock > ($type === 'rate_hike' ? 1000 : 100)) {
            throw new BusinessRuleException('El tamaño del shock está fuera de rango.');
        }

        $rows = [];
        $before = $cash;
        $after = $cash;
        foreach ($positions as $p) {
            $value = (float) ($p['market_value'] ?? 0);
            if ($value <= 0) {
                continue;
            }
            [$change, $why] = $this->impact($p, $type, $shock, $baseCurrency, $scenario, $betas);
            $newValue = $value * (1 + $change);
            $rows[] = [
                'id' => (int) $p['id'],
                'symbol' => $p['symbol'],
                'name' => $p['name'],
                'category' => $p['category_name'],
                'value' => $value,
                'change_pct' => $change * 100,
                'new_value' => $newValue,
                'impact' => $newValue - $value,
                'reason' => $why,
            ];
            $before += $value;
            $after += $newValue;
        }
        usort($rows, fn($a, $b) => $a['impact'] <=> $b['impact']);

        return [
            'scenario' => $scenario,
            'title' => $this->title($scenario),
            'value_before' => $before,
            'value_after' => $after,
            'impact' => $after - $before,
            'impact_pct' => $before > 0 ? ($after / $before - 1) * 100 : 0.0,
            'positions' => $rows,
            'assumptions' => $this->assumptions($type),
        ];
    }

    /**
     * @return array{0:float,1:string} cambio en fracción y explicación
     * @param array<int|string, float> $betas
     * @param array<string, mixed> $p
     * @param array<string, mixed> $scenario
     */
    private function impact(array $p, string $type, float $shock, string $base, array $scenario, array $betas): array
    {
        $class = (string) $p['asset_class'];
        $category = (string) $p['category_code'];
        $beta = $betas[(int) $p['id']] ?? (float) ($p['default_beta'] ?? 1.0);
        if ($category === 'stablecoin' || $class === 'fx') {
            $beta = 0.0;
        }

        switch ($type) {
            case 'market_drop':
                $change = max(-1.0, -$shock / 100 * $beta);

                return [$change, 'Beta ' . num($beta, 2) . ' × caída de ' . num($shock, 0) . '%'];

            case 'rate_hike':
                $dy = $shock / 10000;
                if ($class === 'fixed_income' || $category === 'fci') {
                    $duration = (float) ($p['duration_years'] ?? self::DEFAULT_DURATION[$category] ?? 4.0);
                    $convexity = 0.5 * $duration ** 2 * $dy ** 2; // convexidad aproximada ≈ D²

                    return [-$duration * $dy + $convexity, 'Duración ' . num($duration, 1) . ' años'];
                }
                if ($class === 'crypto' && $category !== 'stablecoin') {
                    return [self::EQUITY_RATE_SENSITIVITY['crypto'] * $shock / 100, 'Sensibilidad supuesta de cripto a tasas'];
                }
                if (in_array($class, ['equity', 'fund', 'index'], true)) {
                    $s = self::EQUITY_RATE_SENSITIVITY[$p['sector'] ?? ''] ?? self::EQUITY_RATE_SENSITIVITY['default'];

                    return [$s * $shock / 100, 'Sensibilidad supuesta del sector ' . ($p['sector'] ?? '') . ' a tasas'];
                }

                return [0.0, 'Sin sensibilidad a tasas'];

            case 'devaluation':
                $ccy = strtoupper((string) ($scenario['currency'] ?? 'ARS'));
                $assetCcy = CurrencyConverter::normalize((string) $p['currency']);
                $d = $shock / 100;
                if ($base === $ccy && $assetCcy !== $ccy) {
                    return [$d, "Activo en $assetCcy: gana en $ccy si $ccy se devalúa"];
                }
                if ($base !== $ccy && $assetCcy === $ccy) {
                    return [1 / (1 + $d) - 1, "Activo en $ccy medido en $base"];
                }

                return [0.0, 'No expuesto a esa moneda'];

            case 'sector_drop':
                if (($p['sector'] ?? null) === ($scenario['sector'] ?? '')) {
                    return [-$shock / 100, 'Pertenece al sector afectado'];
                }

                return [0.0, 'Otro sector'];

            case 'asset_drop':
                if ((int) $p['id'] === (int) ($scenario['instrument_id'] ?? 0)) {
                    return [-$shock / 100, 'Activo afectado'];
                }

                return [0.0, 'Sin cambios'];
        }

        throw new BusinessRuleException('Escenario desconocido.');
    }

    /**
     * @param array<string, mixed> $s
     */
    private function title(array $s): string
    {
        $shock = num((float) $s['shock'], 0);

        return match ($s['type']) {
            'market_drop' => "Caída del mercado de $shock%",
            'rate_hike' => "Suba de tasas de $shock puntos básicos",
            'devaluation' => 'Devaluación del ' . ($s['currency'] ?? 'ARS') . " de $shock%",
            'sector_drop' => 'Caída del sector ' . ($s['sector'] ?? '') . " de $shock%",
            'asset_drop' => "Caída de un activo de $shock%",
            default => 'Escenario',
        };
    }

    /** @return list<string> */
    private function assumptions(string $type): array
    {
        $common = 'Supuesto simplificado: el shock es instantáneo y no considera cambios de correlación ni liquidez.';

        return match ($type) {
            'market_drop' => [$common, 'Cada activo cae en proporción a su beta estimada contra el benchmark (o la beta típica de su categoría si no hay historia suficiente).', 'Stablecoins, divisas y efectivo no se mueven.'],
            'rate_hike' => [$common, 'Renta fija: variación ≈ −duración × Δtasa + ajuste de convexidad.', 'Renta variable y cripto: sensibilidades fijas por sector (no estimadas con datos).'],
            'devaluation' => [$common, 'Los activos en la moneda devaluada pierden valor medidos en la moneda base, y viceversa.', 'No considera traslado a precios locales (las acciones argentinas en pesos suelen reaccionar).'],
            'sector_drop', 'asset_drop' => [$common, 'Solo se mueven los activos afectados; el resto queda igual.'],
            default => [$common],
        };
    }
}
