<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\HttpException;
use App\Security\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/** Solo usuarios con rol administrador (alias "admin"). */
final class RequireAdmin
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, 'Esta sección es solo para administradores.');
        }

        return $next($request);
    }
}
