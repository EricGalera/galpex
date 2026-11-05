<?php

declare(strict_types=1);

namespace App\Services\Exchanges;

/**
 * Contrato para conectar exchanges en modo SOLO LECTURA.
 * Implementaciones previstas: Binance (incluida), OKX y Bybit (pendientes).
 * Nunca se piden ni se usan permisos de retiro, y la operatoria real está deshabilitada.
 */
interface ExchangeConnector
{
    public function code(): string;

    /**
     * Verifica que la clave sea de solo lectura.
     *
     * @return array{read_only:bool,can_trade:bool,can_withdraw:bool,message:string}
     */
    public function verifyPermissions(string $apiKey, string $apiSecret): array;

    /** @return list<array{asset:string,free:float,locked:float}> */
    public function balances(string $apiKey, string $apiSecret): array;

    /**
     * @param list<string> $symbols pares a consultar (ej. BTCUSDT)
     * @return list<array{id:string,symbol:string,side:string,quantity:float,price:float,fee:float,fee_asset:string,executed_at:string}>
     */
    public function trades(string $apiKey, string $apiSecret, array $symbols): array;
}
