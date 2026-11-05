<?php

declare(strict_types=1);

use App\Http\Controllers\Api as A;
use App\Http\Controllers as C;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de GALPEX (Laravel)
|--------------------------------------------------------------------------
| URLs en castellano; los nombres de clase y métodos, en inglés.
| Todas usan el grupo "web" de Laravel: sesión, cookies cifradas y token anti-CSRF.
| Middleware propios (bootstrap/app.php): guest, auth, admin, throttle.
*/

// Instalación y actualización automáticas (ver App\Http\Middleware\EnsureInstalled)
Route::get('/preparar', [C\SetupController::class, 'run']);

Route::get('/', [C\AuthController::class, 'home']);

// Visitantes
Route::middleware('guest')->group(function () {
    Route::get('/login', [C\AuthController::class, 'showLogin']);
    Route::post('/login', [C\AuthController::class, 'login']);
    Route::get('/registro', [C\AuthController::class, 'showRegister']);
    Route::post('/registro', [C\AuthController::class, 'register']);
    Route::get('/recuperar', [C\AuthController::class, 'showForgot']);
    Route::post('/recuperar', [C\AuthController::class, 'forgot']);
    Route::get('/restablecer/{token}', [C\AuthController::class, 'showReset']);
    Route::post('/restablecer/{token}', [C\AuthController::class, 'reset']);
});

// Usuarios autenticados
Route::middleware('auth')->group(function () {
    Route::post('/logout', [C\AuthController::class, 'logout']);
    Route::get('/inicio', [C\DashboardController::class, 'index']);

    Route::get('/mercados', [C\MarketController::class, 'index']);
    Route::get('/mercados/instrumento/{id}', [C\MarketController::class, 'show']);
    Route::post('/listas', [C\WatchlistController::class, 'store']);
    Route::post('/listas/{id}/alternar/{instrument}', [C\WatchlistController::class, 'toggle']);
    Route::post('/listas/{id}/eliminar', [C\WatchlistController::class, 'destroy']);

    Route::get('/carteras', [C\PortfolioController::class, 'index']);
    Route::get('/carteras/nueva', [C\PortfolioController::class, 'create']);
    Route::post('/carteras', [C\PortfolioController::class, 'store']);
    Route::get('/carteras/{id}', [C\PortfolioController::class, 'show']);
    Route::get('/carteras/{id}/editar', [C\PortfolioController::class, 'edit']);
    Route::post('/carteras/{id}', [C\PortfolioController::class, 'update']);
    Route::post('/carteras/{id}/archivar', [C\PortfolioController::class, 'archive']);
    Route::post('/carteras/{id}/operar', [C\PortfolioController::class, 'trade']);
    Route::post('/carteras/{id}/efectivo', [C\PortfolioController::class, 'cash']);
    Route::post('/carteras/{id}/objetivos', [C\PortfolioController::class, 'targets']);
    Route::get('/carteras/{id}/rebalanceo', [C\PortfolioController::class, 'rebalance']);
    Route::post('/carteras/{id}/rebalanceo', [C\PortfolioController::class, 'executeRebalance']);

    Route::get('/asesor', [C\AdvisorController::class, 'index']);
    Route::post('/asesor', [C\AdvisorController::class, 'submit']);
    Route::get('/asesor/perfil/{id}', [C\AdvisorController::class, 'proposals']);
    Route::post('/asesor/propuesta/{id}/crear', [C\AdvisorController::class, 'createPortfolio']);

    Route::get('/riesgo', [C\RiskController::class, 'index']);

    Route::get('/cripto', [C\CryptoController::class, 'index']);
    Route::post('/cripto/exchanges', [C\CryptoController::class, 'connect']);
    Route::post('/cripto/exchanges/{id}/sincronizar', [C\CryptoController::class, 'sync']);
    Route::post('/cripto/exchanges/{id}/eliminar', [C\CryptoController::class, 'disconnect']);

    Route::get('/noticias', [C\NewsController::class, 'index']);

    Route::get('/diario', [C\JournalController::class, 'index']);
    Route::post('/diario', [C\JournalController::class, 'store']);
    Route::post('/diario/{id}', [C\JournalController::class, 'update']);
    Route::post('/diario/{id}/eliminar', [C\JournalController::class, 'destroy']);

    Route::get('/informes', [C\ReportController::class, 'index']);
    Route::get('/informes/exportar', [C\ReportController::class, 'export']);
    Route::get('/informes/imprimir', [C\ReportController::class, 'print']);

    Route::get('/alertas', [C\AlertController::class, 'index']);
    Route::post('/alertas', [C\AlertController::class, 'store']);
    Route::post('/alertas/{id}/alternar', [C\AlertController::class, 'toggle']);
    Route::post('/alertas/{id}/eliminar', [C\AlertController::class, 'destroy']);
    Route::post('/notificaciones/leidas', [C\AlertController::class, 'markRead']);

    Route::get('/configuracion', [C\SettingsController::class, 'index']);
    Route::post('/configuracion/perfil', [C\SettingsController::class, 'profile']);
    Route::post('/configuracion/preferencias', [C\SettingsController::class, 'preferences']);
    Route::post('/configuracion/contrasena', [C\SettingsController::class, 'password']);

    Route::get('/admin', [C\AdminController::class, 'index'])->middleware('admin');
});

// API JSON interna (sesión + CSRF + límite de solicitudes)
Route::prefix('api')->middleware(['auth', 'throttle'])->group(function () {
    Route::get('/buscar', [A\MarketApiController::class, 'search']);
    Route::get('/cotizaciones', [A\MarketApiController::class, 'quotes']);
    Route::get('/pizarra', [A\MarketApiController::class, 'tape']);
    Route::get('/instrumentos/{id}/historial', [A\MarketApiController::class, 'history']);

    Route::get('/patrimonio', [A\PortfolioApiController::class, 'equity']);
    Route::get('/carteras/{id}/distribucion', [A\PortfolioApiController::class, 'allocation']);
    Route::get('/carteras/{id}/previsualizar', [A\PortfolioApiController::class, 'preview']);

    Route::post('/riesgo/escenario', [A\AnalyticsApiController::class, 'scenario']);
    Route::get('/comparar', [A\AnalyticsApiController::class, 'compare']);

    Route::get('/notificaciones', [A\NotificationApiController::class, 'index']);
    Route::post('/preferencias/tema', [A\NotificationApiController::class, 'theme']);
});
