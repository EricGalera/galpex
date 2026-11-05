<?php use App\Http\View; ?>
<div class="page-head">
  <div>
    <h1>Criptomonedas</h1>
    <p class="sub">Cotizaciones de cripto y stablecoins<?= $binanceReal ? ' desde los datos públicos de Binance (no requieren clave)' : '' ?>, y conexión de cuentas de exchanges en modo solo lectura.</p>
  </div>
  <a class="btn" href="<?= e(app_url('/mercados', ['panel' => 'cripto'])) ?>">Ver en Mercados</a>
</div>

<section class="panel">
  <div class="table-wrap">
    <table class="table" id="crypto-table" data-enhance data-quotes-table>
      <thead><tr><th data-sort="text">Activo</th><th>30 días</th><th class="num" data-sort="num">Precio (USD)</th><th class="num" data-sort="num">Var. 24 h</th><th class="num" data-sort="num">Máx. / mín. 24 h</th><th class="num" data-sort="num">Volumen 24 h</th><th>Dato</th></tr></thead>
      <tbody>
      <?php foreach ($coins as $c): ?>
        <tr class="is-link" data-href="<?= e(app_url('/mercados/instrumento/' . $c['id'])) ?>" data-id="<?= (int) $c['id'] ?>">
          <td><span class="sym"><?= e($c['symbol']) ?></span><span class="sym-name"><?= e($c['name']) ?> · <?= e($c['category_name']) ?></span></td>
          <td><?= sparkline($c['spark']) ?></td>
          <td class="num" data-value="<?= e((string) $c['price']) ?>" data-px><?= e(money($c['price'], 'USD')) ?></td>
          <td class="num" data-value="<?= e((string) $c['change_pct']) ?>"><span class="chg <?= trend_class($c['change_pct']) ?>" data-chg><?= e(pct($c['change_pct'])) ?></span></td>
          <td class="num muted"><?= e(num($c['day_high'], smart_decimals((float) $c['price']))) ?><br><?= e(num($c['day_low'], smart_decimals((float) $c['price']))) ?></td>
          <td class="num muted" data-value="<?= e((string) $c['volume']) ?>"><?= e(compact_number($c['volume'])) ?></td>
          <td data-ds><?= View::partial('components/data-status', ['instrument' => $c]) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel-foot">Para operar cripto con dinero virtual, usá cualquier cartera desde la ficha del activo o el botón Operar de la cartera.</div>
</section>

<div class="grid g-main-side">
  <section class="panel">
    <div class="panel-head"><h2>Exchanges conectados</h2><span class="meta">Solo lectura, sin operatoria real</span></div>
    <?php foreach ($connections as $c): ?>
      <div class="panel-body" style="border-bottom:1px solid var(--line)">
        <div class="row-between">
          <div><strong><?= e($c['label']) ?></strong> <span class="badge badge-neutral"><?= e(strtoupper($c['exchange'])) ?></span>
            <span class="badge <?= $c['status'] === 'active' ? 'badge-up' : 'badge-down' ?>"><?= e(['active' => 'Activa', 'error' => 'Con error', 'pending' => 'Pendiente'][$c['status']]) ?></span>
            <?php if ((int) $c['read_only_verified'] === 1): ?><span class="badge badge-brand">Clave de solo lectura verificada</span><?php endif; ?>
            <br><small class="faint">Clave <?= e($c['key_hint']) ?>. Última sincronización: <?= e(local_date($c['last_sync_at'])) ?>. <?= e($c['status_message'] ?? '') ?></small></div>
          <div class="row">
            <form method="post" action="<?= e(app_url('/cripto/exchanges/' . $c['id'] . '/sincronizar')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('refresh', 'icon icon-sm') ?>Sincronizar</button></form>
            <form method="post" action="<?= e(app_url('/cripto/exchanges/' . $c['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar la conexión y sus claves cifradas?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-danger" type="submit">Eliminar</button></form>
          </div>
        </div>
        <?php if ($c['balances']): ?>
          <table class="table table-compact" style="margin-top:var(--s3)"><thead><tr><th>Activo</th><th class="num">Disponible</th><th class="num">Bloqueado</th></tr></thead>
            <tbody><?php foreach ($c['balances'] as $b): ?><tr><td class="sym"><?= e($b['asset']) ?></td><td class="num"><?= e(num($b['free_amount'], 8)) ?></td><td class="num muted"><?= e(num($b['locked_amount'], 8)) ?></td></tr><?php endforeach; ?></tbody></table>
          <p class="faint" style="margin-top:6px"><?= (int) $c['trades'] ?> operaciones importadas al historial.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$connections): ?><div class="empty"><p>No conectaste ningún exchange. Es opcional: sirve para ver tus saldos reales junto a tus carteras virtuales.</p></div><?php endif; ?>
  </section>

  <form class="panel" method="post" action="<?= e(app_url('/cripto/exchanges')) ?>" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2>Conectar un exchange</h2></div>
    <div class="panel-body">
      <?= View::partial('components/field', ['name' => 'exchange', 'label' => 'Exchange', 'type' => 'select', 'value' => 'binance', 'options' => ['binance' => 'Binance', 'okx' => 'OKX (próximamente)', 'bybit' => 'Bybit (próximamente)']]) ?>
      <?= View::partial('components/field', ['name' => 'label', 'label' => 'Nombre para identificarla', 'value' => 'Mi cuenta de Binance', 'attrs' => 'maxlength="60" required']) ?>
      <?= View::partial('components/field', ['name' => 'api_key', 'label' => 'API key', 'attrs' => 'required spellcheck="false" autocomplete="off"']) ?>
      <?= View::partial('components/field', ['name' => 'api_secret', 'label' => 'API secret', 'type' => 'password', 'attrs' => 'required autocomplete="new-password"']) ?>
      <ul class="disclaimer" style="padding-left:18px">
        <li>Creá una clave <strong>solo de lectura</strong>. Si tiene permiso de retiro, GALPEX la rechaza.</li>
        <li>Las claves se guardan cifradas en el servidor (AES-256-GCM + APP_KEY) y nunca se envían al navegador.</li>
        <li>La operatoria real está deshabilitada: solo se importan saldos e historial.</li>
      </ul>
    </div>
    <div class="panel-foot"><button class="btn btn-primary btn-block" type="submit" data-loading-text="Verificando permisos…">Verificar y conectar</button></div>
  </form>
</div>
