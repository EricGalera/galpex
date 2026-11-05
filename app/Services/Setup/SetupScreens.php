<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\SecurityPolicy;
use App\Support\Database;
use App\Support\Logger;
use PDOException;

/**
 * Pantallas de la instalación automática y de los problemas con MySQL:
 * "Preparando GALPEX", "Actualizando GALPEX", "En mantenimiento" y "Falta encender MySQL".
 */
final class SetupScreens
{
    /** Pantalla "Preparando GALPEX" / "Actualizando GALPEX" mientras /preparar trabaja. */
    public static function preparingPage(bool $upgrading): Response
    {
        return Response::html(View::render('setup/preparing', [
            'pageTitle' => $upgrading ? 'Actualizando' : 'Preparando',
            'refreshTo' => '/preparar',
            'upgrading' => $upgrading,
        ], 'layouts/minimal'));
    }

    /** Pantalla para quien entra desde otra PC mientras falta instalar o actualizar. */
    public static function maintenancePage(): Response
    {
        return Response::html(View::render('errors/error', [
            'status' => 503,
            'message' => 'GALPEX se está instalando o actualizando. Abrilo desde la computadora donde está instalado para terminar.',
            'debug' => null,
            'pageTitle' => 'En mantenimiento',
        ], 'layouts/minimal'), 503);
    }

    /** ¿Se puede instalar o actualizar desde esta petición? Solo desde la propia PC (o SETUP_ALLOW_REMOTE=true). */
    public static function maySetup(Request $request): bool
    {
        return SecurityPolicy::isLoopback($request->ip()) || (bool) config('app.setup_allow_remote', false);
    }

    /**
     * Error de MySQL fuera de una consulta normal: si la base fue borrada (por ejemplo desde phpMyAdmin)
     * se vuelve a preparar sola; si MySQL está apagado o rechaza el usuario, se explica cómo arreglarlo.
     */
    public static function databaseProblem(PDOException $e, string $retry = '/'): Response
    {
        $code = self::dbErrorCode($e);
        if (in_array($code, ['1049', '1146', '42S02'], true)) {
            Installer::forget();
            Database::reconnect();
            Logger::warning('Base de datos ausente o incompleta: se reinstala automáticamente. ' . $e->getMessage());

            return Response::redirect('/');
        }

        $reason = match (true) {
            in_array($code, ['2002', '2003', '2006', '2013', '2054'], true) => 'off',
            in_array($code, ['1044', '1045'], true) => 'denied',
            default => 'other',
        };
        Logger::error('Problema con MySQL (' . $code . '): ' . $e->getMessage());

        return Response::html(View::render('setup/database', [
            'pageTitle' => 'Base de datos',
            'reason' => $reason,
            'detail' => $reason === 'other' ? '' : self::cleanMessage($e->getMessage()),
            'retry' => $retry,
        ], 'layouts/minimal'), 503);
    }

    private static function dbErrorCode(PDOException $e): string
    {
        $driver = $e->errorInfo[1] ?? null;
        if ($driver !== null) {
            return (string) $driver;
        }
        if (preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) {
            return $m[1];
        }

        return (string) $e->getCode();
    }

    private static function cleanMessage(string $message): string
    {
        // Windows devuelve el texto del sistema en la codificación local; se descarta lo que no sea UTF-8.
        $message = preg_replace('/^SQLSTATE\[[^\]]+\]\s*(\[\d+\])?\s*/', '', $message) ?? $message;

        return mb_substr(mb_convert_encoding($message, 'UTF-8', 'UTF-8'), 0, 160);
    }
}
