<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Cifrado simétrico autenticado (AES-256-GCM con OpenSSL) para guardar
 * credenciales de terceros. La clave sale de APP_KEY en .env y nunca se
 * envía al navegador. Se usa OpenSSL porque viene activo en XAMPP.
 *
 * Formato guardado: base64( iv[12] . tag[16] . texto cifrado ).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_BYTES = 32;
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    private static function key(): string
    {
        $raw = (string) config('app.key', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = (string) base64_decode(substr($raw, 7), true);
        }
        if (strlen($raw) !== self::KEY_BYTES) {
            throw new RuntimeException('APP_KEY no está configurada. Ejecutá "php artisan key:generate".');
        }

        return $raw;
    }

    private static function requireOpenSsl(): void
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Falta la extensión OpenSSL de PHP: activá extension=openssl en php.ini y reiniciá Apache.');
        }
    }

    public static function encrypt(string $plain): string
    {
        self::requireOpenSsl();
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);
        if ($cipher === false) {
            throw new RuntimeException('No se pudo cifrar el dato.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        self::requireOpenSsl();
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= self::IV_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Dato cifrado inválido.');
        }
        $plain = openssl_decrypt(
            substr($raw, self::IV_BYTES + self::TAG_BYTES),
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::IV_BYTES),
            substr($raw, self::IV_BYTES, self::TAG_BYTES),
        );
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar: ¿cambió APP_KEY?');
        }

        return $plain;
    }

    /** No depende de ninguna extensión: random_bytes es parte de PHP. */
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(self::KEY_BYTES));
    }

    /** Muestra solo los últimos 4 caracteres: "••••••3f9a". */
    public static function mask(string $value): string
    {
        return str_repeat('•', 6) . substr($value, -4);
    }
}
