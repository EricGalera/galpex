<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\AuditLog;
use App\Models\User;
use App\Security\Auth;
use App\Security\RateLimiter;
use App\Security\SecurityPolicy;
use App\Services\Setup\Installer;
use App\Support\Database;
use App\Support\Logger;
use App\Support\Validator;

/**
 * Registro, inicio de sesión y recuperación de contraseña.
 *
 * Defensas:
 *  - contraseñas con password_hash (bcrypt) y política NIST (largo, comunes, sin el email);
 *  - tres límites de intentos fallidos: por email+IP, por email (desde cualquier IP) y por IP
 *    (frena a quien prueba muchas cuentas con la misma contraseña);
 *  - misma respuesta y mismo tiempo exista o no la cuenta (no se puede averiguar qué emails están registrados);
 *  - enlaces de recuperación de un solo uso, guardados como hash y con vencimiento;
 *  - al cambiar o restablecer la contraseña se cierran las demás sesiones abiertas.
 */
class AuthService
{
    /** Hash bcrypt real de un texto aleatorio: hace que un email inexistente tarde lo mismo que uno válido. */
    private const DUMMY_HASH = '$2y$10$YGvV30E1/F00pk4Mxf93SuBJ8tRRYBljDYI8z.9LeO8VvT2jtSsrO';

    /**
     * @return int id del usuario creado
     * @param array<string, mixed> $input
     */
    public function register(array $input, string $ip): int
    {
        if (!RateLimiter::attempt("register:$ip", 10, 3600)) {
            throw new BusinessRuleException('Demasiados registros desde esta conexión. Probá en una hora.');
        }
        $data = Validator::make($input, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:190',
            'password' => 'required|password|confirmed',
            'base_currency' => 'required|in:' . implode(',', (array) config('app.currencies')),
        ]);
        if (($problem = SecurityPolicy::passwordProblem($data['password'], $data['email'])) !== null) {
            throw new ValidationException(['password' => $problem], $input);
        }
        if (User::findByEmail($data['email']) !== null) {
            throw new ValidationException(['email' => 'Ya existe una cuenta con ese email.'], $input);
        }
        $id = User::create($data['name'], $data['email'], $data['password'], 'user', $data['base_currency']);
        AuditLog::record('auth.register', ['email' => $data['email']], $id, $ip);

