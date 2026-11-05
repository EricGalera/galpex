<?php
use App\Http\View;
$e = $editing;
$val = fn (string $k) => $e[$k] ?? '';
$prefill = null;
foreach ($recentTx as $t) { if ((int) $t['id'] === $prefillTx) { $prefill = $t; } }
$defaultType = $e['entry_type'] ?? ($prefill['type'] ?? 'idea');
?>
<div class="page-head">
  <div>
    <h1>Diario de inversiones</h1>
    <p class="sub">Registrá por qué comprás o vendés, qué esperás y qué riesgo asumís. Después compará con lo que pasó.</p>
  </div>
</div>

<div class="grid g-side-main">
  <form class="panel" method="post" action="<?= e(app_url($e ? '/diario/' . $e['id'] : '/diario')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2><?= $e ? 'Editar entrada' : 'Nueva entrada' ?></h2><?php if ($e): ?><a class="link" href="<?= e(app_url('/diario')) ?>">Cancelar</a><?php endif; ?></div>
    <div class="panel-body">
      <?= View::partial('components/field', ['name' => 'entry_type', 'label' => 'Tipo', 'type' => 'select', 'value' => $defaultType, 'options' => $types]) ?>
      <div class="field">
        <label for="f-transaction_id">Operación vinculada</label>
        <select class="select" id="f-transaction_id" name="transaction_id">
          <option value="">Ninguna</option>
          <?php foreach ($recentTx as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= ((int) ($e['transaction_id'] ?? $prefillTx)) === (int) $t['id'] ? 'selected' : '' ?>>
              <?= e(($t['type'] === 'buy' ? 'Compra ' : 'Venta ') . $t['symbol'] . ', ' . local_date($t['executed_at'], 'd/m/Y') . ', ' . $t['portfolio_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">El activo se completa solo a partir de la operación.</span>
      </div>
      <?= View::partial('components/field', ['name' => 'reason', 'label' => 'Motivo', 'type' => 'textarea', 'value' => $val('reason'), 'attrs' => 'maxlength="2000"']) ?>
      <?= View::partial('components/field', ['name' => 'objective', 'label' => 'Objetivo de la operación', 'value' => $val('objective'), 'attrs' => 'maxlength="255"']) ?>
      <?= View::partial('components/field', ['name' => 'expectation', 'label' => 'Expectativa', 'value' => $val('expectation'), 'attrs' => 'maxlength="255"', 'hint' => 'Por ejemplo: "+15% en 6 meses".']) ?>
      <?= View::partial('components/field', ['name' => 'risk_taken', 'label' => 'Riesgo asumido', 'value' => $val('risk_taken'), 'attrs' => 'maxlength="255"']) ?>
      <?= View::partial('components/field', ['name' => 'outcome', 'label' => 'Resultado final', 'type' => 'textarea', 'value' => $val('outcome'), 'attrs' => 'maxlength="2000"', 'hint' => 'Completalo cuando cierres la posición.']) ?>
      <?= View::partial('components/field', ['name' => 'observations', 'label' => 'Observaciones', 'type' => 'textarea', 'value' => $val('observations'), 'attrs' => 'maxlength="2000"']) ?>
      <?= View::partial('components/field', ['name' => 'rating', 'label' => 'Autoevaluación de la decisión', 'type' => 'select', 'value' => $val('rating'), 'options' => ['' => 'Sin evaluar', '1' => '1, mala decisión', '2' => '2', '3' => '3, aceptable', '4' => '4', '5' => '5, muy buena decisión']]) ?>
    </div>
    <div class="panel-foot"><button class="btn btn-primary btn-block" type="submit"><?= $e ? 'Guardar cambios' : 'Guardar en el diario' ?></button></div>
  </form>

  <section class="panel">
    <div class="panel-head">
      <h2>Entradas</h2>
      <span class="meta"><?= (int) $stats['total'] ?> en total<?= $stats['avg_rating'] ? ', autoevaluación promedio ' . e(num($stats['avg_rating'], 1)) . '/5' : '' ?></span>
    </div>
    <form class="table-tools" method="get" action="<?= e(app_url('/diario')) ?>">
      <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Buscar en el diario" aria-label="Buscar">
      <select class="select" name="tipo" aria-label="Tipo" style="max-width:160px"><option value="">Todos los tipos</option><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm" type="submit">Filtrar</button>
    </form>
    <?php foreach ($entries as $j):
        $current = $j['current_price'] !== null && $j['tx_price'] !== null ? ((float) $j['current_price'] / (float) $j['tx_price'] - 1) * 100 : null; ?>
      <article class="journal-entry">
        <div class="when"><?= e(local_date($j['created_at'], 'd/m/Y')) ?><br><span class="badge badge-neutral"><?= e($types[$j['entry_type']]) ?></span>
          <?php if ($j['rating']): ?><br><span class="rating" aria-label="<?= (int) $j['rating'] ?> de 5"><?= str_repeat('■', (int) $j['rating']) ?><span class="faint"><?= str_repeat('■', 5 - (int) $j['rating']) ?></span></span><?php endif; ?></div>
        <div>
          <div class="row-between">
            <strong><?= $j['symbol'] ? '<a class="link" href="' . e(app_url('/mercados/instrumento/' . $j['instrument_id'])) . '">' . e($j['symbol']) . '</a> ' . e($j['instrument_name']) : 'Nota general' ?></strong>
            <div class="row">
              <a class="btn btn-ghost btn-sm" href="<?= e(app_url('/diario', ['editar' => $j['id']])) ?>"><?= icon('edit', 'icon icon-sm') ?>Editar</a>
              <form method="post" action="<?= e(app_url('/diario/' . $j['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta entrada?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('trash', 'icon icon-sm') ?></button></form>
            </div>
          </div>
          <?php if ($j['tx_price'] !== null): ?>
            <p class="faint" style="margin:2px 0 0">Operación: <?= e(num($j['tx_quantity'], (float) $j['tx_quantity'] == floor((float) $j['tx_quantity']) ? 0 : 6)) ?> a <?= e(money($j['tx_price'], $j['instrument_currency'])) ?> el <?= e(local_date($j['tx_date'], 'd/m/Y')) ?>.
              <?php if ($current !== null): ?>Desde entonces el precio varió <span class="<?= trend_class($current) ?>"><?= e(pct($current, 1)) ?></span>.<?php endif; ?>
              <?php if ($j['tx_realized'] !== null): ?>Resultado realizado: <span class="<?= trend_class($j['tx_realized']) ?>"><?= e(signed_money($j['tx_realized'], $j['tx_currency'])) ?></span>.<?php endif; ?></p>
          <?php endif; ?>
          <dl>
            <?php foreach (['reason' => 'Motivo', 'objective' => 'Objetivo', 'expectation' => 'Expectativa', 'risk_taken' => 'Riesgo asumido', 'outcome' => 'Resultado', 'observations' => 'Observaciones'] as $k => $l): if (!empty($j[$k])): ?>
              <dt><?= e($l) ?></dt><dd><?= nl2br(e($j[$k])) ?></dd>
            <?php endif; endforeach; ?>
          </dl>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$entries): ?><div class="empty"><h3>El diario está vacío</h3><p>Después de cada operación GALPEX te ofrece anotar el motivo. También podés registrar ideas antes de invertir.</p></div><?php endif; ?>
  </section>
</div>
