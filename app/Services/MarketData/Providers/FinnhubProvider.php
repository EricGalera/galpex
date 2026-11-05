<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Finnhub — cotizaciones de EE. UU., perfil de empresas y noticias.
 * En el plan gratuito no incluye velas históricas, por eso no ofrece "history".
 * Docs: https://finnhub.io/docs/api
 */
final class FinnhubProvider extends AbstractProvider
{
    private const BASE = 'https://finnhub.io/api/v1';

    public function code(): string
    {
        return 'finnhub';
    }

    public function name(): string
    {
        return 'Finnhub';
    }

    public function isConfigured(): bool
    {
        return (string) config('market.sources.finnhub.key') !== '';
    }

    public function supports(array $instrument, string $capability): bool
    {
        return in_array($capability, ['quote', 'profile'], true)
            && in_array($instrument['market_code'] ?? '', ['NYSE', 'NASDAQ', 'NYSEARCA'], true);
    }

    /**
     * @return array<mixed>
     * @param array<string, mixed> $query
     */
    public function get(string $path, array $query = []): array
    {
        $query['token'] = (string) config('market.sources.finnhub.key');
        $data = $this->http->getJson(self::BASE . $path, $query, [], $this->code());
        if (isset($data['error'])) {
            $this->fail('Finnhub: ' . $data['error'], str_contains((string) $data['error'], 'limit'));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $symbol = $this->providerSymbol($instrument, 'finnhub') ?? $instrument['symbol'];
        $d = $this->get('/quote', ['symbol' => $symbol]);
        if (empty($d['c'])) {
            $this->fail("Finnhub no tiene cotización para $symbol");
        }
        $ts = (int) ($d['t'] ?? time());

        return [
            'price' => (float) $d['c'],
            'prev_close' => self::f($d['pc'] ?? null),
            'open' => self::f($d['o'] ?? null),
            'high' => self::f($d['h'] ?? null),
            'low' => self::f($d['l'] ?? null),
            'volume' => null,
            'quoted_at' => gmdate('Y-m-d H:i:s', $ts > 0 ? $ts : time()),
            'is_delayed' => false,
        ];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $this->fail('Finnhub gratuito no provee series históricas.');
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function profile(array $instrument): array
    {
        $symbol = $this->providerSymbol($instrument, 'finnhub') ?? $instrument['symbol'];
        $p = $this->get('/stock/profile2', ['symbol' => $symbol]);
        if ($p === []) {
            return [];
        }
        $m = $this->get('/stock/metric', ['symbol' => $symbol, 'metric' => 'all'])['metric'] ?? [];

        return array_filter([
            'market_cap' => isset($p['marketCapitalization']) ? (float) $p['marketCapitalization'] * 1e6 : null,
            'industry' => $p['finnhubIndustry'] ?? null,
            'website' => $p['weburl'] ?? null,
            'ipo' => $p['ipo'] ?? null,
            'pe_ratio' => $m['peTTM'] ?? null,
            'eps' => $m['epsTTM'] ?? null,
            'dividend_yield' => isset($m['dividendYieldIndicatedAnnual']) ? (float) $m['dividendYieldIndicatedAnnual'] : null,
            'beta' => $m['beta'] ?? null,
            'week52_high' => $m['52WeekHigh'] ?? null,
            'week52_low' => $m['52WeekLow'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }
}
