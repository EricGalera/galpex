<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Models\Portfolio;
use App\Services\Reports\ReportService;
use App\Support\Validator;

/**
 * Informes de rentabilidad y movimientos, con exportación a CSV y versión imprimible (PDF).
 */
final class ReportController extends Controller
{
    /** @return array{0:?int,1:?string,2:?string} */
    private function filters(Request $request): array
    {
        $portfolioId = $request->query('cartera') ? (int) $request->query('cartera') : null;
        if ($portfolioId !== null) {
            Portfolio::findForUser($portfolioId, $this->userId());
        }
        $from = (string) $request->query('desde', '');
        $to = (string) $request->query('hasta', '');

        return [$portfolioId, Validator::isDate($from) ? $from : null, Validator::isDate($to) ? $to : null];
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Request $request): array
    {
        [$pid, $from, $to] = $this->filters($request);
        $currency = $pid ? (string) Portfolio::findForUser($pid, $this->userId())['base_currency'] : $this->currency();

        return (new ReportService())->build($this->userId(), $currency, $pid, $from, $to) + ['portfolio_id' => $pid];
    }

    public function index(Request $request): Response
    {
        return $this->view('reports/index', [
            'pageTitle' => 'Informes',
            'pageScripts' => ['reports'],
            'r' => $this->build($request),
            'portfolios' => Portfolio::forUser($this->userId(), true),
            'query' => array_filter(['cartera' => $request->query('cartera'), 'desde' => $request->query('desde'), 'hasta' => $request->query('hasta')]),
        ]);
    }

    /** Exportación CSV: movimientos, rentabilidad por activo o mensual. */
    public function export(Request $request): Response
    {
        $r = $this->build($request);
        $what = (string) $request->query('tipo', 'movimientos');
        $service = new ReportService();
        $cur = $r['currency'];
        $stamp = date('Ymd');

        if ($what === 'activos') {
            $rows = array_map(fn($a) => [$a['symbol'], $a['name'], num($a['bought']), num($a['market_value']), num($a['realized']), num($a['unrealized']), num($a['dividends']), num($a['commissions']), num($a['total']), $a['return_pct'] !== null ? num($a['return_pct']) : ''], $r['by_asset']);

            return Response::csv("galpex-rentabilidad-por-activo-$stamp.csv", ['Símbolo', 'Nombre', "Comprado ($cur)", "Valor actual ($cur)", 'Resultado realizado', 'Resultado no realizado', 'Dividendos', 'Comisiones', 'Resultado total', 'Rentabilidad %'], $rows);
        }
        if ($what === 'mensual') {
            $rows = array_map(fn($m) => [$m['period'], num($m['start']), num($m['flows']), num($m['end']), num($m['return'])], $r['periods']);

            return Response::csv("galpex-rentabilidad-mensual-$stamp.csv", ['Mes', "Valor inicial ($cur)", 'Aportes netos', 'Valor final', 'Rentabilidad TWR %'], $rows);
        }
        [$header, $rows] = $service->transactionsCsv($r['transactions']);

        return Response::csv("galpex-movimientos-$stamp.csv", $header, $rows);
    }

    /** Vista de impresión: desde el navegador se guarda como PDF. */
    public function print(Request $request): Response
    {
        $this->shareLayout();

        return Response::html(View::render('reports/print', [
            'pageTitle' => 'Informe GALPEX',
            'r' => $this->build($request),
            'user' => $this->user(),
        ], 'layouts/print'));
    }
}
