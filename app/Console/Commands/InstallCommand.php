<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Setup\Installer;
use Illuminate\Console\Command;

/** Instalación completa desde la consola (la web lo hace sola en la primera visita). */
final class InstallCommand extends Command
{
    protected $signature = 'galpex:install
        {email? : Email del administrador (por defecto admin@galpex.local)}
        {password? : Contraseña del administrador (sin indicarla se genera una al azar)}';

    protected $description = 'Crea la base de datos, aplica las migraciones, carga el catálogo y el administrador';

    public function handle(Installer $installer): int
    {
        $db = config('database.connections.mysql');
        $steps = $installer->install([
            'db_host' => (string) $db['host'],
            'db_port' => (int) $db['port'],
            'db_name' => (string) $db['database'],
            'db_user' => (string) $db['username'],
            'db_pass' => (string) $db['password'],
            'admin_name' => 'Administrador',
            'admin_email' => (string) ($this->argument('email') ?? Installer::ADMIN_EMAIL),
            'admin_password' => (string) ($this->argument('password') ?? ''),
        ], !is_file(base_path('.env')));

        foreach ($steps as $step) {
            $this->line($step);
        }
        $this->info('GALPEX está instalado.');

        return self::SUCCESS;
    }
}
