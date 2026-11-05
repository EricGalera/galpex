<?php

declare(strict_types=1);

namespace App\Services\Exchanges;

use App\Exceptions\ProviderException;
use App\Services\MarketData\HttpClient;

/**
 * Conector de solo lectura para cuentas de Binance (API firmada HMAC-SHA256).
 * Docs: https://developers.binance.com/docs/binance-spot-api-docs/rest-api
 */
final class BinanceConnector implements ExchangeConnector
{
    public function __construct(private HttpClient $http = new HttpClient()) {}

    public function code(): string
    {
        return 'binance';
    }

    private function base(): string
    {
        return rtrim((string) config('market.sources.binance.api_url'), '/');
    }

    /**
     * @return array<mixed>
     * @param array<string, mixed> $params
     */
    private function signed(string $path, string $key, string $secret, array $params = []): array
    {
        $params['timestamp'] = (int) (microtime(true) * 1000);
        $params['recvWindow'] = 10000;
        $query = http_build_query($params);
        $params['signature'] = hash_hmac('sha256', $query, $secret);
        $data = $this->http->getJson($this->base() . $path, $params, ['X-MBX-APIKEY' => $key], 'binance');
        if (isset($data['code']) && (int) $data['code'] < 0) {
            throw new ProviderException('Binance: ' . ($data['msg'] ?? 'error'), 'binance');
        }

        return $data;
    }

    public function verifyPermissions(string $apiKey, string $apiSecret): array
    {
        $r = $this->signed('/sapi/v1/account/apiRestrictions', $apiKey, $apiSecret);
        $withdraw = !empty($r['enableWithdrawals']);
        $trade = !empty($r['enableSpotAndMarginTrading']) || !empty($r['enableFutures']) || !empty($r['enableMargin']);

        return [
            'read_only' => !$withdraw && !$trade,
            'can_trade' => $trade,
            'can_withdraw' => $withdraw,
            'message' => $withdraw
                ? 'La clave tiene permiso de RETIRO. Por seguridad no se acepta: creá una clave solo de lectura.'
                : ($trade ? 'La clave permite operar. GALPEX no opera, pero se recomienda una clave solo de lectura.' : 'Clave verificada: solo lectura.'),
        ];
    }

    public function balances(string $apiKey, string $apiSecret): array
    {
        $account = $this->signed('/api/v3/account', $apiKey, $apiSecret, ['omitZeroBalances' => 'true']);
        $out = [];
        foreach ($account['balances'] ?? [] as $b) {
            $free = (float) $b['free'];
            $locked = (float) $b['locked'];
            if ($free + $locked > 0) {
                $out[] = ['asset' => (string) $b['asset'], 'free' => $free, 'locked' => $locked];
            }
        }

        return $out;
    }

    public function trades(string $apiKey, string $apiSecret, array $symbols): array
    {
        $out = [];
        foreach (array_slice($symbols, 0, 10) as $symbol) {
            $rows = $this->signed('/api/v3/myTrades', $apiKey, $apiSecret, ['symbol' => $symbol, 'limit' => 500]);
            foreach ($rows as $t) {
                $out[] = [
                    'id' => (string) $t['id'],
                    'symbol' => $symbol,
                    'side' => !empty($t['isBuyer']) ? 'buy' : 'sell',
                    'quantity' => (float) $t['qty'],
                    'price' => (float) $t['price'],
                    'fee' => (float) $t['commission'],
                    'fee_asset' => (string) $t['commissionAsset'],
                    'executed_at' => gmdate('Y-m-d H:i:s', intdiv((int) $t['time'], 1000)),
                ];
            }
        }

        return $out;
    }
}
