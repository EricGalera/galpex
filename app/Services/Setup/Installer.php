<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Models\AuditLog;
use App\Models\User;
use App\Security\Crypto;
use App\Security\SecurityPolicy;
use App\Services\MarketData\Cache;
use App\Support\Database;
use RuntimeException;

/**
 * Instalación completa: .env, base de datos, migraciones, catálogo y administrador.
 * No se crean carteras ni movimientos de ejemplo: cada usuario empieza en cero.
 *
 * En la web no hay asistente: la primera visita llama a autoInstall(), que usa
 * los valores por defecto de XAMPP (root sin contraseña en 127.0.0.1:3306).
 */
final class Installer
{
    public const ADMIN_EMAIL = 'admin@galpex.local';
    /** Contraseña fija de versiones anteriores: si el administrador todavía la usa, la actualización la reemplaza. */
    private const LEGACY_ADMIN_PASSWORD = 'Admin1234';
    /** Archivo con la contraseña inicial del administrador (fuera de la web; se borra cuando la cambia). */
    public const CREDENTIALS_FILE = 'storage/credenciales-admin.txt';
    /** Versión del esquema: si la instalación es anterior, se actualiza sola en la próxima visita. */
    public const SCHEMA = 10;

    /**
     * Archivos de versiones anteriores que ya no forman parte del sistema: simulador de precios,
     * datos de demostración y backtesting, y el mini framework propio que reemplazó Laravel.
     * La actualización los borra (y también las carpetas que quedan vacías).
     */
    private const OBSOLETE = [
        'app/Core/App.php', 'app/Core/Auth.php', 'app/Core/Config.php', 'app/Core/Csrf.php', 'app/Core/Database.php',
        'app/Core/Env.php', 'app/Core/ErrorHandler.php', 'app/Core/Logger.php', 'app/Core/RateLimiter.php',
        'app/Core/Request.php', 'app/Core/Response.php', 'app/Core/Router.php', 'app/Core/Security.php',
        'app/Core/Session.php', 'app/Core/Validator.php', 'app/Core/View.php',
        'app/Core/Exceptions/BusinessRuleException.php', 'app/Core/Exceptions/HttpException.php',
        'app/Core/Exceptions/ProviderException.php', 'app/Core/Exceptions/ValidationException.php',
        'app/Http/Middleware/Middleware.php', 'app/Http/Middleware/VerifyCsrfToken.php', 'app/Support/Crypto.php',
        'bootstrap/autoload.php', 'bin/galpex', 'config/routes.php', 'config/middleware.php', 'config/providers.php',
        'app/Http/Controllers/BacktestController.php',
        'app/Services/Analytics/BacktestEngine.php',
        'app/Services/Analytics/BacktestService.php',
        'app/Services/MarketData/SimulatedMarket.php',
        'app/Services/MarketData/Providers/SimulatedProvider.php',
        'app/Services/Setup/DemoSeeder.php',
        'public/assets/js/pages/backtest.js',
        'resources/views/backtesting/index.php',
        'resources/views/partials/sidebar.php',
        'resources/views/partials/topbar.php',
        'tests/Unit/BacktestEngineTest.php',
        'tests/Unit/SimulatedMarketTest.php',
    ];

