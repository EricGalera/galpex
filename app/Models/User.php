<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Usuarios y su perfil (moneda, zona horaria, tema, tasa libre de riesgo).
 */
final class User
{
    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::first('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public static function create(string $name, string $email, string $password, string $role = 'user', string $currency = 'USD'): int
    {
        return Database::transaction(function () use ($name, $email, $password, $role, $currency) {
            $id = Database::insert('users', [
                'name' => $name,
                'email' => mb_strtolower(trim($email)),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
            Database::insert('user_profiles', ['user_id' => $id, 'base_currency' => $currency]);
            Database::insert('watchlists', ['user_id' => $id, 'name' => 'Seguimiento']);

            return $id;
        });
    }

    /** @return array<string,mixed>|null */
    public static function profile(int $userId): ?array
    {
        return Database::first('SELECT * FROM user_profiles WHERE user_id = ?', [$userId]);
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }

    /**
     * Guarda preferencias del perfil (moneda, zona horaria, tema, tasa libre de riesgo, cartera predeterminada).
     *
     * @param array<string, mixed> $values solo columnas de user_profiles ya validadas
     */
    public static function updatePreferences(int $userId, array $values): void
    {
        Database::update('user_profiles', $values, ['user_id' => $userId]);
    }

    /**
     * Usuarios con la cantidad de carteras de cada uno (panel de administración).
     *
     * @return list<array<string, mixed>>
     */
    public static function allWithPortfolioCount(): array
    {
        return Database::all(
            'SELECT u.id, u.name, u.email, u.role, u.status, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM portfolios p WHERE p.user_id = u.id) AS portfolios
             FROM users u ORDER BY u.id',
        );
    }

    /** Cuentas que todavía tienen que elegir su contraseña. */
    public static function countPendingPasswordChange(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM users WHERE must_change_password = 1');
    }
}
