<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\InvestmentProfile;
use App\Services\Advisor\AdvisorService;
use App\Services\Advisor\RiskProfiler;

/**
 * Asesor inversor: cuestionario, perfil de riesgo, propuestas de cartera y creación de la cartera elegida.
 */
final class AdvisorController extends Controller
{
    public function index(Request $request): Response
    {
        $history = InvestmentProfile::historyForUser($this->userId());

        return $this->view('advisor/index', [
            'pageTitle' => 'Asesor inversor',
            'questions' => RiskProfiler::QUESTIONS,
            'history' => $history,
            'currency' => $this->currency(),
        ]);
    }

    public function submit(Request $request): Response
    {
        $id = (new AdvisorService())->submit($this->userId(), $request->all());

        return $this->redirect('/asesor/perfil/' . $id);
    }

    public function proposals(Request $request): Response
    {
        $data = (new AdvisorService())->load($this->userId(), (int) $request->param('id'));

        return $this->view('advisor/proposals', [
            'pageTitle' => 'Propuestas del asesor',
            'pageScripts' => ['advisor'],
            'profile' => $data['profile'],
            'proposals' => $data['proposals'],
            'questions' => RiskProfiler::QUESTIONS,
        ]);
    }

    public function createPortfolio(Request $request): Response
    {
        $r = (new AdvisorService())->createPortfolio($this->userId(), (int) $request->param('id'), $request->all());
        $msg = 'Cartera creada con ' . $r['executed'] . ' compras virtuales y su distribución objetivo.';
        if ($r['skipped']) {
            $msg .= ' No se pudieron comprar: ' . implode(', ', $r['skipped']) . ' (monto insuficiente para una unidad o sin cotización); ese dinero quedó en efectivo.';
        }

        return $this->redirect('/carteras/' . $r['portfolio_id'], $msg);
    }
}
