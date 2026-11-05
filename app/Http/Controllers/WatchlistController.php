<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Watchlist;

/**
 * Listas de seguimiento: crear, agregar o quitar especies y eliminar listas.
 */
final class WatchlistController extends Controller
{
    public function store(Request $request): Response
    {
        $id = Watchlist::create($this->userId(), (string) $request->input('name', ''));

        return $this->redirect('/mercados?lista=' . $id, 'Lista creada.');
    }

    public function toggle(Request $request): Response
    {
        $list = Watchlist::findForUser((int) $request->param('id'), $this->userId());
        $added = Watchlist::toggle((int) $list['id'], (int) $request->param('instrument'));
        $message = $added ? 'Agregado a «' . $list['name'] . '».' : 'Quitado de «' . $list['name'] . '».';
        if ($request->wantsJson()) {
            return $this->json(['added' => $added, 'message' => $message]);
        }

        return $this->back($message);
    }

    public function destroy(Request $request): Response
    {
        $list = Watchlist::findForUser((int) $request->param('id'), $this->userId());
        Watchlist::delete((int) $list['id']);

        return $this->redirect('/mercados', 'Lista eliminada.');
    }
}
