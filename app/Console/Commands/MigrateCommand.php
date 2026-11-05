<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Setup\Migrator;
use App\Support\Database;
use Illuminate\Console\Command;

/** Aplica los archivos SQL pendientes de database/migrations. */
final class MigrateCommand extends Command
{
    protected $signature = 'galpex:migrate';

    protected $description = 'Aplica las migraciones SQL pendientes';

    public function handle(): int
    {
        Migrator::createDatabase(config('database.connections.mysql'));
        Database::reconnect();
        $applied = (new Migrator())->migrate();
        $this->info($applied === [] ? 'Nada para migrar.' : 'Aplicadas: ' . implode(', ', $applied));

        return self::SUCCESS;
    }
}
