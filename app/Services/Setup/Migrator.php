<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Support\Database;
use PDO;

/**
 * Ejecuta los archivos SQL de database/migrations en orden y registra cuáles se aplicaron.
 */
final class Migrator
{
    public function __construct(private string $dir = '')
    {
        $this->dir = $dir !== '' ? $dir : base_path('database/migrations');
    }

    /**
     * Crea la base de datos si no existe (usa las credenciales de .env).
     *
     * @param array<string, mixed> $cfg
     */
    public static function createDatabase(array $cfg): void
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], (int) $cfg['port']),
            $cfg['username'],
            $cfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
        $name = str_replace('`', '', (string) $cfg['database']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /** @return list<string> migraciones aplicadas en esta ejecución */
    public function migrate(): array
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                migration VARCHAR(190) PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
        $done = array_column(Database::all('SELECT migration FROM migrations'), 'migration');
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (self::splitStatements((string) file_get_contents($file)) as $sql) {
                Database::connection()->exec($sql);
            }
            Database::insert('migrations', ['migration' => $name]);
            $applied[] = $name;
        }

        return $applied;
    }

    /** Borra todas las tablas (solo para tests o reinstalación). */
    public function dropAll(): void
    {
        $pdo = Database::connection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return list<string> */
    public static function splitStatements(string $sql): array
    {
        $lines = array_filter(
            explode("\n", str_replace("\r\n", "\n", $sql)),
            fn($l) => !str_starts_with(ltrim($l), '--'),
        );
        $statements = array_map('trim', explode(";\n", implode("\n", $lines) . "\n"));

        return array_values(array_filter($statements, fn($s) => $s !== '' && $s !== ';'));
    }
}