        return $id;
    }

    public function attempt(string $email, string $password, string $ip): int
    {
        $email = mb_strtolower(trim($email));
        $max = (int) config('app.login_max_attempts', 5);
        $window = (int) config('app.login_window_seconds', 900);
        $limits = [
            "login:$email|$ip" => $max,                                              // esta cuenta desde esta conexión
            "login-account:$email" => (int) config('app.login_max_per_account', 20),  // esta cuenta desde cualquier lado
            "login-ip:$ip" => (int) config('app.login_max_per_ip', 50),          // muchas cuentas desde una conexión
        ];

        foreach ($limits as $key => $limit) {
            if (RateLimiter::tooMany($key, $limit, $window)) {
                AuditLog::record('auth.locked', ['email' => $email], null, $ip);
                throw new BusinessRuleException('Demasiados intentos fallidos. Esperá 15 minutos o recuperá tu contraseña.');
            }
        }

        $user = User::findByEmail($email);
        // Se verifica un hash aunque el usuario no exista, para que el tiempo de respuesta no revele qué emails están registrados.
        $ok = password_verify($password, (string) ($user['password_hash'] ?? self::DUMMY_HASH));
        if ($user === null || !$ok || $user['status'] !== 'active') {
            foreach ($limits as $key => $limit) {
                RateLimiter::attempt($key, PHP_INT_MAX, $window);
            }
            AuditLog::record('auth.failed', ['email' => mb_substr($email, 0, 190)], $user ? (int) $user['id'] : null, $ip);
            throw new ValidationException(['email' => 'El email o la contraseña no son correctos.'], ['email' => $email]);
        }

        RateLimiter::clear("login:$email|$ip");
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => (int) $user['id']]);
        }
        Database::update('users', ['last_login_at' => now_utc()], ['id' => (int) $user['id']]);
        AuditLog::record('auth.login', [], (int) $user['id'], $ip);
        Auth::login((int) $user['id']);

        return (int) $user['id'];
    }

    /**
     * Genera un enlace de recuperación. Devuelve el token en claro SOLO para
     * que el controlador pueda mostrarlo en modo local (XAMPP no envía emails).
     * En la base se guarda únicamente su hash SHA-256.
     */
    public function requestReset(string $email, string $ip): ?string
    {
        $email = mb_strtolower(trim($email));
        if (!RateLimiter::attempt("reset:$ip", 5, 3600) || !RateLimiter::attempt("reset-account:$email", 3, 3600)) {
            throw new BusinessRuleException('Demasiadas solicitudes. Probá en una hora.');
        }
        $user = User::findByEmail($email);
        if ($user === null) {
            return null; // la respuesta al usuario es la misma exista o no la cuenta
        }
        $token = bin2hex(random_bytes(32));
        Database::query('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now_utc(), (int) $user['id']]);
        Database::insert('password_resets', [
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * (int) config('app.password_reset_minutes', 60)),
        ]);
        AuditLog::record('auth.reset_requested', [], (int) $user['id'], $ip);
        // El token nunca se escribe en los logs: quien leyera el archivo podría tomar la cuenta.
        Logger::info('Enlace de recuperación generado', ['user_id' => $user['id']]);

        return $token;
    }

    /** @return array<string,mixed>|null fila de password_resets válida */
    public function findValidReset(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return Database::first(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
            [hash('sha256', $token), now_utc()],
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function resetPassword(string $token, array $input, string $ip): void
    {
        $reset = $this->findValidReset($token);
        if ($reset === null) {
            throw new BusinessRuleException('El enlace venció o ya se usó. Pedí uno nuevo.');
        }
        $data = Validator::make($input, ['password' => 'required|password|confirmed']);
        $user = User::find((int) $reset['user_id']);
        if (($problem = SecurityPolicy::passwordProblem($data['password'], (string) ($user['email'] ?? ''))) !== null) {
            throw new ValidationException(['password' => $problem]);
        }
        Database::transaction(function () use ($reset, $data, $ip, $user) {
            self::storePassword((int) $reset['user_id'], $data['password']);
            Database::update('password_resets', ['used_at' => now_utc()], ['id' => (int) $reset['id']]);
            AuditLog::record('auth.password_reset', [], (int) $reset['user_id'], $ip);
            // Quien recupera la cuenta tiene que poder entrar aunque un atacante la haya bloqueado.
            RateLimiter::clear('login-account:' . ($user['email'] ?? ''));
        });
        Installer::forgetInitialAdminPassword((int) $reset['user_id']);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function changePassword(int $userId, array $input): void
    {
        $user = User::find($userId);
        if ($user === null || !password_verify((string) ($input['current_password'] ?? ''), $user['password_hash'])) {
            AuditLog::record('auth.password_change_failed', [], $userId);
            throw new ValidationException(['current_password' => 'La contraseña actual no es correcta.']);
        }
        $data = Validator::make($input, ['password' => 'required|password|confirmed']);
        if (($problem = SecurityPolicy::passwordProblem($data['password'], (string) $user['email'])) !== null) {
            throw new ValidationException(['password' => $problem]);
        }
        if (password_verify($data['password'], $user['password_hash'])) {
            throw new ValidationException(['password' => 'La contraseña nueva tiene que ser distinta de la actual.']);
        }
        self::storePassword($userId, $data['password']);
        AuditLog::record('auth.password_changed', [], $userId);
        // Las demás sesiones quedaron cerradas; esta sigue abierta con la versión nueva.
        Auth::login($userId);
        Installer::forgetInitialAdminPassword($userId);
    }

    /**
     * Cambia el email: pide la contraseña actual, porque con el email se puede recuperar la cuenta
     * (si alguien usara una sesión abierta ajena, no podría quedarse con ella).
     */
    public function changeEmail(int $userId, string $name, string $email, string $currentPassword): void
    {
        $user = User::find($userId);
        $email = mb_strtolower(trim($email));
        if ($user === null) {
            throw new BusinessRuleException('La cuenta no existe.');
        }
        if ($email !== $user['email'] && !password_verify($currentPassword, $user['password_hash'])) {
            AuditLog::record('auth.email_change_failed', [], $userId);
            throw new ValidationException(['current_password_profile' => 'Para cambiar el email ingresá tu contraseña actual.'], ['name' => $name, 'email' => $email]);
        }
        if (Database::scalar('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId])) {
            throw new ValidationException(['email' => 'Ese email ya está en uso por otra cuenta.'], ['name' => $name, 'email' => $email]);
        }
        Database::update('users', ['name' => $name, 'email' => $email], ['id' => $userId]);
        AuditLog::record($email !== $user['email'] ? 'auth.email_changed' : 'settings.profile', $email !== $user['email'] ? ['from' => $user['email'], 'to' => $email] : [], $userId);
    }

    /** Guarda la contraseña nueva y sube la versión de sesión (cierra las demás sesiones). */
    private static function storePassword(int $userId, string $password): void
    {
        Database::query(
            'UPDATE users SET password_hash = ?, session_version = session_version + 1, must_change_password = 0, password_changed_at = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), now_utc(), $userId],
        );
    }
}
