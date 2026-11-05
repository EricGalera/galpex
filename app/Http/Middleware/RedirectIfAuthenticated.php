<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;
use App\Security\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/** Pantallas solo para visitantes (alias "guest"): login, registro, recuperación. */
final class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        return Auth::check() ? Response::redirect('/inicio')->toResponse($request) : $next($request);
    }
}
