<?php $cur = $p['base_currency']; ?>
<div class="page-head">
  <div>
    <p class="sub" style="margin:0 0 2px"><a class="link" href="<?= e(app_url('/carteras/' . $p['id'])) ?>"><?= e($p['name']) ?></a></p>
    <h1>Rebalanceo</h1>
    <p class="sub">Compara la distribución actual con la objetivo y propone las operaciones para volver a ella. Podés ajustar cada cantidad antes de ejecutar.</p>
  </div>
</div>

<?php if ($plan === null): ?>
  <section class="panel"><div class="empty">
    <h3>Primero definí una distribución objetivo</h3>
    <p>Asigná un porcentaje a cada activo en la sección «Distribución objetivo» de la cartera.</p>
    <a class="btn btn-primary" href="<?= e(app_url('/carteras/' . $p['id'] . '#objetivos')) ?>">Definir objetivo</a>
  </div></section>
<?php else: ?>
  <form method="post" action="<?= e(app_url('/carteras/' . $p['id'] . '/rebalanceo')) ?>" data-rebalance-form data-cash="<?= e((string) $plan['cash']) ?>" data-currency="<?= e($cur) ?>" data-confirm="¿Ejecutar las operaciones del rebalanceo?">
    <?= csrf_field() ?>
    <section class="kpis" style="--cols:4">
      <div class="kpi"><div class="kpi-label">Valor de la cartera</div><div class="kpi-value"><?= money_parts($plan['total_value'], $cur) ?></div></div>
      <div class="kpi"><div class="kpi-label">Ventas necesarias</div><div class="kpi-value" data-sum="sells"><?= e(money($plan['sells'], $cur, 2)) ?></div></div>
      <div class="kpi"><div class="kpi-label">Compras necesarias</div><div class="kpi-value" data-sum="buys"><?= e(money($plan['buys'], $cur, 2)) ?></div></div>
      <div class="kpi"><div class="kpi-label">Capital adicional requerido</div><div class="kpi-value" data-sum="required"><?= e(money($plan['capital_required'], $cur, 2)) ?></div><div class="kpi-sub faint">Efectivo actual: <?= e(money($plan['cash'], $cur, 2)) ?></div></div>
    </section>
    <section class="panel">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Activo</th><th class="num">Actual</th><th class="num">Objetivo</th><th>Estado</th><th class="num">Diferencia</th><th class="num">Operación (+ compra / − venta)</th><th class="num">Importe</th><th class="num">Peso final estimado</th></tr></thead>
          <tbody>
          <?php foreach ($plan['rows'] as $r): ?>
            <tr data-row data-rate="<?= e((string) $r['commission_rate']) ?>" data-unit="<?= e((string) $r['unit_value']) ?>" data-current="<?= e((string) $r['current_value']) ?>" data-total="<?= e((string) $plan['total_value']) ?>">
              <td><span class="sym"><?= e($r['symbol']) ?></span><span class="sym-name"><?= e($r['name']) ?></span></td>
              <td class="num"><?= e(pct($r['current_pct'], 1, false)) ?></td>
              <td class="num"><?= e(pct($r['target_pct'], 1, false)) ?></td>
              <td><span class="status-<?= e($r['status']) ?>"><?= ['ok' => 'En objetivo', 'over' => 'Sobreponderado', 'under' => 'Subponderado'][$r['status']] ?></span></td>
              <td class="num <?= trend_class($r['difference']) ?>"><?= e(signed_money($r['difference'], $cur)) ?></td>
              <td class="num"><input class="input input-num weight-input" name="trades[<?= (int) $r['id'] ?>]" value="<?= $r['quantity'] != 0 ? e(num($r['quantity'], $r['fractional'] ? 6 : 0)) : '' ?>" inputmode="decimal" aria-label="Cantidad a operar de <?= e($r['symbol']) ?>" style="width:120px"></td>
              <td class="num" data-trade-value><?= e(signed_money($r['trade_value'], $cur)) ?></td>
              <td class="num" data-final-pct>—</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><td colspan="7">Efectivo estimado después de operar (descontando comisiones)</td><td class="num" data-cash-after><?= e(money($plan['cash_after'], $cur, 2)) ?></td></tr></tfoot>
        </table>
      </div>
      <div class="panel-foot row-between">
        <span class="faint">Las ventas se ejecutan primero. La comisión depende del tipo de instrumento (por ejemplo, 0,5% en acciones). Las cantidades se redondean según el instrumento.</span>
        <button class="btn btn-primary" type="submit" data-loading-text="Ejecutando…">Ejecutar rebalanceo</button>
      </div>
    </section>
  </form>
<?php endif; ?>
