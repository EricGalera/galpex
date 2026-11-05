<?php

declare(strict_types=1);

namespace App\Services\Exchanges;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Security\Crypto;
use App\Support\Database;
use App\Support\Validator;
use Throwable;

/**
 * Gestión de exchanges conectados: alta con verificación de permisos,
 * sincronización de saldos e historial. Sin operatoria real.
 */
class ExchangeService
{
    /** @var array<string,ExchangeConnector> */
    private array $connectors;

    /**
     * @param array<string, \App\Services\Exchanges\ExchangeConnector>|null $connectors
     */
    public function __construct(?array $connectors = null)
    {
        $this->connectors = $connectors ?? ['binance' => new BinanceConnector()];
    }

    /** @return list<string> */
    public function supported(): array
    {
        return array_keys($this->connectors);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function connect(int $userId, array $input): int
    {
        $data = Validator::make($input, [
            'exchange' => 'required|in:binance,okx,bybit',
            'label' => 'required|string|max:60',
            'api_key' => 'required|string|min:16|max:128',
            'api_secret' => 'required|string|min:16|max:128',
        ]);
        $connector = $this->connectors[$data['exchange']] ?? null;
        if ($connector === null) {
            throw new BusinessRuleException('La integración con ' . strtoupper($data['exchange']) . ' todavía no está disponible. Por ahora solo Binance.');
        }
        try {
            $perm = $connector->verifyPermissions($data['api_key'], $data['api_secret']);
        } catch (Throwable $e) {
            throw new BusinessRuleException('No se pudo verificar la clave: ' . $e->getMessage());
        }
        if ($perm['can_withdraw']) {
            AuditLog::record('exchange.rejected_withdraw_key', ['exchange' => $data['exchange']], $userId);
            throw new BusinessRuleException($perm['message']);
        }

        $id = Database::insert('connected_exchanges', [
            'user_id' => $userId,
            'exchange' => $data['exchange'],
            'label' => $data['label'],
            'api_key_enc' => Crypto::encrypt($data['api_key']),
            'api_secret_enc' => Crypto::encrypt($data['api_secret']),
            'read_only_verified' => $perm['read_only'] ? 1 : 0,
            'status' => 'active',
            'status_message' => $perm['message'],
        ]);
        AuditLog::record('exchange.connected', ['exchange' => $data['exchange'], 'read_only' => $perm['read_only']], $userId);
        $this->sync($userId, $id);

        return $id;
    }

    /** @return array{balances:int,trades:int} */
    public function sync(int $userId, int $exchangeId): array
    {
        $ex = $this->find($userId, $exchangeId);
        $connector = $this->connectors[$ex['exchange']] ?? null;
        if ($connector === null) {
            throw new BusinessRuleException('Exchange no soportado.');
        }
        $key = Crypto::decrypt($ex['api_key_enc']);
        $secret = Crypto::decrypt($ex['api_secret_enc']);

        try {
            $balances = $connector->balances($key, $secret);
            $symbols = [];
            foreach ($balances as $b) {
                if (!in_array($b['asset'], ['USDT', 'USDC', 'BUSD', 'FDUSD'], true)) {
                    $symbols[] = $b['asset'] . 'USDT';
                }
            }
            $trades = $connector->trades($key, $secret, $symbols);
        } catch (Throwable $e) {
            Database::update('connected_exchanges', ['status' => 'error', 'status_message' => mb_substr($e->getMessage(), 0, 250)], ['id' => $exchangeId]);
            throw new BusinessRuleException('Error al sincronizar: ' . $e->getMessage());
        }

        Database::transaction(function () use ($exchangeId, $balances, $trades) {
            Database::query('DELETE FROM exchange_balances WHERE exchange_id = ?', [$exchangeId]);
            foreach ($balances as $b) {
                Database::insert('exchange_balances', [
                    'exchange_id' => $exchangeId, 'asset' => $b['asset'], 'free_amount' => $b['free'],
                    'locked_amount' => $b['locked'], 'synced_at' => now_utc(),
                ]);
            }
            foreach ($trades as $t) {
                Database::query(
                    'INSERT IGNORE INTO exchange_trades (exchange_id, external_id, symbol, side, quantity, price, fee, fee_asset, executed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$exchangeId, $t['id'], $t['symbol'], $t['side'], $t['quantity'], $t['price'], $t['fee'], $t['fee_asset'], $t['executed_at']],
                );
            }
            Database::update('connected_exchanges', ['status' => 'active', 'last_sync_at' => now_utc()], ['id' => $exchangeId]);
        });

        return ['balances' => count($balances), 'trades' => count($trades)];
    }

    /** @return array<string,mixed> */
    public function find(int $userId, int $exchangeId): array
    {
        $ex = Database::first('SELECT * FROM connected_exchanges WHERE id = ? AND user_id = ?', [$exchangeId, $userId]);
        if ($ex === null) {
            throw new HttpException(404, 'La conexión no existe.');
        }

        return $ex;
    }

    /** @return list<array<string,mixed>> conexiones sin credenciales */
    public function forUser(int $userId): array
    {
        $rows = Database::all('SELECT id, exchange, label, read_only_verified, status, status_message, last_sync_at, created_at, api_key_enc FROM connected_exchanges WHERE user_id = ? ORDER BY id', [$userId]);
        foreach ($rows as &$r) {
            try {
                $r['key_hint'] = Crypto::mask(Crypto::decrypt($r['api_key_enc']));
            } catch (Throwable) {
                $r['key_hint'] = '—';
            }
            unset($r['api_key_enc']);
            $r['balances'] = Database::all('SELECT asset, free_amount, locked_amount FROM exchange_balances WHERE exchange_id = ? ORDER BY asset', [(int) $r['id']]);
            $r['trades'] = (int) Database::scalar('SELECT COUNT(*) FROM exchange_trades WHERE exchange_id = ?', [(int) $r['id']]);
        }
        unset($r);

        return $rows;
    }

    public function disconnect(int $userId, int $exchangeId): void
    {
        $this->find($userId, $exchangeId);
        Database::delete('connected_exchanges', ['id' => $exchangeId]);
        AuditLog::record('exchange.disconnected', ['id' => $exchangeId], $userId);
    }
}
