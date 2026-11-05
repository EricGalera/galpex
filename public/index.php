<?php

declare(strict_types=1);

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Front controller: toda petición web entra por acá
|--------------------------------------------------------------------------
*/

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../bootstrap/prepare.php';

// En XAMPP la app vive en una carpeta (http://localhost/Galpex) y el .htaccess de la raíz
// redirige a /public. Se informa la carpeta real para que Laravel arme bien las rutas y URLs.
if (isset($_SERVER['SCRIPT_NAME']) && str_ends_with(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/public/index.php')) {
    $_SERVER['SCRIPT_NAME'] = substr(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), 0, -strlen('/public/index.php')) . '/index.php';
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
}

// Con el servidor de PHP (php -S) se sirven directo los archivos estáticos.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

header_remove('X-Powered-By');

require __DIR__ . '/../vendor/autoload.php';

(require_once __DIR__ . '/../bootstrap/app.php')->handleRequest(Request::capture());
