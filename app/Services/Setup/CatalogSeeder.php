<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Support\Database;

/**
 * Carga el catálogo base: mercados, categorías, instrumentos destacados y proveedores.
 * Es idempotente: se puede ejecutar varias veces sin duplicar datos.
 */
final class CatalogSeeder
{
    public function run(): void
    {
        $catalog = require base_path('database/seeds/catalog.php');

        Database::transaction(function () use ($catalog) {
            foreach ($catalog['markets'] as $m) {
                Database::query(
                    'INSERT INTO markets (code, name, country, currency, timezone, opens_at, closes_at)
                     VALUES (:code, :name, :country, :currency, :timezone, :opens_at, :closes_at)
                     ON DUPLICATE KEY UPDATE name = VALUES(name)',
                    $m,
                );
            }

            foreach ($catalog['categories'] as $i => [$code, $name, $class, $beta]) {
                Database::query(
                    'INSERT INTO asset_categories (code, name, asset_class, default_beta, sort_order) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order)',
                    [$code, $name, $class, $beta, $i],
                );
            }

            $markets = array_column(Database::all('SELECT id, code FROM markets'), 'id', 'code');
            $categories = array_column(Database::all('SELECT id, code FROM asset_categories'), 'id', 'code');

            foreach ($catalog['instruments'] as $row) {
                [$symbol, $name, $market, $category, $currency, $country, $sector, $extra] = $row;
                $providerSymbols = array_filter([
                    'yahoo' => $extra['yh'] ?? null,
                    'data912' => $extra['d9'] ?? null,
                    'dolar' => $extra['dolar'] ?? null,
                    'byma' => $extra['byma'] ?? null,
                    'binance' => $extra['bn'] ?? null,
                    'twelvedata' => $extra['td'] ?? null,
                    'twelvedata_exchange' => $extra['td_ex'] ?? null,
                    'finnhub' => $extra['fh'] ?? null,
                ], fn($v) => $v !== null);
                $fractional = !empty($extra['frac']) || in_array($market, ['CRYPTO', 'FX'], true);

                Database::query(
                    'INSERT INTO instruments (symbol, name, market_id, category_id, currency, quote_unit, fractional, country, sector,
                        description, provider_symbols, coupon_rate, duration_years, maturity_date, is_benchmark, is_active, needs_name)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), category_id = VALUES(category_id),
                        currency = VALUES(currency), quote_unit = VALUES(quote_unit), fractional = VALUES(fractional),
                        sector = VALUES(sector), provider_symbols = VALUES(provider_symbols), is_benchmark = VALUES(is_benchmark),
                        maturity_date = VALUES(maturity_date), is_active = 1, needs_name = 0',
                    [
                        $symbol, $name, $markets[$market], $categories[$category], $currency,
                        $extra['unit'] ?? 1, $fractional ? 1 : 0, $country, $sector, $extra['desc'] ?? null,
                        $providerSymbols === [] ? null : json_encode($providerSymbols, JSON_UNESCAPED_UNICODE),
                        $extra['coupon'] ?? null, $extra['duration'] ?? null, $extra['maturity'] ?? null,
                        !empty($extra['benchmark']) ? 1 : 0,
                    ],
                );
            }

            foreach ($catalog['providers'] as [$code, $name, $perMinute, $perDay]) {
                Database::query(
                    'INSERT INTO api_providers (code, name, requests_per_minute, requests_per_day) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), requests_per_minute = VALUES(requests_per_minute),
                        requests_per_day = VALUES(requests_per_day)',
                    [$code, $name, $perMinute, $perDay],
                );
            }
        });
    }
}
