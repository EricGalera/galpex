<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\HttpException;
use App\Http\Request as GalpexRequest;
use App\Security\SecurityPolicy;
use App\Support\Logger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Segunda barrera contra CSRF: además del token que valida Laravel (ValidateCsrfToken),
 * rechaza envíos que el navegador indica que salen de otro sitio (cabeceras Origin / Sec-Fetch-Site).
 * Cada rechazo queda en el log.
 */
final class VerifySameOrigin
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $req = GalpexRequest::fromLaravel($request);
        if (!$request->isMethodSafe() && !SecurityPolicy::isSameOrigin($req)) {
            Logger::warning('Envío rechazado: viene de otro sitio', [
                'path' => SecurityPolicy::logText($req->path(), 200),
                'ip' => $req->ip(),
                'origin' => SecurityPolicy::logText($req->header('Origin'), 200),
            ]);
            // 403 (y no 419): Apache convierte los códigos que no conoce en 500.
            throw new HttpException(403, 'El formulario venció. Recargá la página e intentá de nuevo.');
        }

        return $next($request);
    }
}
