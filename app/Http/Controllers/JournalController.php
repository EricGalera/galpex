<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\JournalEntry;
use App\Models\Transaction;

/**
 * Diario de inversiones: motivo, objetivo y resultado de cada decisión.
 */
final class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        $filters = ['type' => (string) $request->query('tipo', ''), 'q' => trim((string) $request->query('q', ''))];
        $entries = JournalEntry::forUser($userId, $filters);

        // Operaciones recientes para vincular una entrada
        $recentTx = Transaction::recentTradesForUser($userId, 40);
        $prefillTx = (int) $request->query('transaccion', 0);
        $editing = $request->query('editar') ? JournalEntry::findForUser((int) $request->query('editar'), $userId) : null;

        // Estadística de autoevaluación
        $stats = JournalEntry::stats($userId);

        return $this->view('journal/index', [
            'pageTitle' => 'Diario de inversiones',
            'entries' => $entries,
            'filters' => $filters,
            'types' => JournalEntry::TYPES,
            'recentTx' => $recentTx,
            'prefillTx' => $prefillTx,
            'editing' => $editing,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = JournalEntry::validated($this->userId(), $request->all());
        JournalEntry::create($this->userId(), $data);

        return $this->redirect('/diario', 'Entrada guardada en el diario.');
    }

    public function update(Request $request): Response
    {
        $entry = JournalEntry::findForUser((int) $request->param('id'), $this->userId());
        $data = JournalEntry::validated($this->userId(), $request->all());
        JournalEntry::updateEntry((int) $entry['id'], $data);

        return $this->redirect('/diario', 'Entrada actualizada.');
    }

    public function destroy(Request $request): Response
    {
        $entry = JournalEntry::findForUser((int) $request->param('id'), $this->userId());
        JournalEntry::deleteEntry((int) $entry['id']);

        return $this->redirect('/diario', 'Entrada eliminada.');
    }
}
