<?php
use App\Http\View;
use App\Models\Instrument;
use App\Support\Labels;
$cur = $p['base_currency'];
$archived = (int) $p['is_archived'] === 1;
$journalTx = session()->get('journal_tx');
$origins = ['manual' => 'Manual', 'rebalance' => 'Rebalanceo', 'advisor' => 'Asesor', 'import' => 'Importación'];
?>
<div class="page-head">
  <div>
    <p class="sub" style="margin:0 0 2px"><a class="link" href="<?= e(app_url('/carteras')) ?>">Carteras</a></p>
    <h1><?= e($p['name']) ?></h1>
    <p class="sub"><?= e($objectives[$p['objective']]) ?>, en <?= e($cur) ?>, horizonte de <?= (int) $p['horizon_years'] ?> años, riesgo objetivo <?= (int) $p['target_risk'] ?>/10<?= $p['description'] ? '. ' . e($p['description']) : '' ?></p>
  </div>
  <?php if (!$archived): ?>
  <div class="page-head-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('/carteras/' . $p['id'] . '/editar')) ?>"><?= icon('edit') ?>Editar</a>
    <button class="btn" type="button" data-open-cash><?= icon('cash') ?>Ingresar o retirar</button>
    <a class="btn" href="<?= e(app_url('/carteras/' . $p['id'] . '/rebalanceo')) ?>"><?= icon('scale') ?>Rebalancear</a>
    <button class="btn btn-primary" type="button" data-open-trade><?= icon('plus') ?>Operar</button>
  </div>
  <?php endif; ?>
</div>

<?php if ($archived): ?><div class="notice"><?= icon('info') ?><p>Esta cartera está archivada: se conserva su historial pero no admite operaciones.</p></div><?php endif; ?>
<?php if ($journalTx): ?>
  <div class="notice"><?= icon('journal') ?><p>¿Querés dejar registrado por qué hiciste esta operación? <a class="link" href="<?= e(app_url('/diario', ['transaccion' => $journalTx])) ?>">Anotarla en el diario</a></p></div>
<?php endif; ?>

<section class="kpis" style="--cols:6">
  <?= View::partial('components/kpi', ['lead' => true, 'label' => 'Valor total', 'value' => money_parts($v['total_value'], $cur), 'sub' => '<span class="chg ' . trend_class($v['day_change']) . '">' . e(pct($v['day_change_pct'])) . '</span> <span class="faint">hoy</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Invertido (costo)', 'value' => money_parts($v['cost_basis'], $cur)]) ?>
  <?= View::partial('components/kpi', ['label' => 'Efectivo', 'value' => money_parts($v['cash'], $cur)]) ?>
  <?= View::partial('components/kpi', ['label' => 'No realizado', 'value' => '<span class="' . trend_class($v['unrealized_pnl']) . '">' . e(signed_money($v['unrealized_pnl'], $cur)) . '</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Realizado', 'value' => '<span class="' . trend_class($v['realized_pnl']) . '">' . e(signed_money($v['realized_pnl'], $cur)) . '</span>', 'sub' => '<span class="faint">Comisiones: ' . e(money($v['commissions'], $cur, 2)) . '</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Rentabilidad total', 'value' => '<span class="' . trend_class($v['total_return_pct']) . '">' . e(pct($v['total_return_pct'])) . '</span>', 'sub' => '<span class="faint">Aportes netos: ' . e(money($v['net_contributions'], $cur, 0)) . '</span>']) ?>
</section>

<div class="grid g-main-side">
  <section class="panel" data-equity-chart data-endpoint="<?= e(app_url('/api/patrimonio')) ?>" data-portfolio="<?= (int) $p['id'] ?>" data-currency="<?= e($cur) ?>">
    <div class="panel-head">
      <h2>Evolución</h2>
      <div class="seg" role="group" aria-label="Período">
        <?php foreach (['1M', '3M', '6M', '1A', 'MAX'] as $r): ?>
          <button type="button" data-range="<?= $r ?>" aria-pressed="<?= $r === 'MAX' ? 'true' : 'false' ?>"><?= $r === 'MAX' ? 'Máx.' : $r ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="panel-body">
      <div class="chart-legend-line" data-chart-summary></div>
      <div class="chart-box"><canvas role="img" aria-label="Evolución de la cartera"></canvas><div class="chart-state" data-chart-state>Cargando…</div></div>
    </div>
  </section>

  <section class="panel" data-allocation='<?= json_attr(['category' => $v['allocation']['category'], 'asset' => $v['allocation']['asset'], 'sector' => $v['allocation']['sector'], 'country' => $v['allocation']['country'], 'currency' => $v['allocation']['currency']]) ?>' data-currency="<?= e($cur) ?>">
    <div class="panel-head">
      <h2>Distribución</h2>
      <div class="seg" role="group" aria-label="Agrupar por">
        <button type="button" data-dim="category" aria-pressed="true">Tipo</button>
        <button type="button" data-dim="asset" aria-pressed="false">Activo</button>
        <button type="button" data-dim="sector" aria-pressed="false">Sector</button>
        <button type="button" data-dim="country" aria-pressed="false">País</button>
        <button type="button" data-dim="currency" aria-pressed="false">Moneda</button>
      </div>
    </div>
    <div class="panel-body alloc">
      <div class="donut-box"><canvas role="img" aria-label="Distribución de la cartera"></canvas></div>
      <div class="legend" data-legend></div>
    </div>
    <div class="panel-body" style="padding-top:0"><div class="bars" data-alloc-bars></div></div>
    <div class="panel-foot" data-alloc-detail></div>
  </section>
