<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Instrument;
use App\Services\MarketData\MarketDataService;
use App\Services\Setup\CatalogSeeder;
use App\Services\Setup\CatalogSync;
use App\Support\Database;
use Illuminate\Console\Command;

/** Catálogo de instrumentos: destacados, panel completo de BYMA y EE. UU. y nombres pendientes. */
final class CatalogCommand extends Command
{
    protected $signature = 'galpex:catalog
        {--sync : Suma todo lo que cotiza en BYMA y EE. UU. (data912)}
        {--names : Completa los nombres pendientes de las especies sumadas (Yahoo Finance)}';

    protected $description = 'Actualiza el catálogo de instrumentos';

    public function handle(): int
    {
        (new CatalogSeeder())->run();
        $this->info('Catálogo base actualizado.');

        if ($this->option('sync')) {
            foreach ((new CatalogSync())->run() as $panel => $added) {
                $this->line(sprintf('%-12s %d nuevos', $panel, $added));
            }
        }

        if ($this->option('names')) {
            $market = new MarketDataService(null, 1000);
            $total = 0;
            do {
                $pending = Database::all(Instrument::SELECT . ' WHERE i.needs_name = 1 AND i.is_active = 1 LIMIT 200');
                $done = $market->completeNames($pending, 10);
                $total += $done;
            } while ($pending !== [] && $done > 0);
            $this->line("Nombres completados: $total");
        }

        return self::SUCCESS;
    }
}
