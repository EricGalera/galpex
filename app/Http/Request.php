<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request as LaravelRequest;

/**
 * Petición HTTP actual, como la usan los controladores de GALPEX.
 *
 * Envuelve la Request de Laravel (Illuminate\Http\Request): Laravel la crea, resuelve la ruta y
 * la inyecta en cada controlador a través del contenedor de servicios (ver AppServiceProvider).
 * Esta clase solo ofrece una interfaz corta y en el mismo idioma que el resto del código.
 */
final class Request
{
    public function __construct(private LaravelRequest $request) {}

    public static function fromLaravel(LaravelRequest $request): self
    {
        return new self($request);
    }

    /** Petición de Laravel original, por si se necesita algo más específico. */
    public function laravel(): LaravelRequest
    {
        return $this->request;
    }

    /** Método HTTP, con el reemplazo de formularios (_method=DELETE) que resuelve Laravel. */
    public function method(): string
    {
        return strtoupper($this->request->method());
    }

    /** Ruta relativa a la carpeta de la app, por ejemplo "/carteras/3". */
    public function path(): string
    {
        $path = $this->request->path();

        return $path === '/' ? '/' : '/' . trim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    /** Valor de texto: si llega una lista u otro tipo (name[]=…) devuelve el valor por defecto. */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->request->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->request->query($key, $default);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->request->all();
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        return $this->request->only($keys);
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        return $this->request->isJson() ? (array) $this->request->json()->all() : [];
    }

    public function header(string $name): string
    {
        return (string) $this->request->header($name, '');
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path(), '/api/')
            || $this->request->expectsJson()
            || $this->request->ajax();
    }

    public function ip(): string
    {
        return (string) ($this->request->server('REMOTE_ADDR') ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) $this->request->userAgent(), 0, 255);
    }

    /** Parámetro de la ruta: en /carteras/{id}, param('id'). */
    public function param(string $key, ?string $default = null): ?string
    {
        $value = $this->request->route($key);

        return $value === null ? $default : (string) $value;
    }

    public function isSecure(): bool
    {
        return $this->request->isSecure();
    }
}
