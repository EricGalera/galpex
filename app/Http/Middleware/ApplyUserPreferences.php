<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\Auth;
use App\Services\Setup\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Throwable;

/** Muestra fechas y horas en la zona horaria que eligió el usuario (las fechas se guardan en UTC). */
final class ApplyUserPreferences
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (Installer::isInstalled() && !Installer::needsUpgrade()) {
            $user = Auth::user();
            if ($user && !empty($user['timezone'])) {
                try {
                    date_default_timezone_set((string) $user['timezone']);
                } catch (Throwable) {
                    // zona inválida: se mantiene la por defecto
                }
            }
        }

        return $next($request);
    }
}
