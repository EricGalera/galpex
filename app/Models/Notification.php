<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/** Centro de notificaciones del usuario. */
final class Notification
{
    public static function create(int $userId, string $title, ?string $body = null, ?string $link = null, string $kind = 'alert', ?int $alertId = null): int
    {
        return Database::insert('notifications', [
            'user_id' => $userId, 'alert_id' => $alertId, 'kind' => $kind,
            'title' => mb_substr($title, 0, 120), 'body' => $body !== null ? mb_substr($body, 0, 255) : null, 'link' => $link,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function forUser(int $userId, int $limit = 50, bool $unreadOnly = false): array
    {
        return Database::all(
            'SELECT * FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : '') . ' ORDER BY id DESC LIMIT ' . (int) $limit,
            [$userId],
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::query('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now_utc(), $userId]);
    }
}
