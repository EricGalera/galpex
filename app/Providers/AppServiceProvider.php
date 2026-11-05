<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Request;
use Illuminate\Support\ServiceProvider;

/**
 * Registro de servicios de GALPEX en el contenedor de Laravel.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Los controladores reciben App\Http\Request: se arma a partir de la petición de Laravel.
        $this->app->bind(Request::class, fn($app) => Request::fromLaravel($app['request']));
    }
}
