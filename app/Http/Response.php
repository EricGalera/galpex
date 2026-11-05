<?php

declare(strict_types=1);

namespace App\Http;

use App\Security\SecurityPolicy;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response as LaravelResponse;

/**
 * Respuesta HTTP. Los controladores devuelven instancias de esta clase y Laravel la convierte
 * en su propia respuesta (interfaz Responsable). Las cabeceras de seguridad las agrega el
 * middleware App\Http\Middleware\SecurityHeaders a todas las respuestas.
 */
class Response implements Responsable
{
    /** @param array<string,string> $headers */
    public function __construct(
        protected string $body = '',
        protected int $status = 200,
        protected array $headers = [],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return new self((string) $body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $location = preg_match('#^https?://#', $to) ? $to : app_url($to);

        return new self('', $status, ['Location' => $location]);
    }

    public static function back(string $fallback = '/inicio'): self
    {
        $referer = (string) request()->headers->get('referer', '');
        $host = (string) request()->getHttpHost();
        if ($referer !== '' && $host !== '' && parse_url($referer, PHP_URL_HOST) === explode(':', $host)[0]) {
            return new self('', 302, ['Location' => $referer]);
        }

        return self::redirect($fallback);
    }

    /**
     * Descarga de archivo CSV. Usa ";" y BOM UTF-8 para que Excel en español lo abra bien.
     *
     * @param list<string> $headerRow
     * @param list<list<scalar|null>> $rows
     */
    public static function csv(string $filename, array $headerRow, array $rows): self
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_map([SecurityPolicy::class, 'csvCell'], $headerRow), ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, array_map([SecurityPolicy::class, 'csvCell'], $row), ';', '"', '\\');
        }
        rewind($fh);
        $content = (string) stream_get_contents($fh);
        fclose($fh);

        return new self($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"',
        ]);
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /** Conversión a la respuesta de Laravel. */
    public function toResponse($request): LaravelResponse
    {
        return new LaravelResponse($this->body, $this->status, $this->headers);
    }
}
