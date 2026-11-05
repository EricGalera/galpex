<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Contrato común de todos los proveedores de datos de mercado.
 * Para agregar un proveedor nuevo alcanza con implementar esta interfaz
 * y registrarlo en config/market.php: el resto del sistema no cambia.
 *
 * Formato de cotización devuelto por quote():
 *   price, prev_close, open, high, low, volume, market_cap?, quoted_at (UTC "Y-m-d H:i:s"), is_delayed
 *
 * Formato de cada barra devuelta por history():
 *   date (Y-m-d), open, high, low, close, adj_close, volume
 */
interface MarketDataProvider
{
    public function code(): string;

    public function name(): string;

    /** Tiene credenciales/configuración para funcionar. */
    public function isConfigured(): bool;

    /**
     * @param array<string,mixed> $instrument fila de instruments + market_code + category_code
     * @param 'quote'|'history'|'profile' $capability
     */
    public function supports(array $instrument, string $capability): bool;

    /**
     * @return array<string,mixed>
     * @param array<string, mixed> $instrument
     */
    public function quote(array $instrument): array;

    /**
     * @return list<array<string,mixed>>
     * @param array<string, mixed> $instrument
     */
    public function history(array $instrument, string $from, string $to): array;

    /**
     * @return array<string,mixed> datos fundamentales disponibles (puede ser vacío)
     * @param array<string, mixed> $instrument
     */
    public function profile(array $instrument): array;
}
