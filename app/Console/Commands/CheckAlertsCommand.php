<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Alerts\AlertService;
use App\Support\Database;
use Illuminate\Console\Command;

/** Evalúa las alertas activas de todos los usuarios y genera las notificaciones. */
final class CheckAlertsCommand extends Command
{
    protected $signature = 'galpex:alerts';

    protected $description = 'Evalúa las alertas de todos los usuarios';

    public function handle(AlertService $alerts): int
    {
        $total = 0;
        foreach (Database::all('SELECT DISTINCT user_id FROM alerts WHERE is_active = 1') as $row) {
            $total += $alerts->evaluateForUser((int) $row['user_id']);
        }
        $this->info("Alertas disparadas: $total");

        return self::SUCCESS;
    }
}
