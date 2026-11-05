<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Alert;
use App\Models\Instrument;
use App\Models\Notification;
use App\Models\Portfolio;
use App\Services\Alerts\AlertService;

/**
 * Alertas de precio, variación y cartera, y centro de notificaciones.
 */
final class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        (new AlertService())->evaluateThrottled($userId, 30);
        $prefill = $request->query('instrumento') ? Instrument::find((int) $request->query('instrumento')) : null;

        return $this->view('alerts/index', [
            'pageTitle' => 'Alertas y notificaciones',
            'pageScripts' => ['alerts'],
            'alerts' => Alert::forUser($userId),
            'types' => Alert::TYPES,
            'portfolios' => Portfolio::forUser($userId),
            'notifications' => Notification::forUser($userId, 60),
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request): Response
    {
        $service = new AlertService();
        $service->create($this->userId(), $request->all());
        $fired = $service->evaluateForUser($this->userId());

        return $this->redirect('/alertas', $fired > 0
            ? 'Alerta creada. La condición ya se cumple: revisá el centro de notificaciones.'
            : 'Alerta creada. Se revisa cada vez que navegás y desde la tarea programada.');
    }

    public function toggle(Request $request): Response
    {
        $a = Alert::findForUser((int) $request->param('id'), $this->userId());
        Alert::toggle($a);

        return $this->back((int) $a['is_active'] === 1 ? 'Alerta pausada.' : 'Alerta reactivada.');
    }

    public function destroy(Request $request): Response
    {
        $a = Alert::findForUser((int) $request->param('id'), $this->userId());
        Alert::delete((int) $a['id']);

        return $this->back('Alerta eliminada.');
    }

    public function markRead(Request $request): Response
    {
        Notification::markAllRead($this->userId());

        return $this->back('Notificaciones marcadas como leídas.');
    }
}
