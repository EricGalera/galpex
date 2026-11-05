<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Alpha Vantage — cotización, serie diaria (últimos 100 días en plan gratuito) y fundamentales.
 * Docs: https://www.alphavantage.co/documentation/
 */
final class AlphaVantageProvider extends AbstractProvider
{
    private const BASE = 'https://www.alphavantage.co/query';

    public function code(): string
    {
        return 'alphavantage';
    }

    public function name(): string
    {
        return 'Alpha Vantage';
    }

    public function isConfigured(): bool
    {
        return (string) config('market.sources.alphavantage.key') !== '';
    }

    public function supports(array $instrument, string $capability): bool
    {
        return in_array($capability, ['quote', 'history', 'profile'], true)
            && in_array($instrument['market_code'] ?? '', ['NYSE', 'NASDAQ', 'NYSEARCA'], true);
    }

    /**
     * @return array<mixed>
     * @param array<string, mixed> $query
     */
    private function get(array $query): array
    {
        $query['apikey'] = (string) config('market.sources.alphavantage.key');
        $d = $this->http->getJson(self::BASE, $query, [], $this->code());
        foreach (['Note', 'Information'] as $k) {
            if (isset($d[$k])) {
                $this->fail('Alpha Vantage: límite de solicitudes alcanzado.', true);
            }
        }
        if (isset($d['Error Message'])) {
            $this->fail('Alpha Vantage: ' . $d['Error Message']);
        }

        return $d;
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $q = $this->get(['function' => 'GLOBAL_QUOTE', 'symbol' => $instrument['symbol']])['Global Quote'] ?? [];
        if (empty($q['05. price'])) {
            $this->fail('Alpha Vantage no devolvió precio para ' . $instrument['symbol']);
        }

        return [
            'price' => (float) $q['05. price'],
            'prev_close' => self::f($q['08. previous close'] ?? null),
            'open' => self::f($q['02. open'] ?? null),
            'high' => self::f($q['03. high'] ?? null),
            'low' => self::f($q['04. low'] ?? null),
            'volume' => self::f($q['06. volume'] ?? null),
            'quoted_at' => ($q['07. latest trading day'] ?? gmdate('Y-m-d')) . ' 21:00:00',
            'is_delayed' => true,
        ];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $series = $this->get([
            'function' => 'TIME_SERIES_DAILY',
            'symbol' => $instrument['symbol'],
            'outputsize' => 'compact',
        ])['Time Series (Daily)'] ?? [];

        $bars = [];
        foreach ($series as $date => $v) {
            if ($date < $from || $date > $to) {
                continue;
            }
            $bars[] = [
                'date' => $date,
                'open' => (float) $v['1. open'],
                'high' => (float) $v['2. high'],
                'low' => (float) $v['3. low'],
                'close' => (float) $v['4. close'],
                'adj_close' => (float) $v['4. close'],
                'volume' => (float) $v['5. volume'],
            ];
        }
        usort($bars, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $bars;
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function profile(array $instrument): array
    {
        $o = $this->get(['function' => 'OVERVIEW', 'symbol' => $instrument['symbol']]);
        if (!isset($o['Symbol'])) {
            return [];
        }
        $n = fn(string $k) => isset($o[$k]) && is_numeric($o[$k]) ? (float) $o[$k] : null;

        return array_filter([
            'market_cap' => $n('MarketCapitalization'),
            'pe_ratio' => $n('PERatio'),
            'eps' => $n('EPS'),
            'dividend_yield' => $n('DividendYield') !== null ? $n('DividendYield') * 100 : null,
            'beta' => $n('Beta'),
            'week52_high' => $n('52WeekHigh'),
            'week52_low' => $n('52WeekLow'),
            'industry' => $o['Industry'] ?? null,
        ], fn($v) => $v !== null);
    }
}
