<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Preparación previa a Laravel
|--------------------------------------------------------------------------
| Corre antes de cargar el framework, sin dependencias, para que GALPEX funcione en XAMPP
| sin instalar nada a mano:
|   1. Verifica la versión de PHP.
|   2. Si falta vendor/ (las librerías de Laravel) y está el paquete storage/vendor.zip,
|      lo descomprime. Con Composer instalado alcanza con "composer install".
|   3. Si falta .env, lo crea desde .env.example con una APP_KEY nueva (Laravel la necesita
|      para cifrar las cookies desde la primera petición).
*/

if (PHP_VERSION_ID < 80200) {
    galpex_abort('GALPEX necesita PHP 8.2 o superior. Versión actual: ' . PHP_VERSION . '. Actualizá XAMPP.');
}

galpex_ensure_vendor(dirname(__DIR__));
galpex_ensure_env(dirname(__DIR__));

function galpex_ensure_vendor(string $root): void
{
    if (is_file($root . '/vendor/autoload.php')) {
        return;
    }
    $bundle = $root . '/storage/vendor.zip';
    if (!is_file($bundle)) {
        galpex_abort('Faltan las librerías de Laravel (carpeta vendor). Ejecutá "composer install" en la carpeta del proyecto.');
    }

    @set_time_limit(0);
    $lock = fopen($root . '/storage/vendor.lock', 'c');
    flock($lock, LOCK_EX); // dos pestañas abiertas a la vez no descomprimen en paralelo
    try {
        if (is_file($root . '/vendor/autoload.php')) {
            return;
        }
        $tmp = $root . '/vendor.tmp-' . bin2hex(random_bytes(4));
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($bundle) !== true || !$zip->extractTo($tmp)) {
                galpex_abort('No se pudo descomprimir storage/vendor.zip.');
            }
            $zip->close();
        } else {
            (new PharData($bundle))->extractTo($tmp, null, true);
        }
        if (!rename($tmp . '/vendor', $root . '/vendor')) {
            galpex_abort('No se pudo crear la carpeta vendor. Verificá los permisos de la carpeta del proyecto.');
        }
        @rmdir($tmp);
        @unlink($bundle);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($root . '/storage/vendor.lock');
    }
}

function galpex_ensure_env(string $root): void
{
    $env = $root . '/.env';
    if (is_file($env) || !is_file($root . '/.env.example')) {
        return;
    }
    $key = 'base64:' . base64_encode(random_bytes(32));
    $content = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, (string) file_get_contents($root . '/.env.example'));
    if (@file_put_contents($env, $content, LOCK_EX) === false) {
        galpex_abort('No se pudo crear el archivo .env. Verificá los permisos de la carpeta del proyecto.');
    }
}

function galpex_abort(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>GALPEX</title>'
        . '<p style="font:16px system-ui;max-width:640px;margin:15vh auto">' . htmlspecialchars($message) . '</p>';
    exit;
}
