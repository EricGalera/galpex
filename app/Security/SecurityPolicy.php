<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Request;

/**
 * Controles de seguridad transversales, en un solo lugar para poder revisarlos y probarlos:
 *
 *  - cabeceras HTTP de endurecimiento (CSP, anti-clickjacking, no-cache de datos privados…);
 *  - detección de peticiones locales (las tareas de instalación solo se aceptan desde la propia PC);
 *  - validación de redirecciones internas (evita redirecciones abiertas);
 *  - política de contraseñas;
 *  - neutralización de fórmulas en CSV (inyección de fórmulas en Excel);
 *  - limpieza de texto para los logs (evita falsificar líneas).
 */
final class SecurityPolicy
{
    /** Contraseñas demasiado comunes: se rechazan aunque cumplan el largo y la mezcla de caracteres. */
    private const COMMON_PASSWORDS = [
        'password1', 'password123', 'passw0rd', 'qwerty123', 'qwerty1234', 'abc12345', 'abcd1234', 'abc123456',
        'admin123', 'admin1234', 'administrador1', 'iloveyou1', 'welcome1', 'letmein1', 'monkey123', 'dragon123',
        'contrasena1', 'contraseña1', 'clave1234', 'galpex123', 'galpex1234', 'bolsa1234', 'merval123',
        'boca1234', 'river1234', 'argentina1', 'argentina123', '1q2w3e4r', '1qaz2wsx', 'zaq12wsx', 'q1w2e3r4',
        'asdf1234', 'test1234', 'prueba123', 'usuario1', 'usuario123', 'changeme1', 'secret123', 'football1',
    ];

    /** Máximo de bytes que bcrypt tiene en cuenta: más allá se ignora en silencio. */
    public const PASSWORD_MAX_BYTES = 72;

    /**
     * Cabeceras que se agregan a todas las respuestas dinámicas.
     *
     * @return array<string,string>
     */
    public static function headers(bool $https): array
    {
        $headers = [
            // Solo se ejecuta código propio: ni scripts en línea ni de otros dominios (mitiga XSS).
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https:",
                "font-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]),
            'X-Frame-Options' => 'DENY',                       // nadie puede meter GALPEX en un iframe (clickjacking)
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',                // los enlaces externos no reciben la URL interna
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // Carteras, saldos y movimientos no quedan guardados en la caché del navegador ni de un proxy.
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
        ];
        if ($https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    /** ¿La petición viene de la misma computadora donde corre XAMPP? */
    public static function isLoopback(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
    }

    /**
     * Devuelve la ruta interna si es segura para redirigir, o el valor por defecto.
     * Acepta solo rutas del propio sitio ("/carteras/3"); rechaza "//evil.com", "/\evil.com",
     * esquemas ("javascript:"), saltos de línea y cualquier carácter raro.
     */
    public static function safeRedirectPath(mixed $path, string $fallback = '/inicio'): string
    {
        if (!is_string($path) || $path === '' || strlen($path) > 300) {
            return $fallback;
        }
        if (!preg_match('#^/(?![/\\\\])[A-Za-z0-9/_\-.~%?=&]*$#', $path) || str_contains($path, '..')) {
            return $fallback;
        }

        return $path;
    }

    /**
     * Problema de la contraseña o null si es aceptable. Sigue la guía NIST 800-63B:
     * largo mínimo, lista de contraseñas comunes y que no contenga el email; sin reglas absurdas.
     */
    public static function passwordProblem(string $password, string $email = ''): ?string
    {
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'La contraseña debe tener al menos 8 caracteres, con letras y números.';
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            return 'La contraseña no puede superar los 72 caracteres.';
        }
        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON_PASSWORDS, true) || preg_match('/^(.)\1+$/u', $password)) {
            return 'Esa contraseña es demasiado común. Elegí otra más difícil de adivinar.';
        }
        $local = mb_strtolower((string) strstr($email, '@', true));
        if (mb_strlen($local) >= 4 && str_contains($lower, $local)) {
            return 'La contraseña no puede contener tu email.';
        }

        return null;
    }

    /** Contraseña aleatoria legible (sin caracteres que se confundan: 0/O, 1/l/I). */
    public static function randomPassword(int $length = 16): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        // Garantiza letras y números para cumplir la propia política.
        return $out . random_int(2, 9);
    }

    /**
     * Celda segura para CSV: un texto que empieza con = + - @ o tabulación se interpretaría
     * como fórmula al abrirlo en Excel. Los números (incluidos los negativos) quedan igual.
     */
    public static function csvCell(mixed $value): mixed
    {
        if (!is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    /** Texto para una línea de log: sin saltos de línea ni caracteres de control. */
    public static function logText(string $text, int $max = 2000): string
    {
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);

        return mb_substr($clean, 0, $max);
    }

    /**
     * Comprueba con Origin/Sec-Fetch-Site que un envío salga del propio sitio
     * (segunda barrera contra CSRF, además del token).
     */
    public static function isSameOrigin(Request $request): bool
    {
        $site = strtolower($request->header('Sec-Fetch-Site'));
        if ($site === 'cross-site') {
            return false;
        }
        $origin = $request->header('Origin');
        if ($origin === '' || $origin === 'null') {
            return $origin === ''; // sin Origin (navegadores viejos, tests): decide el token
        }
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $port = parse_url($origin, PHP_URL_PORT);
        $expected = strtolower($request->header('Host'));

        return $expected !== '' && ($host . ($port ? ':' . $port : '')) === $expected;
    }
}
