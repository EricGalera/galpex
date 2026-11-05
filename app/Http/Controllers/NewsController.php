<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Portfolio;
use App\Services\News\NewsService;

/**
 * Noticias por tema y de los activos que el usuario tiene en cartera.
 */
final class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        $news = new NewsService();
        $tabs = array_map(fn($t) => $t[0], NewsService::TOPICS) + ['mine' => 'Mis activos'];
        $tab = (string) $request->query('tema', 'mercados');
        $tab = array_key_exists($tab, $tabs) ? $tab : 'mercados';

        // Activos que el usuario tiene en cartera (se buscan por el nombre de la empresa)
        $held = Portfolio::heldCompanies($this->userId());

        $items = [];
        $error = null;
        if ($tab === 'mine') {
            foreach (array_slice($held, 0, 6) as $h) {
                $r = $news->about((string) $h['name'], (string) $h['symbol']);
                $error ??= $r['error'];
                foreach (array_slice($r['items'], 0, 5) as $n) {
                    $n['related'] = [$h['symbol']];
                    $items[$n['id']] = $n;
                }
            }
            $items = array_values($items);
            usort($items, fn($a, $b) => strcmp($b['datetime'], $a['datetime']));
        } else {
            $r = $news->market($tab);
            $items = $r['items'];
            $error = $r['error'];
        }

        $usSymbols = array_column($held, 'symbol');

        return $this->view('news/index', [
            'pageTitle' => 'Noticias',
            'tab' => $tab,
            'tabs' => $tabs,
            'items' => $items,
            'error' => $error,
            'held' => $held,
            'earningsAvailable' => $news->earningsAvailable(),
            'earnings' => $news->earnings($usSymbols, 30),
        ]);
    }
}
