<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Textos legibles para códigos internos.
 */
final class Labels
{
    public const COUNTRIES = [
        'AR' => 'Argentina', 'US' => 'Estados Unidos', 'BR' => 'Brasil', 'TW' => 'Taiwán', 'NL' => 'Países Bajos',
        'DK' => 'Dinamarca', 'DE' => 'Alemania', 'JP' => 'Japón', 'CN' => 'China', 'GL' => 'Global',
        'EM' => 'Emergentes', 'UY' => 'Uruguay', 'CL' => 'Chile', 'MX' => 'México', 'ES' => 'España',
        'EU' => 'Zona euro', 'GB' => 'Reino Unido',
    ];

    public const ASSET_CLASSES = [
        'equity' => 'Renta variable', 'fixed_income' => 'Renta fija', 'fund' => 'Fondos y ETFs',
        'crypto' => 'Cripto', 'cash' => 'Efectivo', 'index' => 'Índices', 'fx' => 'Divisas',
    ];

    public const TX_TYPES = [
        'deposit' => 'Ingreso', 'withdrawal' => 'Retiro', 'buy' => 'Compra', 'sell' => 'Venta', 'dividend' => 'Dividendo',
    ];

    public const DATA_STATUS = [
        'live' => ['Tiempo real', 'Cotización al momento, informada por el proveedor'],
        'delayed' => ['Diferido', 'Cotización real con la demora habitual de los datos gratuitos (BYMA: 20 minutos)'],
        'stale' => ['Desactualizado', 'Último precio real conocido: tiene más de 4 días (especie sin operaciones o proveedor sin respuesta)'],
        'none' => ['Sin cotización', 'Todavía no hay un precio real para este instrumento'],
    ];

    public const SOURCES = [
        'data912' => 'BYMA (data912)', 'yahoo' => 'Yahoo Finance', 'dolar' => 'DolarApi', 'twelvedata' => 'Twelve Data',
        'finnhub' => 'Finnhub', 'alphavantage' => 'Alpha Vantage', 'binance' => 'Binance', 'manual' => 'carga manual', 'history' => 'historial',
    ];

    /** Nombre corto para la franja de cotizaciones. */
    public const TAPE_NAMES = [
        'MERV' => 'MSCI Merval', 'SPX' => 'S&P 500', 'NDX' => 'Nasdaq 100', 'DJI' => 'Dow Jones',
        'USD/ARS' => 'Dólar MEP', 'USD/ARS-OF' => 'Dólar oficial', 'USD/ARS-CCL' => 'Dólar CCL', 'USD/ARS-BLUE' => 'Dólar blue',
        'BTC' => 'Bitcoin', 'ETH' => 'Ether',
    ];

    /**
     * @param array<string, mixed> $instrument
     */
    public static function tapeName(array $instrument): string
    {
        return self::TAPE_NAMES[$instrument['symbol']] ?? (string) $instrument['symbol'];
    }

    public static function source(?string $code): string
    {
        return self::SOURCES[$code ?? ''] ?? (string) $code;
    }

    public static function country(?string $code): string
    {
        return $code === null ? 'Sin país' : (self::COUNTRIES[$code] ?? $code);
    }

    public static function assetClass(?string $code): string
    {
        return self::ASSET_CLASSES[$code ?? ''] ?? (string) $code;
    }

    public static function txType(string $type): string
    {
        return self::TX_TYPES[$type] ?? $type;
    }
}