</div>

<section class="panel">
  <div class="panel-head"><h2>Posiciones</h2>
    <input class="input" type="search" placeholder="Filtrar posiciones" data-filter-for="positions" style="max-width:220px;min-height:30px" aria-label="Filtrar posiciones">
  </div>
  <div class="table-wrap">
    <table class="table" id="positions" data-enhance>
      <thead><tr>
        <th data-sort="text">Activo</th><th class="num" data-sort="num">Cantidad</th><th class="num" data-sort="num">Precio promedio</th><th class="num" data-sort="num">Precio actual</th>
        <th class="num" data-sort="num">Valor</th><th class="num" data-sort="num" title="Qué parte del valor total de la cartera representa este activo hoy">Peso</th><th class="num" data-sort="num" title="Qué parte de la cartera querés que represente; se define en Distribución objetivo y se usa para rebalancear">Objetivo</th><th class="num" data-sort="num">Día</th>
        <th class="num" data-sort="num">Resultado</th><th>Dato</th><th><span class="sr-only">Acciones</span></th>
      </tr></thead>
      <tbody>
      <?php foreach ($v['positions'] as $pos): $frac = Instrument::isFractional($pos); ?>
        <tr>
          <td><a href="<?= e(app_url('/mercados/instrumento/' . $pos['id'])) ?>"><span class="sym"><?= e($pos['symbol']) ?></span><span class="sym-name"><?= e($pos['category_name']) ?></span></a></td>
          <td class="num" data-value="<?= e((string) $pos['quantity']) ?>"><?= e(num($pos['quantity'], $frac ? 6 : 0)) ?></td>
          <td class="num" data-value="<?= e((string) $pos['avg_cost']) ?>"><?= e(money($pos['avg_cost'], $pos['currency'])) ?></td>
          <td class="num" data-value="<?= e((string) $pos['price']) ?>"><?= e(money($pos['price'], $pos['currency'])) ?></td>
          <td class="num" data-value="<?= e((string) $pos['market_value']) ?>"><?= e(money($pos['market_value'], $cur, 2)) ?></td>
          <td class="num" data-value="<?= e((string) $pos['weight']) ?>"><?= e(pct($pos['weight'], 1, false)) ?></td>
          <td class="num muted" data-value="<?= e((string) ($targetMap[$pos['id']] ?? '')) ?>"><?= isset($targetMap[$pos['id']]) ? e(pct((float) $targetMap[$pos['id']], 1, false)) : '—' ?></td>
          <td class="num" data-value="<?= e((string) $pos['change_pct']) ?>"><span class="chg <?= trend_class($pos['change_pct']) ?>"><?= e(pct($pos['change_pct'])) ?></span></td>
          <td class="num" data-value="<?= e((string) $pos['unrealized_pct']) ?>"><span class="<?= trend_class($pos['unrealized_pnl']) ?>"><?= e(signed_money($pos['unrealized_pnl'], $cur)) ?><br><small><?= e(pct($pos['unrealized_pct'])) ?></small></span></td>
          <td><?= View::partial('components/data-status', ['instrument' => $pos]) ?></td>
          <td>
            <?php if (!$archived): ?>
            <div class="position-actions">
              <button class="btn btn-sm" type="button" data-open-trade data-side="buy" data-instrument="<?= (int) $pos['id'] ?>" data-label="<?= e($pos['symbol'] . ' · ' . $pos['name']) ?>">Comprar</button>
              <button class="btn btn-sm" type="button" data-open-trade data-side="sell" data-instrument="<?= (int) $pos['id'] ?>" data-label="<?= e($pos['symbol'] . ' · ' . $pos['name']) ?>">Vender</button>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($v['positions'] === []): ?>
      <div class="empty"><h3>Sin posiciones</h3><p>Tenés <?= e(money($v['cash'], $cur, 2)) ?> disponibles para invertir.</p><?php if (!$archived): ?><button class="btn btn-primary" type="button" data-open-trade>Hacer la primera compra</button><?php endif; ?></div>
    <?php endif; ?>
    <p class="empty" data-filter-empty hidden>Ninguna posición coincide con el filtro.</p>
  </div>
