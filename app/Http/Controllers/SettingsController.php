<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\AuditLog;
use App\Models\Portfolio;
use App\Models\User;
use App\Security\Auth;
use App\Services\Auth\AuthService;
use App\Support\Validator;

/**
 * Configuración de la cuenta: datos personales, preferencias y contraseña.
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('settings/index', [
            'pageTitle' => 'Configuración',
            'currencies' => config('app.currencies'),
            'timezones' => config('app.timezones'),
            'portfolios' => Portfolio::forUser($this->userId()),
            'activity' => AuditLog::forUser($this->userId(), 12),
        ]);
    }

    public function profile(Request $request): Response
    {
        $data = Validator::make($request->all(), ['name' => 'required|string|min:2|max:100', 'email' => 'required|email|max:190']);
        (new AuthService())->changeEmail($this->userId(), $data['name'], $data['email'], $request->string('current_password_profile'));
        Auth::refresh();

        return $this->redirect('/configuracion', 'Perfil actualizado.');
    }

    public function preferences(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'base_currency' => 'required|in:' . implode(',', (array) config('app.currencies')),
            'timezone' => 'required|in:' . implode(',', (array) config('app.timezones')),
            'theme' => 'required|in:dark,light',
            'risk_free_rate' => 'required|numeric|between:0,100',
            'default_portfolio_id' => 'nullable|integer',
        ]);
        $default = $data['default_portfolio_id'] ? Portfolio::findForUser((int) $data['default_portfolio_id'], $this->userId())['id'] : null;
        User::updatePreferences($this->userId(), [
            'base_currency' => $data['base_currency'],
            'timezone' => $data['timezone'],
            'theme' => $data['theme'],
            'risk_free_rate' => (float) $data['risk_free_rate'] / 100,
            'default_portfolio_id' => $default,
        ]);
        Auth::refresh();

        return $this->redirect('/configuracion', 'Preferencias guardadas.');
    }

    public function password(Request $request): Response
    {
        (new AuthService())->changePassword($this->userId(), $request->all());

        return $this->redirect('/configuracion', 'Contraseña actualizada.');
    }
}
