<?php use App\Http\View; ?>
<div class="page-head">
  <div>
    <h1>Alertas y notificaciones</h1>
    <p class="sub">Te avisamos cuando un precio, una variación o una cartera cruza el umbral que definas.</p>
  </div>
</div>

<div class="grid g-side-main">
  <form class="panel" method="post" action="<?= e(app_url('/alertas')) ?>" data-alert-form novalidate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2>Nueva alerta</h2></div>
    <div class="panel-body">
      <?= View::partial('components/field', ['name' => 'type', 'label' => 'Condición', 'type' => 'select', 'value' => 'price_above', 'options' => $types]) ?>
      <div class="field" data-for="instrument">
        <label for="al-inst">Instrumento</label>
        <div style="position:relative" data-alert-picker>
          <input class="input" id="al-inst" type="search" autocomplete="off" placeholder="Buscá por símbolo" value="<?= $prefill ? e($prefill['symbol'] . ' · ' . $prefill['name']) : '' ?>">
          <div class="popover search-results" hidden></div>
        </div>
        <input type="hidden" name="instrument_id" value="<?= $prefill ? (int) $prefill['id'] : '' ?>">
        <span class="hint" data-current-price><?= $prefill && $prefill['price'] !== null ? 'Precio actual: ' . e(money($prefill['price'], $prefill['currency'])) : '' ?></span>
        <?php if ($err = field_error('instrument_id')): ?><span class="error"><?= e($err) ?></span><?php endif; ?>
      </div>
      <div class="field" data-for="portfolio" hidden>
        <label for="al-port">Cartera</label>
        <select class="select" id="al-port" name="portfolio_id">
          <?php foreach ($portfolios as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?= View::partial('components/field', ['name' => 'threshold', 'label' => 'Valor', 'numeric' => true, 'attrs' => 'inputmode="decimal" required', 'hint' => 'Precio, porcentaje o puntos porcentuales según la condición.']) ?>
      <?= View::partial('components/field', ['name' => 'note', 'label' => 'Nota (opcional)', 'attrs' => 'maxlength="160"']) ?>
      <label class="check"><input type="checkbox" name="repeat_alert" value="1"> Repetir (como máximo una vez por día)</label>
    </div>
    <div class="panel-foot"><button class="btn btn-primary btn-block" type="submit">Crear alerta</button></div>
  </form>

  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Mis alertas</h2><span class="meta"><?= count(array_filter($alerts, fn ($a) => (int) $a['is_active'] === 1)) ?> activas</span></div>
      <div class="table-wrap">
        <table class="table table-compact">
          <thead><tr><th>Condición</th><th>Sobre</th><th class="num">Valor</th><th class="num">Último valor</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
          <tbody>
          <?php foreach ($alerts as $a): $isPrice = in_array($a['type'], ['price_above', 'price_below'], true); ?>
            <tr>
              <td><?= e($types[$a['type']]) ?><?= $a['note'] ? '<br><small class="faint">' . e($a['note']) . '</small>' : '' ?></td>
              <td class="sym"><?= e($a['symbol'] ?? $a['portfolio_name'] ?? '—') ?></td>
              <td class="num"><?= $isPrice ? e(money($a['threshold'], $a['instrument_currency'] ?? 'USD')) : e(num($a['threshold'], 2)) ?></td>
              <td class="num muted"><?= $isPrice && $a['current_price'] !== null ? e(money($a['current_price'], $a['instrument_currency'])) : ($a['observed_value'] !== null ? e(num($a['observed_value'], 2)) : '—') ?></td>
              <td><?php if ((int) $a['is_active'] === 1): ?><span class="badge badge-up">Activa</span><?php elseif ($a['triggered_at']): ?><span class="badge badge-brand">Disparada <?= e(local_date($a['triggered_at'], 'd/m H:i')) ?></span><?php else: ?><span class="badge badge-neutral">Pausada</span><?php endif; ?></td>
              <td class="num"><div class="position-actions">
                <form method="post" action="<?= e(app_url('/alertas/' . $a['id'] . '/alternar')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $a['is_active'] === 1 ? 'Pausar' : 'Reactivar' ?></button></form>
                <form method="post" action="<?= e(app_url('/alertas/' . $a['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar la alerta?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-danger" type="submit" aria-label="Eliminar"><?= icon('trash', 'icon icon-sm') ?></button></form>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$alerts): ?><div class="empty"><p>Todavía no creaste alertas.</p></div><?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Centro de notificaciones</h2>
        <form method="post" action="<?= e(app_url('/notificaciones/leidas')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Marcar todo como leído</button></form>
      </div>
      <div class="list">
        <?php foreach ($notifications as $n): ?>
          <a class="list-item notif <?= $n['read_at'] ? '' : 'is-unread' ?>" href="<?= e(app_url($n['link'] ?? '/alertas')) ?>">
            <span><span class="title"><?= e($n['title']) ?></span><?= $n['body'] ? '<br><span class="meta">' . e($n['body']) . '</span>' : '' ?></span>
            <span class="meta"><?= e(time_ago($n['created_at'])) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (!$notifications): ?><p class="empty">No hay notificaciones.</p><?php endif; ?>
      </div>
      <div class="panel-foot">Las alertas se revisan al navegar por GALPEX. Para revisarlas aunque no estés conectado, programá <code>php artisan schedule:run</code> cada minuto (ver docs/instalacion.md).</div>
    </section>
  </div>
</div>