</section>

<div class="grid g-2">
  <section class="panel" id="objetivos">
    <div class="panel-head"><h2>Distribución objetivo</h2><a class="link" href="<?= e(app_url('/carteras/' . $p['id'] . '/rebalanceo')) ?>">Ver rebalanceo</a></div>
    <form method="post" action="<?= e(app_url('/carteras/' . $p['id'] . '/objetivos')) ?>" data-targets-form>
      <?= csrf_field() ?>
      <table class="table table-compact">
        <thead><tr><th>Activo</th><th class="num">Actual</th><th class="num">Objetivo %</th></tr></thead>
        <tbody>
        <?php
        $rows = [];
        foreach ($v['positions'] as $pos) { $rows[(int) $pos['id']] = ['symbol' => $pos['symbol'], 'weight' => $pos['weight']]; }
        foreach ($targets as $t) { $rows[(int) $t['instrument_id']] ??= ['symbol' => $t['symbol'], 'weight' => 0]; }
        foreach ($rows as $id => $r): ?>
          <tr>
            <td class="sym"><?= e($r['symbol']) ?></td>
            <td class="num muted"><?= e(pct($r['weight'], 1, false)) ?></td>
            <td class="num"><input class="input input-num weight-input" name="targets[<?= (int) $id ?>]" value="<?= isset($targetMap[$id]) ? e(num((float) $targetMap[$id], 2)) : '' ?>" inputmode="decimal" aria-label="Objetivo para <?= e($r['symbol']) ?>" <?= $archived ? 'disabled' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Total asignado</td><td></td><td class="num" data-targets-total>—</td></tr></tfoot>
      </table>
      <?php if (!$archived): ?>
      <div class="panel-body" data-instrument-picker-inline>
        <label class="label" for="add-target">Agregar un activo al objetivo</label>
        <div class="row" style="position:relative">
          <input class="input" id="add-target" type="search" placeholder="Buscar instrumento" autocomplete="off" style="flex:1">
          <input class="input input-num weight-input" name="new_target_pct" placeholder="%" inputmode="decimal" aria-label="Porcentaje">
          <div class="popover search-results" hidden></div>
        </div>
        <input type="hidden" name="new_instrument_id">
        <p class="hint faint" style="margin-top:6px">Lo que no asignes queda como efectivo objetivo.</p>
      </div>
      <div class="panel-foot row-between"><span class="faint">Se usa para el rebalanceo y las alertas de desvío.</span><button class="btn btn-primary btn-sm" type="submit">Guardar objetivo</button></div>
      <?php endif; ?>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Benchmarks y posiciones cerradas</h2></div>
    <div class="panel-body">
      <p class="muted">Se compara contra: <?= $benchmarks ? e(implode(', ', array_map(fn ($b) => $b['symbol'], $benchmarks))) : 'ninguno' ?>. <a class="link" href="<?= e(app_url('/riesgo', ['cartera' => $p['id']])) ?>">Ver análisis de riesgo y comparación</a>.</p>
    </div>
    <?php if ($closed): ?>
      <table class="table table-compact">
        <thead><tr><th>Activo cerrado</th><th class="num">Resultado realizado</th></tr></thead>
        <tbody><?php foreach ($closed as $c): ?><tr><td class="sym"><?= e($c['symbol']) ?></td><td class="num <?= trend_class($c['realized_pnl']) ?>"><?= e(signed_money($c['realized_pnl'], $cur)) ?></td></tr><?php endforeach; ?></tbody>
      </table>
    <?php else: ?>
      <div class="panel-body"><p class="faint">No hay posiciones cerradas.</p></div>
    <?php endif; ?>
    <?php if ($rejected): ?>
      <div class="panel-head"><h2>Órdenes rechazadas</h2><span class="meta">Últimas 10</span></div>
      <div class="list"><?php foreach ($rejected as $o): ?>
        <div class="list-item"><span><span class="title"><?= $o['side'] === 'buy' ? 'Compra' : 'Venta' ?> <?= e($o['symbol']) ?></span><br><span class="meta"><?= e($o['reject_reason']) ?></span></span><span class="meta"><?= e(local_date($o['created_at'])) ?></span></div>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (!$archived): ?>
      <form class="panel-foot" method="post" action="<?= e(app_url('/carteras/' . $p['id'] . '/archivar')) ?>" data-confirm="¿Archivar la cartera? Se conserva el historial.">
        <?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('trash', 'icon icon-sm') ?>Archivar cartera</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<section class="panel" id="movimientos">
  <div class="panel-head">
    <h2>Historial de movimientos</h2>
    <nav class="seg" aria-label="Tipo de movimiento">
      <?php foreach (['' => 'Todos', 'buy' => 'Compras', 'sell' => 'Ventas', 'deposit' => 'Ingresos', 'withdrawal' => 'Retiros', 'dividend' => 'Dividendos'] as $k => $l): ?>
        <a href="<?= e(app_url('/carteras/' . $p['id'], $k ? ['tipo' => $k] : []) . '#movimientos') ?>" class="<?= $txFilter === $k ? 'is-active' : '' ?>"><?= e($l) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="table-wrap">
    <table class="table table-compact" data-enhance id="tx-table">
      <thead><tr><th data-sort="text">Fecha</th><th>Tipo</th><th data-sort="text">Activo</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num" data-sort="num">Importe</th><th class="num">Comisión</th><th class="num">Efectivo luego</th><th class="num" data-sort="num">Resultado</th><th>Origen</th></tr></thead>
      <tbody>
      <?php foreach ($transactions as $t): ?>
        <tr>
          <td class="nowrap"><?= e(local_date($t['executed_at'])) ?></td>
          <td><span class="badge <?= in_array($t['type'], ['buy', 'withdrawal'], true) ? 'badge-neutral' : 'badge-brand' ?>"><?= e(Labels::txType($t['type'])) ?></span></td>
          <td class="sym"><?= e($t['symbol'] ?? '—') ?></td>
          <td class="num"><?= $t['quantity'] !== null ? e(num($t['quantity'], (float) $t['quantity'] == floor((float) $t['quantity']) ? 0 : 6)) : '' ?></td>
          <td class="num"><?= $t['price'] !== null ? e(money($t['price'], $t['instrument_currency'])) : '' ?></td>
          <td class="num <?= trend_class($t['cash_effect']) ?>" data-value="<?= e((string) $t['cash_effect']) ?>"><?= e(signed_money($t['cash_effect'], $cur)) ?></td>
          <td class="num muted"><?= (float) $t['commission'] > 0 ? e(money($t['commission'], $cur, 2)) : '' ?></td>
          <td class="num muted"><?= e(money($t['cash_after'], $cur, 2)) ?></td>
          <td class="num <?= trend_class($t['realized_pnl']) ?>" data-value="<?= e((string) ($t['realized_pnl'] ?? '')) ?>"><?= $t['realized_pnl'] !== null ? e(signed_money($t['realized_pnl'], $cur)) : '' ?></td>
          <td class="muted"><?= e($origins[$t['origin'] ?? ''] ?? ($t['notes'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($transactions === []): ?><p class="empty">No hay movimientos de este tipo.</p><?php endif; ?>
  </div>
</section>

<?php if (!$archived): ?>
  <?= View::partial('partials/trade-dialog', ['portfolios' => $portfolios, 'portfolio' => $p]) ?>
  <dialog class="dialog" id="cash-dialog" aria-labelledby="cash-title">
    <form method="post" action="<?= e(app_url('/carteras/' . $p['id'] . '/efectivo')) ?>">
      <?= csrf_field() ?>
      <div class="dialog-head"><h2 id="cash-title">Movimiento de efectivo</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Cerrar"><?= icon('x') ?></button></div>
      <div class="dialog-body">
        <div class="ticket-side" role="radiogroup" aria-label="Tipo">
          <label><input type="radio" name="type" value="deposit" checked><span>Ingresar</span></label>
          <label><input type="radio" name="type" value="withdrawal"><span>Retirar</span></label>
        </div>
        <div class="field"><label for="c-amount">Monto en <?= e($cur) ?></label><input class="input input-num" id="c-amount" name="amount" inputmode="decimal" required></div>
        <div class="field"><label for="c-notes">Nota (opcional)</label><input class="input" id="c-notes" name="notes" maxlength="255"></div>
        <p class="faint">Disponible: <?= e(money($v['cash'], $cur, 2)) ?>. Es dinero virtual: no se transfiere nada real.</p>
      </div>
      <div class="dialog-foot"><button class="btn" type="button" data-close>Cancelar</button><button class="btn btn-primary" type="submit">Registrar</button></div>
    </form>
  </dialog>
<?php endif; ?>
