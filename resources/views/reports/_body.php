<?php
/** Cuerpo del informe, compartido por la pantalla y la versión imprimible. */
use App\Support\Labels;
$cur = $r['currency'];
$s = $r['summary'];
$t = $r['totals'];
?>
<section class="kpis" style="--cols:6">
  <div class="kpi is-lead"><div class="kpi-label">Patrimonio actual</div><div class="kpi-value"><?= money_parts($s['total_value'], $cur) ?></div></div>
  <div class="kpi"><div class="kpi-label">Resultado total</div><div class="kpi-value <?= trend_class($s['total_pnl']) ?>"><?= e(signed_money($s['total_pnl'], $cur)) ?></div><div class="kpi-sub <?= trend_class($s['total_return_pct']) ?>"><?= e(pct($s['total_return_pct'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">Realizado en el período</div><div class="kpi-value <?= trend_class($t['realized']) ?>"><?= e(signed_money($t['realized'], $cur)) ?></div></div>
  <div class="kpi"><div class="kpi-label">No realizado</div><div class="kpi-value <?= trend_class($s['market_value'] - $s['cost_basis']) ?>"><?= e(signed_money($s['market_value'] - $s['cost_basis'], $cur)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Dividendos</div><div class="kpi-value"><?= e(money($t['dividends'], $cur, 2)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Comisiones</div><div class="kpi-value"><?= e(money($t['commissions'], $cur, 2)) ?></div><div class="kpi-sub faint"><?= (int) $t['operations'] ?> operaciones</div></div>
</section>

<div class="grid g-2">
  <section class="panel">
    <div class="panel-head"><h2>Rentabilidad mensual</h2><span class="meta">Por tiempo (TWR)</span></div>
    <div class="panel-body no-print-chart"><div class="chart-box sm"><canvas data-monthly-chart='<?= json_attr(array_reverse(array_map(fn ($m) => ['p' => $m['period'], 'r' => round($m['return'], 4)], $r['periods']))) ?>' role="img" aria-label="Rentabilidad mensual"></canvas></div></div>
    <div class="table-wrap">
      <table class="table table-compact">
        <thead><tr><th>Mes</th><th class="num">Valor inicial</th><th class="num">Aportes netos</th><th class="num">Valor final</th><th class="num">Rentabilidad</th></tr></thead>
        <tbody><?php foreach ($r['periods'] as $m): ?><tr><td><?= e($m['period']) ?></td><td class="num"><?= e(money($m['start'], $cur, 0)) ?></td><td class="num muted"><?= e(signed_money($m['flows'], $cur)) ?></td><td class="num"><?= e(money($m['end'], $cur, 0)) ?></td><td class="num <?= trend_class($m['return']) ?>"><?= e(pct($m['return'])) ?></td></tr><?php endforeach; ?></tbody>
      </table>
    </div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Evolución del capital</h2><span class="meta"><?= e(short_date($r['from'])) ?> a <?= e(short_date($r['to'])) ?></span></div>
    <div class="panel-body"><div class="chart-box"><canvas data-capital-chart='<?= json_attr(array_map(fn ($p) => ['d' => $p['date'], 'v' => $p['value'], 'c' => $p['contributions']], $r['curve'])) ?>' data-currency="<?= e($cur) ?>" role="img" aria-label="Evolución del capital"></canvas><?php if (count($r['curve']) < 2): ?><div class="chart-state">Hace falta más de un día de historia para dibujar la evolución.</div><?php endif; ?></div></div>
    <div class="panel-head"><h2>Distribución patrimonial</h2></div>
    <div class="panel-body"><div class="bars">
      <?php foreach (array_slice($s['allocation']['category'], 0, 10) as $k => $g): ?>
        <div class="bar-row" style="--c:var(--c<?= $k % 8 + 1 ?>)"><span class="ellipsis"><?= e($g['label']) ?></span><span class="bar-track"><span class="bar-fill" style="width:<?= max(0.5, round($g['weight'], 2)) ?>%"></span></span><span class="num"><?= e(pct($g['weight'], 1, false)) ?></span></div>
      <?php endforeach; ?>
    </div></div>
  </section>
</div>

<div class="grid g-main-side">
  <section class="panel">
    <div class="panel-head"><h2>Rentabilidad por activo</h2></div>
    <div class="table-wrap">
      <table class="table table-compact" data-enhance id="by-asset">
        <thead><tr><th data-sort="text">Activo</th><th class="num" data-sort="num">Comprado</th><th class="num" data-sort="num">Valor actual</th><th class="num" data-sort="num">Realizado</th><th class="num" data-sort="num">No realizado</th><th class="num" data-sort="num">Dividendos</th><th class="num" data-sort="num">Total</th><th class="num" data-sort="num">%</th></tr></thead>
        <tbody><?php foreach ($r['by_asset'] as $a): ?>
          <tr><td><span class="sym"><?= e($a['symbol']) ?></span><span class="sym-name"><?= e($a['name']) ?></span></td>
            <td class="num" data-value="<?= e((string) $a['bought']) ?>"><?= e(money($a['bought'], $cur, 0)) ?></td>
            <td class="num" data-value="<?= e((string) $a['market_value']) ?>"><?= e(money($a['market_value'], $cur, 0)) ?></td>
            <td class="num <?= trend_class($a['realized']) ?>" data-value="<?= e((string) $a['realized']) ?>"><?= e(signed_money($a['realized'], $cur)) ?></td>
            <td class="num <?= trend_class($a['unrealized']) ?>" data-value="<?= e((string) $a['unrealized']) ?>"><?= e(signed_money($a['unrealized'], $cur)) ?></td>
            <td class="num" data-value="<?= e((string) $a['dividends']) ?>"><?= e(money($a['dividends'], $cur, 2)) ?></td>
            <td class="num <?= trend_class($a['total']) ?>" data-value="<?= e((string) $a['total']) ?>"><?= e(signed_money($a['total'], $cur)) ?></td>
            <td class="num <?= trend_class($a['return_pct']) ?>" data-value="<?= e((string) $a['return_pct']) ?>"><?= e(pct($a['return_pct'], 1)) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
    </div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Por categoría</h2></div>
    <table class="table table-compact">
      <thead><tr><th>Categoría</th><th class="num">Peso</th><th class="num">No realizado</th></tr></thead>
      <tbody><?php foreach ($r['by_category'] as $c): ?><tr><td><?= e($c['category']) ?></td><td class="num"><?= e(pct($c['weight'], 1, false)) ?></td><td class="num <?= trend_class($c['unrealized']) ?>"><?= e(pct($c['return_pct'], 1)) ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <div class="panel-body"><dl class="kv">
      <dt>Ingresos de efectivo</dt><dd><?= e(money($t['deposits'], $cur, 2)) ?></dd>
      <dt>Retiros</dt><dd><?= e(money($t['withdrawals'], $cur, 2)) ?></dd>
      <dt>Volumen comprado</dt><dd><?= e(money($t['buys'], $cur, 2)) ?></dd>
      <dt>Volumen vendido</dt><dd><?= e(money($t['sells'], $cur, 2)) ?></dd>
    </dl></div>
  </section>
</div>

<section class="panel">
  <div class="panel-head"><h2>Historial completo</h2><input class="input no-print" type="search" placeholder="Filtrar" data-filter-for="history" style="max-width:220px;min-height:30px" aria-label="Filtrar historial"></div>
  <div class="table-wrap">
    <table class="table table-compact" id="history" data-enhance>
      <thead><tr><th data-sort="text">Fecha</th><th data-sort="text">Cartera</th><th>Tipo</th><th data-sort="text">Activo</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Comisión</th><th class="num" data-sort="num">Efecto en efectivo</th><th class="num">Resultado</th></tr></thead>
      <tbody><?php foreach ($r['transactions'] as $tx): ?>
        <tr><td class="nowrap"><?= e(local_date($tx['executed_at'])) ?></td><td><?= e($tx['portfolio_name']) ?></td><td><?= e(Labels::txType($tx['type'])) ?></td><td class="sym"><?= e($tx['symbol'] ?? '') ?></td>
          <td class="num"><?= $tx['quantity'] !== null ? e(num($tx['quantity'], (float) $tx['quantity'] == floor((float) $tx['quantity']) ? 0 : 6)) : '' ?></td>
          <td class="num"><?= $tx['price'] !== null ? e(money($tx['price'], $tx['instrument_currency'])) : '' ?></td>
          <td class="num muted"><?= (float) $tx['commission'] > 0 ? e(money($tx['commission'], $tx['base_currency'], 2)) : '' ?></td>
          <td class="num <?= trend_class($tx['cash_effect']) ?>" data-value="<?= e((string) $tx['cash_effect']) ?>"><?= e(signed_money($tx['cash_effect'], $tx['base_currency'])) ?></td>
          <td class="num <?= trend_class($tx['realized_pnl']) ?>"><?= $tx['realized_pnl'] !== null ? e(signed_money($tx['realized_pnl'], $tx['base_currency'])) : '' ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
    <?php if (!$r['transactions']): ?><p class="empty">No hay movimientos en el período.</p><?php endif; ?>
  </div>
  <div class="panel-foot">Montos de cada fila en la moneda de su cartera.</div>
</section>
