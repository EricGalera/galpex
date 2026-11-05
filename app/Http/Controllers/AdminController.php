<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\AuditLog;
use App\Models\Instrument;
use App\Models\User;
use App\Services\MarketData\ApiUsage;
use App\Services\MarketData\MarketDataService;

/** Panel de administración: estado de proveedores, usuarios y registro de eventos. */
final class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $market = new MarketDataService();
        $configured = [];
        foreach ($market->providers() as $code => $p) {
            $configured[$code] = $p->isConfigured();
        }

        return $this->view('admin/index', [
            'pageTitle' => 'Administración',
            'providers' => ApiUsage::status(),
            'configured' => $configured,
            'users' => User::allWithPortfolioCount(),
            'audit' => AuditLog::recent(60),
            // Panel de seguridad: intentos fallidos y bloqueos de las últimas 24 horas.
            'security' => AuditLog::securitySummary(),
            'topFailedIps' => AuditLog::topFailedIps(5),
            'pendingPasswordChange' => User::countPendingPasswordChange(),
            'quoteStats' => Instrument::quoteStats(),
            'historyStats' => Instrument::historyStats(),
        ]);
    }
}
