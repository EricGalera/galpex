<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MarketData\ApiUsage;
use App\Services\MarketData\MarketDataService;
use App\Support\Database;
use Illuminate\Console\Command;

/** Estado de los proveedores de datos de mercado: clave, consumo del día y último error. */
final class ProvidersCommand extends Command
{
    protected $signature = 'galpex:providers {--clear-cache : Vacía la caché de respuestas de las APIs}';

    protected $description = 'Muestra el estado de los proveedores de cotizaciones';

    public function handle(): int
    {
        if ($this->option('clear-cache')) {
            Database::query('DELETE FROM api_cache');
            $this->info('Caché de APIs vaciada.');
        }

        $rows = [];
        $usage = array_column(ApiUsage::status(), null, 'code');
        foreach ((new MarketDataService())->providers() as $code => $provider) {
            $rows[] = [
                $code,
                $provider->isConfigured() ? 'activo' : 'sin clave',
                (int) ($usage[$code]['calls_today'] ?? 0),
                $usage[$code]['last_error'] ?? '—',
            ];
        }
        $this->table(['Proveedor', 'Estado', 'Llamadas hoy', 'Último error'], $rows);

        return self::SUCCESS;
    }
}
