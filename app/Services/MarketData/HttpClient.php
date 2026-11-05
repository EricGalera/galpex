<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Exceptions\ProviderException;

/**
 * Cliente HTTP mínimo (cURL) para consumir APIs externas en JSON, XML o texto.
 */
class HttpClient
{
    /** Algunos servicios públicos (Yahoo, Google Noticias) rechazan clientes sin un navegador conocido. */
    public const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /**
     * @param array<string,scalar> $query
     * @param array<string,string> $headers
     * @return array<mixed>
     */
    public function getJson(string $url, array $query = [], array $headers = [], string $provider = ''): array
    {
        $body = $this->get($url, $query, ['Accept' => 'application/json'] + $headers, $provider);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ProviderException('Respuesta inválida del proveedor (no es JSON).', $provider);
        }

        return $data;
    }

    /**
     * Envía un cuerpo JSON por POST y devuelve la respuesta decodificada.
     *
     * @param array<mixed> $body
     * @return array<mixed>
     */
    public function postJson(string $url, array $body, string $provider = ''): array
    {
        $data = json_decode($this->get($url, [], ['Accept' => 'application/json', 'Content-Type' => 'application/json'], $provider, json_encode($body ?: new \stdClass())), true);
        if (!is_array($data)) {
            throw new ProviderException('Respuesta inválida del proveedor (no es JSON).', $provider);
        }

        return $data;
    }

    /**
     * Descarga el cuerpo de la respuesta como texto (o envía $postBody por POST).
     *
     * @param array<string,scalar> $query
     * @param array<string,string> $headers
     */
    public function get(string $url, array $query = [], array $headers = [], string $provider = '', ?string $postBody = null): string
    {
        if (!function_exists('curl_init')) {
            throw new ProviderException('La extensión cURL de PHP no está habilitada (php.ini → extension=curl).', $provider);
        }
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers += ['Accept' => '*/*', 'User-Agent' => self::BROWSER_UA];
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int) config('market.http.timeout', 8),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Solo HTTPS, también en redirecciones: un proveedor no puede desviar a file://, http:// ni a la red interna por otro protocolo.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 30 * 1024 * 1024, // ninguna respuesta legítima pesa más de 30 MB
        ];
        if ($postBody !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $postBody;
        }
        $ca = self::caBundle();
        if ($ca !== null) {
            $options[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $hint = str_contains($error, 'SSL') || str_contains($error, 'certificate') ? ' Configurá HTTP_CA_BUNDLE (ver docs/apis.md).' : '';
            throw new ProviderException("Sin conexión con el proveedor: $error.$hint", $provider);
        }
        if ($status === 429) {
            throw new ProviderException('El proveedor alcanzó su límite de solicitudes.', $provider, true);
        }
        if ($status >= 400) {
            throw new ProviderException("El proveedor respondió con error HTTP $status.", $provider);
        }

        return (string) $body;
    }

    /**
     * Certificados raíz para HTTPS. XAMPP en Windows suele traer cURL sin certificados
     * configurados: si .env no indica uno, se usan los que XAMPP incluye.
     */
    public static function caBundle(): ?string
    {
        $configured = config('market.http.ca_bundle');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }
        if ((string) ini_get('curl.cainfo') !== '' && is_file((string) ini_get('curl.cainfo'))) {
            return null;
        }
        $php = dirname(PHP_BINARY);
        foreach ([
            $php . '/extras/ssl/cacert.pem',
            dirname($php) . '/apache/bin/curl-ca-bundle.crt',
            dirname($php) . '/php/extras/ssl/cacert.pem',
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
