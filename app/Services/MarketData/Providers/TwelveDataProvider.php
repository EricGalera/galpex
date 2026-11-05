<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Twelve Data — acciones (EE. UU. y otras bolsas), ETFs, índices, divisas y cripto.
 * Docs: https://twelvedata.com/docs
 */
final class TwelveDataProvider extends AbstractProvider
{
    private const BASE = 'https://api.twelvedata.com';

    public function code(): string
    {
        return 'twelvedata';
    }

    public function name(): string
    {
        return 'Twelve Data';
    }

    public function isConfigured(): bool
    {
        return (string) config('market.sources.twelvedata.key') !== '';
    }

    public function supports(array $instrument, string $capability): bool
    {
        if (!in_array($capability, ['quote', 'history'], true)) {
            return false;
        }

        return !in_array($instrument['market_code'] ?? '', ['FCI'], true)
            && ($instrument['category_code'] ?? '') !== 'stablecoin';
    }

    /**
     * @return array<string,string>
     * @param array<string, mixed> $instrument
     */
    private function params(array $instrument): array
    {
        $params = ['symbol' => $this->providerSymbol($instrument, 'twelvedata') ?? $instrument['symbol']];
        $exchange = $this->providerSymbol($instrument, 'twelvedata_exchange');
        if ($exchange !== null) {
            $params['exchange'] = $exchange;
        }
        $params['apikey'] = (string) config('market.sources.twelvedata.key');

        return $params;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function check(array $data): void
    {
        if (($data['status'] ?? '') === 'error') {
            $code = (int) ($data['code'] ?? 0);
            $this->fail('Twelve Data: ' . ($data['message'] ?? 'error desconocido'), $code === 429);
        }
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array
    {
        $d = $this->http->getJson(self::BASE . '/quote', $this->params($instrument), [], $this->code());
        $this->check($d);
        if (!isset($d['close'])) {
            $this->fail('Twelve Data no devolvió precio para ' . $instrument['symbol']);
        }
        $ts = isset($d['timestamp']) ? (int) $d['timestamp'] : time();

        return [
            'price' => (float) $d['close'],
            'prev_close' => self::f($d['previous_close'] ?? null),
            'open' => self::f($d['open'] ?? null),
            'high' => self::f($d['high'] ?? null),
            'low' => self::f($d['low'] ?? null),
            'volume' => self::f($d['volume'] ?? null),
            'quoted_at' => gmdate('Y-m-d H:i:s', $ts),
            'is_delayed' => !($d['is_market_open'] ?? false) ? false : (time() - $ts) > 900,
        ];
    }

    /**
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array
    {
        $params = $this->params($instrument) + [
            'interval' => '1day',
            'start_date' => $from,
            'end_date' => $to,
            'outputsize' => 5000,
            'order' => 'ASC',
        ];
        $d = $this->http->getJson(self::BASE . '/time_series', $params, [], $this->code());
        $this->check($d);

        $bars = [];
        foreach ($d['values'] ?? [] as $v) {
            $close = (float) $v['close'];
            $bars[] = [
                'date' => substr((string) $v['datetime'], 0, 10),
                'open' => self::f($v['open'] ?? null),
                'high' => self::f($v['high'] ?? null),
                'low' => self::f($v['low'] ?? null),
                'close' => $close,
                'adj_close' => $close,
                'volume' => self::f($v['volume'] ?? null),
            ];
        }

        return $bars;
    }
}
