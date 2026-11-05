<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Cabeceras HTTP de endurecimiento en todas las respuestas (incluidas las de error):
 * Content-Security-Policy, anti-clickjacking, no-cache de datos privados, etc. Ver App\Security\SecurityPolicy::headers().
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $response = $next($request);
        foreach (SecurityPolicy::headers($request->isSecure()) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
