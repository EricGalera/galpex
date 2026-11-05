<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\AuditLog;
use App\Models\Instrument;
use App\Security\Auth;
use App\Security\SecurityPolicy;
use App\Services\Auth\AuthService;
use App\Services\MarketData\MarketDataService;

/**
 * Acceso: inicio y cierre de sesión, registro y recuperación de contraseña.
 */
final class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function home(Request $request): Response
    {
        return Response::redirect(Auth::check() ? '/inicio' : '/login');
    }

    /**
     * Cotizaciones reales para la pantalla de acceso (las que ya están en la base, sin llamar a proveedores).
     *
     * @return array<string, mixed>
     */
    private function board(): array
    {
        $rows = [];
        foreach ([['MERV', 'INDEX'], ['SPX', 'INDEX'], ['USD/ARS', 'FX'], ['USD/ARS-CCL', 'FX'], ['GGAL', 'BYMA'], ['YPFD', 'BYMA'], ['AL30', 'BYMA'], ['BTC', 'CRYPTO']] as [$s, $m]) {
            $i = Instrument::findBySymbol($s, $m);
            if ($i && $i['price'] !== null && MarketDataService::dataStatus($i) !== 'stale') {
                $rows[] = $i;
            }
        }

        return ['boardRows' => $rows];
    }

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['pageTitle' => 'Ingresar'] + $this->board(), 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $this->auth->attempt($request->string('email'), $request->string('password'), $request->ip());
        $intended = session()->get('intended', '/inicio');
        session()->forget('intended');

        // Solo rutas internas: nunca se redirige a otro sitio (redirección abierta).
        return Response::redirect(SecurityPolicy::safeRedirectPath($intended));
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth/register', ['pageTitle' => 'Crear cuenta', 'currencies' => config('app.currencies')] + $this->board(), 'layouts/auth');
    }

    public function register(Request $request): Response
    {
        $id = $this->auth->register($request->all(), $request->ip());
        Auth::login($id);

        return $this->redirect('/carteras/nueva', 'Tu cuenta está lista. Creá tu primera cartera: empieza en cero y vos decidís cuánto dinero virtual depositar.');
    }

    public function logout(Request $request): Response
    {
        AuditLog::record('auth.logout', [], Auth::id(), $request->ip());
        Auth::logout();

        return Response::redirect('/login');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['pageTitle' => 'Recuperar contraseña'] + $this->board(), 'layouts/auth');
    }

    public function forgot(Request $request): Response
    {
        $token = $this->auth->requestReset($request->string('email'), $request->ip());
        session()->flash('success', 'Si el email está registrado, generamos un enlace para restablecer la contraseña (válido por 60 minutos).');
        // XAMPP no envía correos: el enlace se muestra en pantalla SOLO si se pide desde la misma
        // computadora donde corre GALPEX. Desde otra PC de la red nadie puede tomar una cuenta ajena.
        if ($token !== null && config('app.env') === 'local' && SecurityPolicy::isLoopback($request->ip())) {
            session()->flash('reset_link', app_url('/restablecer/' . $token));
        }

        return Response::redirect('/recuperar');
    }

    public function showReset(Request $request): Response
    {
        $token = (string) $request->param('token');
        $valid = $this->auth->findValidReset($token) !== null;

        return $this->view('auth/reset', ['pageTitle' => 'Nueva contraseña', 'token' => $token, 'valid' => $valid] + $this->board(), 'layouts/auth');
    }

    public function reset(Request $request): Response
    {
        $this->auth->resetPassword((string) $request->param('token'), $request->all(), $request->ip());

        return $this->redirect('/login', 'Contraseña actualizada. Ya podés ingresar.');
    }
}
