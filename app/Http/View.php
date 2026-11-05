<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\View\Factory;

/**
 * Vistas: plantillas PHP de resources/views renderizadas con el motor de vistas de Laravel.
 * Cada pantalla se arma dentro de un layout (la pantalla queda en la variable $content).
 * Las vistas escapan toda salida con e() para prevenir XSS.
 */
final class View
{
    public static function share(string $key, mixed $value): void
    {
        self::factory()->share($key, $value);
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::factory()->shared($key, $default);
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }

        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * Renderiza una vista parcial (componentes).
     *
     * @param array<string,mixed> $data
     */
    public static function partial(string $template, array $data = []): string
    {
        return self::factory()->make(str_replace('/', '.', $template), $data)->render();
    }

    private static function factory(): Factory
    {
        return app('view');
    }
}
