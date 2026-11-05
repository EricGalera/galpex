<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Arranque de GALPEX sobre Laravel
|--------------------------------------------------------------------------
| Rutas, middleware y manejo de errores. Lo usan la web (public/index.php),
| la consola (artisan) y las pruebas (tests/run.php).
*/

use App\Exceptions\BusinessRuleException;
use App\Exceptions\Handler;
use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;
use App\Http\Middleware;
use App\Http\Request as GalpexRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware as MiddlewareConfig;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (MiddlewareConfig $middleware) {
        // Cabeceras de seguridad en todas las respuestas.
        $middleware->append(Middleware\SecurityHeaders::class);
        // El validador de GALPEX ya recorta y normaliza los datos: se conservan tal cual llegan.
        $middleware->remove([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        // Grupo "web" de Laravel (cookies cifradas, sesión, token CSRF) + los de GALPEX.
        $middleware->web(append: [
            Middleware\SessionSecurity::class,
            Middleware\VerifySameOrigin::class,
            Middleware\EnsureInstalled::class,
            Middleware\ApplyUserPreferences::class,
        ]);

        $middleware->alias([
            'auth' => Middleware\Authenticate::class,
            'guest' => Middleware\RedirectIfAuthenticated::class,
            'admin' => Middleware\RequireAdmin::class,
            'throttle' => Middleware\ThrottleApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Errores esperables (reglas de negocio, formularios, 404…): no ensucian laravel.log.
        $exceptions->dontReport([BusinessRuleException::class, ValidationException::class, HttpException::class, TokenMismatchException::class]);

        // Toda excepción se muestra con las pantallas y mensajes de GALPEX.
        $exceptions->render(function (Throwable $e, Request $request) {
            return Handler::render($e, GalpexRequest::fromLaravel($request))->toResponse($request);
        });
    })
    ->create();
