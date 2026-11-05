<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\HttpException;
use App\Security\Auth;
use App\Security\RateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/** Límite de solicitudes por minuto a la API interna, por usuario o IP (alias "throttle"). */
final class ThrottleApi
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $key = 'api:' . (Auth::id() ?? $request->server('REMOTE_ADDR'));
        $max = (int) config('app.api_rate_per_minute', 120);
        if (!RateLimiter::attempt($key, $max, 60)) {
            throw new HttpException(429, 'Demasiadas solicitudes. Esperá un minuto.');
        }

        return $next($request);
    }
}
