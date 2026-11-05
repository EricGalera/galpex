<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Services\Exchanges\ExchangeService;
use App\Services\MarketData\MarketDataService;
use DateTimeImmutable;

/**
 * Criptomonedas: cotizaciones y conexión de cuentas de exchanges en modo solo lectura.
 */
final class CryptoController extends Controller
{
    public function index(Request $request): Response
    {
        $market = new MarketDataService();
        $coins = $market->refreshQuotes(Instrument::search(['market' => 'CRYPTO'], 50, 'symbol'));
        $from = (new DateTimeImmutable('-30 days'))->format('Y-m-d');
        foreach ($coins as &$c) {
            $c['spark'] = array_values($market->closes($c, $from, date('Y-m-d')));
            if ($c['price'] !== null) {
                $c['spark'][] = (float) $c['price'];
            }
        }
        unset($c);
        // Ordenadas por volumen operado en las últimas 24 horas (en dólares)
        usort($coins, fn($a, $b) => (float) $b['volume'] * ($b['category_code'] === 'stablecoin' ? 0 : 1) <=> (float) $a['volume'] * ($a['category_code'] === 'stablecoin' ? 0 : 1));
        $exchanges = new ExchangeService();

        return $this->view('crypto/index', [
            'pageTitle' => 'Criptomonedas',
            'pageScripts' => ['markets'],
            'coins' => $coins,
            'connections' => $exchanges->forUser($this->userId()),
            'supported' => $exchanges->supported(),
            'binanceReal' => $market->providers()['binance']->isConfigured(),
        ]);
    }

    public function connect(Request $request): Response
    {
        (new ExchangeService())->connect($this->userId(), $request->all());

        return $this->redirect('/cripto', 'Cuenta conectada en modo solo lectura y saldos importados.');
    }

    public function sync(Request $request): Response
    {
        $r = (new ExchangeService())->sync($this->userId(), (int) $request->param('id'));

        return $this->redirect('/cripto', "Sincronizado: {$r['balances']} saldos y {$r['trades']} operaciones.");
    }

    public function disconnect(Request $request): Response
    {
        (new ExchangeService())->disconnect($this->userId(), (int) $request->param('id'));

        return $this->redirect('/cripto', 'Conexión eliminada junto con sus claves cifradas.');
    }
}
