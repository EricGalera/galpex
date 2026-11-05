<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

/**
 * Comisión que cobra el bróker según el tipo de instrumento (config/commissions.php).
 *
 * Es la única fuente de la tasa: la usan la compra y la venta, la previsualización
 * del diálogo de operación, el asesor y el rebalanceo.
 */
final class CommissionSchedule
{
    /**
     * Tasa (0,005 = 0,5 %) que corresponde a un instrumento según su categoría.
     *
     * @param array<string, mixed> $instrument
     */
    public static function rateFor(array $instrument): float
    {
        $category = (string) ($instrument['category_code'] ?? '');
        foreach ((array) config('commissions.groups', []) as [$rate, $categories]) {
            if (in_array($category, $categories, true)) {
                return (float) $rate;
            }
        }

        return (float) config('commissions.default', 0.005);
    }

    /** Comisión en dinero sobre un importe bruto, con el mínimo configurado. */
    public static function amount(float $gross, float $rate): float
    {
        $minimum = (float) config('commissions.minimum', 0.0);

        return round(max($gross * max(0.0, $rate), $gross > 0 ? $minimum : 0.0), 2);
    }

    /**
     * Tabla para mostrar al usuario.
     *
     * @return list<array{label:string,rate:float}>
     */
    public static function table(): array
    {
        $rows = [];
        foreach ((array) config('commissions.groups', []) as $label => [$rate]) {
            $rows[] = ['label' => (string) $label, 'rate' => (float) $rate];
        }

        return $rows;
    }
}
