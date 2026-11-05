<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Models\Notification;
use App\Models\User;
use App\Security\Auth;
use App\Services\Alerts\AlertService;

/**
 * API JSON de notificaciones y del tema claro/oscuro del usuario.
 */
final class NotificationApiController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        (new AlertService())->evaluateThrottled($userId);
        $items = array_map(fn($n) => [
            'id' => (int) $n['id'], 'title' => $n['title'], 'body' => $n['body'], 'link' => $n['link'],
            'read_at' => $n['read_at'], 'ago' => time_ago($n['created_at']),
        ], Notification::forUser($userId, 12));

        return $this->json(['items' => $items, 'unread' => Notification::unreadCount($userId)]);
    }

    public function theme(Request $request): Response
    {
        $theme = $request->input('theme') === 'light' ? 'light' : 'dark';
        User::updatePreferences($this->userId(), ['theme' => $theme]);
        Auth::refresh();

        return $this->json(['theme' => $theme]);
    }
}
