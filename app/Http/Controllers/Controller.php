<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Response;
use App\Http\View;
use App\Models\Instrument;
use App\Models\Notification;
use App\Models\Portfolio;
use App\Security\Auth;
use App\Services\MarketData\MarketDataService;

/**
 * Controlador base: arma los datos compartidos del layout (usuario, franja de cotizaciones,
 * notificaciones, fuentes de datos) y ofrece atajos para responder.
 */
abstract class Controller
{
    /** Símbolos de la pizarra superior: [símbolo, mercado]. */
    private const TAPE = [
        ['MERV', 'INDEX'], ['SPX', 'INDEX'], ['NDX', 'INDEX'], ['USD/ARS-OF', 'FX'], ['USD/ARS', 'FX'], ['USD/ARS-CCL', 'FX'],
        ['GD30D', 'BYMA'], ['AL30D', 'BYMA'], ['GGAL', 'BYMA'], ['YPFD', 'BYMA'], ['BTC', 'CRYPTO'], ['ETH', 'CRYPTO'],
    ];

    /** @return array<string,mixed> */
    protected function user(): array
    {
        return Auth::user() ?? [];
    }

    protected function userId(): int
    {
        return (int) Auth::id();
    }

    protected function currency(): string
    {
        return (string) ($this->user()['base_currency'] ?? 'USD');
    }

    protected function riskFree(): float
    {
        return (float) ($this->user()['risk_free_rate'] ?? 0.04);
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): Response
    {
        if (Auth::check() && $layout === 'layouts/app') {
            $this->shareLayout();
        }

        return Response::html(View::render($template, $data, $layout));
    }

    protected function shareLayout(): void
    {
        $user = $this->user();
        View::share('authUser', $user);
        View::share('unreadCount', Notification::unreadCount((int) $user['id']));
        View::share('tape', self::tapeInstruments());
        View::share('dataMode', self::dataMode());
        View::share('userPortfolios', Portfolio::forUser((int) $user['id']));
    }

    /** @return list<array<string,mixed>> */
    public static function tapeInstruments(): array
    {
        $out = [];
        foreach (self::TAPE as [$symbol, $market]) {
            $i = Instrument::findBySymbol($symbol, $market);
            if ($i !== null) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /**
     * Fuentes de datos activas (se muestran al pie de cada página).
     *
     * @return array<string,mixed>
     */
    public static function dataMode(): array
    {
        return ['providers' => (new MarketDataService())->activeProviderNames()];
    }

    protected function redirect(string $to, ?string $success = null): Response
    {
        if ($success !== null) {
            session()->flash('success', $success);
        }

        return Response::redirect($to);
    }

    protected function back(?string $success = null): Response
    {
        if ($success !== null) {
            session()->flash('success', $success);
        }

        return Response::back();
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}
