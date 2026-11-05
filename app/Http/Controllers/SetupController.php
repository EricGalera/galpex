<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Security\Auth;
use App\Services\Setup\Installer;
use App\Services\Setup\SetupScreens;
use PDOException;

/**
 * /preparar: instala GALPEX la primera vez o lo actualiza a la versión nueva del esquema.
 * EnsureInstalled ya verificó que la petición viene de la propia computadora.
 */
final class SetupController extends Controller
{
    public function run(Request $request): Response
    {
        if (!Installer::isInstalled()) {
            try {
                (new Installer())->autoInstall();
            } catch (PDOException $e) {
                return SetupScreens::databaseProblem($e, '/preparar');
            }
            session()->flash('success', 'GALPEX está listo. Creá tu cuenta para empezar a operar. La contraseña inicial del administrador quedó en ' . Installer::CREDENTIALS_FILE . '.');

            return Response::redirect('/registro');
        }

        if (Installer::needsUpgrade()) {
            (new Installer())->upgrade();
            $notice = 'GALPEX se actualizó.';
            if (is_file(base_path(Installer::CREDENTIALS_FILE))) {
                $notice .= ' La contraseña inicial del administrador está en la carpeta del proyecto, en ' . Installer::CREDENTIALS_FILE . '.';
            }
            session()->flash('success', $notice);

            // Directo a la pantalla final: con un paso intermedio el aviso se perdería.
            return Response::redirect(Auth::check() ? '/inicio' : '/login');
        }

        return Response::redirect('/');
    }
}