    public static function lockFile(): string
    {
        return base_path('storage/installed.lock');
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile());
    }

    /**
     * Instalación automática de la primera visita. Un candado de archivo evita
     * que dos pestañas abiertas a la vez instalen en paralelo.
     *
     * @return list<string>
     */
    public function autoInstall(): array
    {
        $dir = base_path('storage/cache');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $mutex = fopen($dir . '/setup.mutex', 'c');
        if ($mutex === false) {
            throw new RuntimeException('No se pudo escribir en storage/. Verificá los permisos de la carpeta del proyecto.');
        }
        flock($mutex, LOCK_EX);
        try {
            if (self::isInstalled()) {
                return [];
            }
            @set_time_limit(600);
            $db = config('database.connections.mysql');

            return $this->install([
                'db_host' => (string) $db['host'],
                'db_port' => (int) $db['port'],
                'db_name' => (string) $db['database'],
                'db_user' => (string) $db['username'],
                'db_pass' => (string) $db['password'],
                'admin_name' => 'Administrador',
                'admin_email' => self::ADMIN_EMAIL,
                'admin_password' => '', // se genera una al azar
            ], !is_file(base_path('.env')));
        } finally {
            flock($mutex, LOCK_UN);
            fclose($mutex);
        }
    }

    /** ¿La instalación es de una versión anterior del esquema? */
    public static function needsUpgrade(): bool
    {
        $lock = (string) @file_get_contents(self::lockFile());

        return !preg_match('/schema=(\d+)/', $lock, $m) || (int) $m[1] < self::SCHEMA;
    }

    /**
     * Actualiza una instalación existente: migraciones pendientes (que también borran
     * los datos de demostración de versiones anteriores), catálogo y panel completo.
     *
     * @return list<string>
     */
    public function upgrade(): array
    {
        $dir = base_path('storage/cache');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $mutex = fopen($dir . '/setup.mutex', 'c');
        if ($mutex === false) {
            throw new RuntimeException('No se pudo escribir en storage/. Verificá los permisos de la carpeta del proyecto.');
        }
        flock($mutex, LOCK_EX);
        try {
            if (!self::needsUpgrade()) {
                return [];
            }
            @set_time_limit(300);
            $applied = (new Migrator())->migrate();
            (new CatalogSeeder())->run();
            $steps = [count($applied) . ' migraciones aplicadas.', 'Catálogo actualizado.'];
            $steps[] = $this->syncCatalog();
            $steps[] = $this->removeObsoleteFiles() . ' archivos obsoletos eliminados.';
            $steps[] = $this->secureLegacyAdmin();
            $this->writeLock();

            return $steps;
        } finally {
            flock($mutex, LOCK_UN);
            fclose($mutex);
        }
    }

    /** Borra los archivos que quedaron de versiones anteriores. Devuelve cuántos borró. */
    private function removeObsoleteFiles(): int
    {
        $removed = 0;
        foreach (self::OBSOLETE as $relative) {
            $file = base_path($relative);
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }
        foreach (['resources/views/backtesting', 'app/Core/Exceptions', 'app/Core', 'bin'] as $relative) {
            $dir = base_path($relative);
            if (is_dir($dir) && (scandir($dir) ?: []) === ['.', '..']) {
                @rmdir($dir);
            }
        }

        return $removed;
    }

    /** Suma todo el panel de BYMA y EE. UU. Si no hay internet, queda el catálogo base. */
    private function syncCatalog(): string
    {
        try {
            $added = array_sum((new CatalogSync())->run());
            Cache::put('catalog-sync', 1, 24 * 3600);

            return "Panel completo sincronizado: $added instrumentos nuevos.";
        } catch (\Throwable $e) {
            return 'No se pudo descargar el panel completo (se reintenta al abrir Mercados): ' . $e->getMessage();
        }
    }

    private function writeLock(): void
    {
        if (!is_dir(dirname(self::lockFile()))) {
            mkdir(dirname(self::lockFile()), 0775, true);
        }
        file_put_contents(self::lockFile(), 'Instalado el ' . gmdate('Y-m-d H:i:s') . ' UTC · schema=' . self::SCHEMA . "\n");
    }

    /**
     * Versiones anteriores creaban el administrador con una contraseña fija y publicada en el README.
     * Si todavía la tiene, se reemplaza por una al azar (guardada en storage/) y se obliga a cambiarla.
     */
    private function secureLegacyAdmin(): string
    {
        $admin = User::findByEmail(self::ADMIN_EMAIL);
        if ($admin === null || !password_verify(self::LEGACY_ADMIN_PASSWORD, (string) $admin['password_hash'])) {
            return 'Contraseña del administrador: sin cambios.';
        }
        $password = SecurityPolicy::randomPassword();
        Database::query(
            'UPDATE users SET password_hash = ?, must_change_password = 1, session_version = session_version + 1 WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']],
        );
        $this->writeCredentials(self::ADMIN_EMAIL, $password);
        AuditLog::record('auth.default_password_rotated', [], (int) $admin['id'], 'sistema');

        return 'La contraseña fija del administrador se reemplazó; la nueva está en ' . self::CREDENTIALS_FILE . '.';
    }

    /** Escribe la contraseña inicial en storage/ (carpeta bloqueada para la web). */
    private function writeCredentials(string $email, string $password): void
    {
        $text = "GALPEX: credenciales iniciales del administrador\r\n"
            . 'Generadas el ' . gmdate('Y-m-d H:i') . " UTC\r\n\r\n"
            . "Email:       $email\r\n"
            . "Contraseña:  $password\r\n\r\n"
            . "Al ingresar, GALPEX te va a pedir que elijas una contraseña propia.\r\n"
            . "Este archivo se borra solo en ese momento. No lo compartas.\r\n";
        @file_put_contents(base_path(self::CREDENTIALS_FILE), $text, LOCK_EX);
        @chmod(base_path(self::CREDENTIALS_FILE), 0600);
    }

    /** Cuando el administrador elige su contraseña, la inicial deja de servir y se borra el archivo. */
    public static function forgetInitialAdminPassword(int $userId): void
    {
        $file = base_path(self::CREDENTIALS_FILE);
        $admin = User::findByEmail(self::ADMIN_EMAIL);
        if ($admin !== null && (int) $admin['id'] === $userId && is_file($file)) {
            @unlink($file);
        }
    }

    /** Borra la marca de instalación (por ejemplo, si la base fue eliminada desde phpMyAdmin). */
    public static function forget(): void
    {
        if (is_file(self::lockFile())) {
            @unlink(self::lockFile());
        }
    }

    /**
     * @param array{db_host:string,db_port:int,db_name:string,db_user:string,db_pass:string,
     *              admin_name:string,admin_email:string,admin_password:string} $opts
     * @return list<string> pasos realizados
     */
    public function install(array $opts, bool $writeEnv = true): array
    {
        $steps = [];
        $db = [
            'host' => $opts['db_host'], 'port' => $opts['db_port'], 'database' => $opts['db_name'],
            'username' => $opts['db_user'], 'password' => $opts['db_pass'],
        ];

        if ($writeEnv) {
            $this->writeEnv([
                'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'],
                'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
            ]);
            $steps[] = 'Archivo .env creado con una APP_KEY nueva.';
        }
        foreach (['host', 'port', 'database', 'username', 'password'] as $k) {
            config(["database.connections.mysql.$k" => $db[$k]]);
        }

        Migrator::createDatabase($db);
        Database::reconnect();
        $steps[] = "Base de datos «{$db['database']}» lista.";

        $applied = (new Migrator())->migrate();
        $steps[] = count($applied) . ' migraciones aplicadas.';

        (new CatalogSeeder())->run();
        $steps[] = 'Catálogo de mercados e instrumentos cargado.';
        $steps[] = $this->syncCatalog();

        if ($opts['admin_email'] !== '' && User::findByEmail($opts['admin_email']) === null) {
            $generated = $opts['admin_password'] === '';
            $password = $generated ? SecurityPolicy::randomPassword() : $opts['admin_password'];
            $id = User::create($opts['admin_name'] ?: 'Administrador', $opts['admin_email'], $password, 'admin', 'USD');
            if ($generated) {
                // Contraseña al azar, guardada solo en un archivo de la PC y de cambio obligatorio al ingresar.
                Database::update('users', ['must_change_password' => 1], ['id' => $id]);
                $this->writeCredentials($opts['admin_email'], $password);
                $steps[] = "Administrador {$opts['admin_email']} creado. Su contraseña inicial está en " . self::CREDENTIALS_FILE . '.';
            } else {
                $steps[] = "Administrador {$opts['admin_email']} creado.";
            }
        }

        $this->writeLock();

        return $steps;
    }

    /** @param array<string,string> $values */
    public function writeEnv(array $values): void
    {
        $envPath = base_path('.env');
        $template = is_file($envPath) ? (string) file_get_contents($envPath) : (string) file_get_contents(base_path('.env.example'));
        if (!str_contains($template, 'APP_KEY=base64:')) {
            $values['APP_KEY'] = Crypto::generateKey();
        }
        foreach ($values as $key => $value) {
            $line = $key . '=' . (preg_match('/[\s#"\']/', $value) ? '"' . addcslashes($value, '"\\') . '"' : $value);
            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $template)) {
                $template = (string) preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', str_replace('$', '\$', $line), $template);
            } else {
                $template .= "\n$line";
            }
            // Disponible en esta misma petición, sin volver a leer el archivo.
            putenv("$key=$value");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        if (isset($values['APP_KEY'])) {
            config(['app.key' => $values['APP_KEY']]);
        }
        if (@file_put_contents($envPath, $template) === false) {
            throw new RuntimeException('No se pudo escribir el archivo .env. Verificá los permisos de la carpeta del proyecto.');
        }
    }
}
