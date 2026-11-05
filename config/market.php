<?php

declare(strict_types=1);

use App\Services\MarketData\Providers;

/*
 * Capa de proveedores intercambiables. Todos los datos son reales: si ningún
 * proveedor responde, se muestra el último precio real conocido (marcado como
 * desactualizado) o "sin cotización". Nunca se inventan precios.
 *
 * El orden define la prioridad: si el primero falla o llegó a su límite, se usa el siguiente.
 * data912, Yahoo Finance, DolarApi, BYMA Datos abiertos y Binance funcionan sin clave. Twelve Data,
 * Finnhub y Alpha Vantage son opcionales (se activan al cargar su clave en .env).
 */
return [
    // Fuentes: las públicas se pueden apagar desde .env; las opcionales se activan al cargar su clave.
    'sources' => [
        'data912' => ['enabled' => (bool) env('DATA912_ENABLED', true)],
        'yahoo' => ['enabled' => (bool) env('YAHOO_ENABLED', true)],
        'byma' => ['enabled' => (bool) env('BYMA_ENABLED', true)],
        'binance' => [
            'enabled' => (bool) env('BINANCE_ENABLED', true),
            'url' => env('BINANCE_MARKET_DATA_URL', 'https://data-api.binance.vision'), // cotizaciones públicas
            'api_url' => env('BINANCE_API_URL', 'https://api.binance.com'),             // cuentas conectadas (solo lectura)
        ],
        'twelvedata' => ['key' => (string) env('TWELVEDATA_API_KEY', '')],
        'finnhub' => ['key' => (string) env('FINNHUB_API_KEY', '')],
        'alphavantage' => ['key' => (string) env('ALPHAVANTAGE_API_KEY', '')],
    ],

    // Cliente HTTP hacia los proveedores
    'http' => [
        'timeout' => (int) env('HTTP_TIMEOUT', 8),
        'ca_bundle' => env('HTTP_CA_BUNDLE'), // solo si cURL de XAMPP no encuentra los certificados
    ],

    'registry' => [
        'data912' => Providers\Data912Provider::class,
        'yahoo' => Providers\YahooFinanceProvider::class,
        'dolar' => Providers\DolarProvider::class,
        'byma' => Providers\BymaIndexProvider::class,
        'binance' => Providers\BinanceProvider::class,
        'twelvedata' => Providers\TwelveDataProvider::class,
        'finnhub' => Providers\FinnhubProvider::class,
        'alphavantage' => Providers\AlphaVantageProvider::class,
    ],

    // Prioridad por mercado (código de la tabla markets)
    'routing' => [
        'BYMA' => ['data912', 'yahoo', 'twelvedata'],
        'USA' => ['data912', 'yahoo', 'finnhub', 'twelvedata', 'alphavantage'],
        'NYSE' => ['data912', 'yahoo', 'finnhub', 'twelvedata', 'alphavantage'],
        'NASDAQ' => ['data912', 'yahoo', 'finnhub', 'twelvedata', 'alphavantage'],
        'NYSEARCA' => ['data912', 'yahoo', 'finnhub', 'twelvedata', 'alphavantage'],
        'INDEX' => ['byma', 'yahoo', 'twelvedata'],
        'FX' => ['dolar', 'yahoo', 'twelvedata'],
        'CRYPTO' => ['binance', 'yahoo', 'twelvedata'],
    ],

    // Para historia se prefiere el proveedor con series más largas
    'history_routing' => [
        'BYMA' => ['data912', 'yahoo', 'twelvedata'],
        'USA' => ['yahoo', 'twelvedata', 'alphavantage'],
        'NYSE' => ['yahoo', 'twelvedata', 'alphavantage'],
        'NASDAQ' => ['yahoo', 'twelvedata', 'alphavantage'],
        'NYSEARCA' => ['yahoo', 'twelvedata', 'alphavantage'],
        'INDEX' => ['yahoo', 'twelvedata'],
        'FX' => ['dolar', 'yahoo', 'twelvedata'],
        'CRYPTO' => ['binance', 'yahoo', 'twelvedata'],
    ],

    'quote_ttl' => (int) env('QUOTE_TTL_SECONDS', 60),
    // Una cotización más vieja que esto se muestra como "desactualizada"
    'stale_after' => 60 * 60 * 24 * 4,
    // Años de historia que se intentan cargar por instrumento
    'history_years' => 5,
    // El catálogo completo (todo BYMA y EE. UU.) se sincroniza como mucho una vez por día
    'catalog_sync_hours' => 24,
];
