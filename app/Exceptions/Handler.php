<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\SecurityPolicy;
use App\Services\Setup\SetupScreens;
use App\Support\Logger;
use Illuminate\Session\TokenMismatchException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Manejo centralizado de errores: convierte excepciones en respuestas HTML o JSON con el diseño de GALPEX.
 * Laravel lo llama para toda excepción (bootstrap/app.php → withExceptions); también traduce
 * las de Laravel y Symfony (página inexistente, formulario vencido) y los problemas con MySQL.
 */
final class Handler
{
    public static function render(Throwable $e, Request $request): Response
    {
        // Excepciones propias de Laravel y Symfony, con los textos de GALPEX.
        // Laravel convierte el token CSRF vencido en un error HTTP 419 antes de llegar acá.
        if ($e instanceof TokenMismatchException || ($e instanceof SymfonyHttpException && $e->getStatusCode() === 419)) {
            Logger::warning('Envío rechazado por CSRF', ['path' => SecurityPolicy::logText($request->path(), 200), 'ip' => $request->ip()]);
            // 403 (y no 419): Apache convierte los códigos que no conoce en 500.
            $e = new HttpException(403, 'El formulario venció. Recargá la página e intentá de nuevo.');
        } elseif ($e instanceof NotFoundHttpException || $e instanceof MethodNotAllowedHttpException) {
            $e = new HttpException(404, 'La página que buscás no existe.');
        } elseif ($e instanceof SymfonyHttpException) {
            $e = new HttpException($e->getStatusCode(), $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo completar la solicitud.');
        } elseif ($e instanceof PDOException && !$request->wantsJson() && self::isConnectionProblem($e)) {
            return SetupScreens::databaseProblem($e, $request->path());
        }

        // El detalle técnico (archivo, línea, traza) solo se muestra en la PC donde corre GALPEX:
        // a cualquier otro visitante le revelaría rutas y estructura interna.
        $debug = (bool) config('app.debug', false) && SecurityPolicy::isLoopback($request->ip());

        if ($e instanceof ValidationException) {
            if ($request->wantsJson()) {
                return Response::json(['error' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            session()->flash('form_errors', $e->errors());
            session()->flashInput(self::withoutSecrets($e->input()));

            return Response::back();
        }

        if ($e instanceof BusinessRuleException) {
            if ($request->wantsJson()) {
                return Response::json(['error' => $e->getMessage()], 422);
            }
            session()->flash('error', $e->getMessage());
            session()->flashInput(self::withoutSecrets($request->all()));

            return Response::back();
        }

        $status = $e instanceof HttpException ? $e->statusCode() : 500;
        $message = $e instanceof HttpException ? $e->getMessage() : 'Ocurrió un error interno. Quedó registrado en storage/logs.';

        if ($status >= 500) {
            Logger::error(SecurityPolicy::logText($e->getMessage()), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => SecurityPolicy::logText($request->path(), 300),
            ]);
        }

        if ($request->wantsJson()) {
            $payload = ['error' => $message];
            if ($debug && $status >= 500) {
                $payload['debug'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
            }

            return Response::json($payload, $status);
        }

        try {
            $html = View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'debug' => $debug && $status >= 500 ? $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString() : null,
                'pageTitle' => "Error $status",
            ], 'layouts/minimal');
        } catch (Throwable) {
            $html = '<h1>Error ' . $status . '</h1><p>' . htmlspecialchars($message) . '</p>';
        }

        return Response::html($html, $status);
    }

    /** MySQL apagado, credenciales rechazadas o base/tabla inexistente (no un error de una consulta puntual). */
    private static function isConnectionProblem(PDOException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? (preg_match('/\[(\d{4})\]/', $e->getMessage(), $m) ? $m[1] : $e->getCode()));

        return in_array($code, ['2002', '2003', '2006', '2013', '2054', '1044', '1045', '1049', '1146'], true);
    }

    /**
     * Los valores que se vuelven a mostrar en un formulario con errores se guardan en la sesión:
     * nunca contraseñas, tokens ni claves de API.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function withoutSecrets(array $input): array
    {
        return array_filter(
            $input,
            static fn($key) => !preg_match('/password|_token|secret|api_key|token/i', (string) $key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
