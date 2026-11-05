<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/** Registro de eventos importantes para trazabilidad. */
final class AuditLog
{
    /** @param array<string,mixed> $details */
    public static function record(string $event, array $details = [], ?int $userId = null, ?string $ip = null): void
    {
        Database::insert('audit_log', [
            'user_id' => $userId,
            'event' => $event,
            'ip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        return Database::all(
            'SELECT a.*, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . (int) $limit,
        );
    }

    /**
     * Actividad reciente de un usuario (pantalla de Configuración).
     *
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId, int $limit = 12): array
    {
        return Database::all('SELECT event, ip, created_at FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$userId]);
    }

    /**
     * Resumen de seguridad de las últimas 24 horas: ingresos fallidos, bloqueos, conexiones y restablecimientos.
     *
     * @return array<string, mixed>|null
     */
    public static function securitySummary(): ?array
    {
        return Database::first(
            "SELECT SUM(event = 'auth.failed') AS failed, SUM(event = 'auth.locked') AS locked,
                    COUNT(DISTINCT CASE WHEN event = 'auth.failed' THEN ip END) AS ips,
                    SUM(event = 'auth.password_reset') AS resets
             FROM audit_log WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY",
        );
    }

    /**
     * Conexiones con más intentos fallidos o bloqueos en las últimas 24 horas.
     *
     * @return list<array<string, mixed>>
     */
    public static function topFailedIps(int $limit = 5): array
    {
        return Database::all(
            "SELECT ip, COUNT(*) AS n, MAX(created_at) AS last FROM audit_log
             WHERE event IN ('auth.failed', 'auth.locked') AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY
             GROUP BY ip ORDER BY n DESC LIMIT " . max(1, $limit),
        );
    }
}
