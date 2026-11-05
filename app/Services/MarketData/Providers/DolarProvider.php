<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

use App\Services\MarketData\Cache;

/**
 * Cotizaciones del dólar en Argentina (oficial, MEP, contado con liquidación,
 * blue, mayorista, cripto y tarjeta). No requieren clave.
 *
 *   DolarApi        https://dolarapi.com/v1/dolares                  valor actual
 *   ArgentinaDatos  https://api.argentinadatos.com/v1/cotizaciones   historia diaria
 *
 * Se toma el precio de VENTA (lo que cuesta comprar un dólar).
 */
final class DolarProvider extends AbstractProvider implements BatchQuoteProvider
{
    private const LIVE = 'https://dolarapi.com/v1/dolares';
    private const HISTORY = 'https://api.argentinadatos.com/v1/cotizaciones/dolares/';

    public function code(): string
    {
        return 'dolar';
    }

    public function name(): string
    {
        return 'DolarApi / ArgentinaDatos';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supports(array $instrument, string $capability): bool
    {
        return in_array($capability, ['quote', 'history'], true) && $this->providerSymbol($instrument, 'dolar') !== null;
    }

    /** @return array<string,array<string,mixed>> casa => fila */
    private function live(): array
    {
        return Cache::remember('dolarapi:live', 60, function () {
            $out = [];
            foreach ($this->http->getJson(self::LIVE, [], [], $this->code()) as $r) {
                if (is_array($r) && isset($r['casa'])) {
                    $out[(string) $r['casa']] = $r;
                }
            }

            return $out === [] ? null : $out;
        }) ?? [];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $q = $this->quoteMany([$instrument])[(int) $instrument['id']] ?? null;
        if ($q === null) {
            $this->fail('No hay cotización para ' . $instrument['symbol']);
        }

        return $q;
    }

    public function quoteMany(array $instruments): array
    {
        $live = $this->live();
        $out = [];
        foreach ($instruments as $i) {
            $casa = $this->providerSymbol($i, 'dolar');
            $r = $casa !== null ? ($live[$casa] ?? null) : null;
            if ($r === null || !is_numeric($r['venta'] ?? null)) {
                continue;
            }
            $date = substr((string) ($r['fechaActualizacion'] ?? ''), 0, 19);
            $out[(int) $i['id']] = [
                'price' => (float) $r['venta'],
                'prev_close' => $this->previousClose($casa, (float) $r['venta']),
                'bid' => is_numeric($r['compra'] ?? null) ? (float) $r['compra'] : null,
                'ask' => (float) $r['venta'],
                'quoted_at' => $date !== '' ? gmdate('Y-m-d H:i:s', (int) strtotime($date . ' UTC')) : gmdate('Y-m-d H:i:s'),
                'is_delayed' => false,
            ];
        }

        return $out;
    }

    /** Cierre del último día anterior a hoy, para calcular la variación diaria. */
    private function previousClose(string $casa, float $current): ?float
    {
        try {
            $series = $this->series($casa);
        } catch (\Throwable) {
            return null;
        }
        $today = date('Y-m-d');
        $prev = null;
        foreach ($series as $date => $value) {
            if ($date < $today) {
                $prev = $value;
            }
        }

        return $prev;
    }

    /** @return array<string,float> fecha => venta, ordenada */
    private function series(string $casa): array
    {
        return Cache::remember("argentinadatos:$casa", 6 * 3600, function () use ($casa) {
            $out = [];
            foreach ($this->http->getJson(self::HISTORY . rawurlencode($casa), [], [], $this->code()) as $r) {
                if (isset($r['fecha'], $r['venta']) && is_numeric($r['venta']) && (float) $r['venta'] > 0) {
                    $out[(string) $r['fecha']] = (float) $r['venta'];
                }
            }
            ksort($out);

            return $out === [] ? null : $out;
        }) ?? [];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $casa = $this->providerSymbol($instrument, 'dolar');
        if ($casa === null) {
            return [];
        }
        $bars = [];
        foreach ($this->series($casa) as $date => $value) {
            if ($date >= $from && $date <= $to) {
                $bars[] = ['date' => $date, 'open' => null, 'high' => null, 'low' => null, 'close' => $value, 'adj_close' => $value, 'volume' => null];
            }
        }

        return $bars;
    }
}
