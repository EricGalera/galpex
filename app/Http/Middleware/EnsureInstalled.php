<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request as GalpexRequest;
use App\Services\Setup\Installer;
use App\Services\Setup\SetupScreens;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Instalación y actualización automáticas, sin asistente:
 *  - primera visita: muestra "Preparando GALPEX" y /preparar crea la base, las tablas y el catálogo;
 *  - versión anterior del esquema: muestra "Actualizando GALPEX" y /preparar aplica las migraciones.
 * Solo se acepta desde la computadora donde corre XAMPP (ver SetupScreens::maySetup()).
 */
final class EnsureInstalled
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $installed = Installer::isInstalled();
        if ($installed && !Installer::needsUpgrade()) {
            return $next($request);
        }
        if (!SetupScreens::maySetup(GalpexRequest::fromLaravel($request))) {
            return SetupScreens::maintenancePage()->toResponse($request);
        }
        if (trim($request->path(), '/') === 'preparar') {
            return $next($request); // SetupController hace el trabajo
        }

        return SetupScreens::preparingPage($installed)->toResponse($request);
    }
}
