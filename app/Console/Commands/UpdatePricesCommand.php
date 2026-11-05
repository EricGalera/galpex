<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Instrument;
use App\Services\MarketData\MarketDataService;
use App\Support\Database;
use Illuminate\Console\Command;

/** Actualiza cotizaciones de todo el catálogo e historia de lo que está en carteras o listas. */
final class UpdatePricesCommand extends Command
{
    protected $signature = 'galpex:prices';

    protected $description = 'Actualiza las cotizaciones (pensado para el programador de tareas)';

    public function handle(): int
    {
        $market = new MarketDataService(null, 500);
        $all = Instrument::search([], 20000);
        $market->refreshQuotes($all, true);
        $this->line('Cotizaciones actualizadas: ' . count($all));

        $held = Database::all(Instrument::SELECT . ' WHERE i.id IN (SELECT instrument_id FROM portfolio_positions WHERE quantity > 0
            UNION SELECT instrument_id FROM watchlist_items) OR i.is_benchmark = 1');
        foreach ($held as $instrument) {
            $market->ensureHistory($instrument);
        }
        $this->line('Historia actualizada: ' . count($held));

        return self::SUCCESS;
    }
}
