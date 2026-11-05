<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Exceptions\BusinessRuleException;
use App\Models\Instrument;

/**
 * Conversión entre monedas usando los pares de divisas del catálogo
 * (USD/ARS, EUR/USD, USD/BRL). Las stablecoins se tratan como USD.
 */
class CurrencyConverter
{
    /** Par => [moneda cotizada en, moneda base del par] */
    private const PAIRS = [
        'USD/ARS' => ['USD', 'ARS'],   // precio = ARS por 1 USD
        'EUR/USD' => ['EUR', 'USD'],
        'USD/BRL' => ['USD', 'BRL'],
    ];

    /** @var array<string,float> */
    private array $spot = [];
    /** @var array<string,array<string,float>> par => fecha => precio */
    private array $history = [];

    public function __construct(private ?MarketDataService $market = null)
    {
        $this->market ??= new MarketDataService();
    }

    public static function normalize(string $currency): string
    {
        $c = strtoupper(trim($currency));

        return in_array($c, ['USDT', 'USDC', 'DAI'], true) ? 'USD' : $c;
    }

    /** Tipo de cambio actual: cuántas unidades de $to vale 1 unidad de $from. */
    public function rate(string $from, string $to): float
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        if ($from === $to) {
            return 1.0;
        }
        if ($from !== 'USD' && $to !== 'USD') {
            return $this->rate($from, 'USD') * $this->rate('USD', $to);
        }

        foreach (self::PAIRS as $pair => [$base, $quote]) {
            if ($base === $from && $quote === $to) {
                return $this->spot($pair);
            }
            if ($base === $to && $quote === $from) {
                return 1 / $this->spot($pair);
            }
        }

        throw new BusinessRuleException("No hay tipo de cambio disponible entre $from y $to.");
    }

    /** Tipo de cambio en una fecha (último cierre conocido a esa fecha). */
    public function rateOn(string $from, string $to, string $date): float
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        if ($from === $to) {
            return 1.0;
        }
        if ($from !== 'USD' && $to !== 'USD') {
            return $this->rateOn($from, 'USD', $date) * $this->rateOn('USD', $to, $date);
        }
        foreach (self::PAIRS as $pair => [$base, $quote]) {
            if ($base === $from && $quote === $to) {
                return $this->historical($pair, $date);
            }
            if ($base === $to && $quote === $from) {
                return 1 / $this->historical($pair, $date);
            }
        }

        throw new BusinessRuleException("No hay tipo de cambio disponible entre $from y $to.");
    }

    private function spot(string $pair): float
    {
        if (!isset($this->spot[$pair])) {
            $inst = Instrument::findBySymbol($pair, 'FX');
            if ($inst === null) {
                throw new BusinessRuleException("Falta el par $pair en el catálogo.");
            }
            $inst = $this->market->refreshQuote($inst);
            if ($inst['price'] === null || (float) $inst['price'] <= 0) {
                throw new BusinessRuleException("No hay cotización para $pair.");
            }
            $this->spot[$pair] = (float) $inst['price'];
        }

        return $this->spot[$pair];
    }

    private function historical(string $pair, string $date): float
    {
        if (!isset($this->history[$pair])) {
            $inst = Instrument::findBySymbol($pair, 'FX');
            $this->history[$pair] = $inst ? $this->market->closes($inst, '2000-01-01', '2100-01-01') : [];
        }
        $series = $this->history[$pair];
        if ($date >= date('Y-m-d') || $series === []) {
            return $this->spot($pair);
        }
        if (isset($series[$date])) {
            return $series[$date];
        }
        // Último valor anterior a la fecha (búsqueda binaria sobre fechas ordenadas)
        $dates = array_keys($series);
        $lo = 0;
        $hi = count($dates) - 1;
        $found = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($dates[$mid] <= $date) {
                $found = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $found === null ? $series[$dates[0]] : $series[$dates[$found]];
    }

    /** Permite fijar tipos de cambio (tests y simulaciones). */
    public function setSpot(string $pair, float $value): void
    {
        $this->spot[$pair] = $value;
    }
}
