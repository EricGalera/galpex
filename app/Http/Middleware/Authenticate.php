<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;
use App\Security\Auth;
use App\Security\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Protege rutas (alias "auth"): exige sesión iniciada. Si la cuenta tiene que elegir una contraseña
 * nueva (contraseña inicial del administrador), solo deja entrar a Configuración.
 */
final class Authenticate
{
    /** Rutas permitidas mientras la contraseña está pendiente de cambio. */
    private const ALLOWED_WHILE_CHANGING = ['/configuracion', '/configuracion/contrasena', '/logout'];

    public function handle(Request $request, Closure $next): BaseResponse
    {
        $path = '/' . trim($request->path(), '/');
        $wantsJson = str_starts_with($path, '/api/') || $request->expectsJson();

        $user = Auth::user();
        if ($user === null) {
            if ($wantsJson) {
                return Response::json(['error' => 'Tu sesión expiró. Volvé a iniciar sesión.'], 401)->toResponse($request);
            }
            if ($request->isMethod('GET')) {
                session()->put('intended', SecurityPolicy::safeRedirectPath($path));
            }

            return Response::redirect('/login')->toResponse($request);
        }

        $readOnlyApi = $request->isMethod('GET') && str_starts_with($path, '/api/'); // franja de precios y avisos de la barra
        if (!empty($user['must_change_password']) && !$readOnlyApi && !in_array($path, self::ALLOWED_WHILE_CHANGING, true)) {
            if ($wantsJson) {
                return Response::json(['error' => 'Antes de seguir, elegí una contraseña nueva.'], 403)->toResponse($request);
            }
            session()->flash('error', 'Por seguridad, antes de seguir elegí una contraseña nueva para tu cuenta.');

            return Response::redirect('/configuracion')->toResponse($request);
        }

        return $next($request);
    }
}
