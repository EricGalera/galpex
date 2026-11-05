<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Reglas de seguridad de la sesión que se suman a las de Laravel:
 *  - vence a las N minutos sin uso (SESSION_IDLE_MINUTES) y siempre a las N horas (SESSION_ABSOLUTE_HOURS);
 *  - el ID de sesión cambia cada 15 minutos: una cookie copiada deja de servir enseguida.
 *    La sesión anterior queda marcada y vence en 60 segundos, así no se corta una actualización
 *    de precios que estaba en curso.
 */
final class SessionSecurity
{
    private const ROTATE_EVERY = 900;
    private const GRACE = 60;

    public function handle(Request $request, Closure $next): BaseResponse
    {
        $session = $request->session();
        $now = time();
        $idle = (int) config('app.session_idle_minutes', 120) * 60;
        $absolute = (int) config('app.session_absolute_hours', 12) * 3600;

        $replacedAt = $session->get('_replaced_at');
        $last = $session->get('_last_activity');
        $born = (int) $session->get('_created', $now);

        $expired = ($replacedAt !== null && $now - (int) $replacedAt > self::GRACE)
            || ($last !== null && $now - (int) $last > $idle)
            || ($now - $born > $absolute);
        if ($expired) {
            $session->invalidate();
            $session->regenerateToken();
            $born = $now;
            $replacedAt = null;
        }
        $session->put('_created', $born);
        $session->put('_last_activity', $now);

        if ($replacedAt === null && $now - (int) $session->get('_rotated', 0) > self::ROTATE_EVERY) {
            $this->rotate($session, $now);
        }

        return $next($request);
    }

    /** Guarda la sesión vieja marcada como reemplazada y sigue con un ID nuevo. */
    private function rotate(\Illuminate\Contracts\Session\Session $session, int $now): void
    {
        $oldId = $session->getId();
        $handler = $session->getHandler();
        $handler->write($oldId, serialize(['_replaced_at' => $now] + $session->all()));
        $session->migrate(false);
        $session->put('_rotated', $now);
    }
}
