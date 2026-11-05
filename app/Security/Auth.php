<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Database;

/**
 * Usuario autenticado de la petición actual.
 *
 * Además del id, la sesión guarda la "versión de sesión" del usuario y una huella del navegador:
 * al cambiar o restablecer la contraseña la versión sube y todas las demás sesiones abiertas
 * (por ejemplo en otra computadora) se cierran solas; y una cookie copiada a otro navegador no sirve.
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = session()->get('user_id');
            if (is_int($id) || ctype_digit((string) $id)) {
                $user = Database::first(
                    'SELECT u.id, u.name, u.email, u.role, u.status, u.session_version, u.must_change_password,
                            p.base_currency, p.timezone, p.theme, p.country, p.default_portfolio_id, p.risk_free_rate
                     FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
                     WHERE u.id = ? AND u.status = \'active\'',
                    [(int) $id],
                );
                $valid = $user !== null
                    && (int) session()->get('auth_version', 0) === (int) $user['session_version']
                    && hash_equals((string) session()->get('auth_agent', ''), self::agentFingerprint());
                if ($valid) {
                    self::$user = $user;
                } else {
                    // Sesión de un usuario dado de baja, con contraseña cambiada o robada: se descarta.
                    session()->forget('user_id');
                    session()->forget('auth_version');
                    session()->forget('auth_agent');
                }
            }
        }

        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();

        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    public static function login(int $userId): void
    {
        session()->regenerate(true);   // ID nuevo al ingresar (evita la fijación de sesión)
        session()->regenerateToken();  // y token anti-CSRF nuevo
        session()->put('user_id', $userId);
        session()->put('auth_version', (int) Database::scalar('SELECT session_version FROM users WHERE id = ?', [$userId]));
        session()->put('auth_agent', self::agentFingerprint());
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        // Borra los datos, cambia el ID de sesión y el token anti-CSRF.
        session()->invalidate();
        session()->regenerateToken();
        self::$loaded = true;
        self::$user = null;
    }

    /** Huella del navegador: hash del User-Agent (no identifica a la persona, solo al programa). */
    private static function agentFingerprint(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    /** Fuerza recarga (por ejemplo después de cambiar preferencias). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }
}
