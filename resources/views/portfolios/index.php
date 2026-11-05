<div class="page-head">
  <div>
    <h1>Carteras</h1>
    <p class="sub">Cada cartera tiene su propio efectivo virtual, moneda base, objetivo y nivel de riesgo.</p>
  </div>
  <div class="page-head-actions">
    <a class="btn" href="<?= e(app_url('/asesor')) ?>"><?= icon('advisor') ?>Armar con el asesor</a>
    <a class="btn btn-primary" href="<?= e(app_url('/carteras/nueva')) ?>"><?= icon('plus') ?>Nueva cartera</a>
  </div>
</div>

<?php if ($cards === []): ?>
  <section class="panel"><div class="empty">
    <h3>No tenés carteras activas</h3>
    <p>Una cartera arranca con capital virtual en efectivo. Después comprás y vendés instrumentos con cotizaciones del sistema.</p>
    <a class="btn btn-primary" href="<?= e(app_url('/carteras/nueva')) ?>">Crear mi primera cartera</a>
  </div></section>
<?php else: ?>
  <div class="portfolio-cards">
    <?php foreach ($cards as ['p' => $p, 'v' => $v, 'spark' => $spark]): ?>
      <a class="panel portfolio-card" href="<?= e(app_url('/carteras/' . $p['id'])) ?>" style="--pc: <?= e($p['color'] ?? 'var(--brand)') ?>">
        <div class="row-between">
          <h2><?= e($p['name']) ?></h2>
          <span class="badge badge-neutral"><?= e($objectives[$p['objective']]) ?></span>
        </div>
        <div class="row-between" style="align-items:flex-end">
          <div>
            <div class="value"><?= money_parts($v['total_value'], $p['base_currency']) ?></div>
            <span class="chg <?= trend_class($v['total_return_pct']) ?>"><?= e(pct($v['total_return_pct'])) ?></span> <span class="faint">desde el inicio</span>
          </div>
          <?= sparkline($spark, 110, 36) ?>
        </div>
        <dl class="kv">
          <dt>Resultado</dt><dd class="<?= trend_class($v['total_pnl']) ?>"><?= e(signed_money($v['total_pnl'], $p['base_currency'])) ?></dd>
          <dt>Hoy</dt><dd class="<?= trend_class($v['day_change']) ?>"><?= e(pct($v['day_change_pct'])) ?></dd>
          <dt>Efectivo</dt><dd><?= e(money($v['cash'], $p['base_currency'], 2)) ?></dd>
          <dt>Posiciones</dt><dd><?= count($v['positions']) ?></dd>
          <dt>Riesgo objetivo</dt><dd><?= (int) $p['target_risk'] ?>/10, <?= (int) $p['horizon_years'] ?> años</dd>
        </dl>
        
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($archived): ?>
  <section class="panel">
    <div class="panel-head"><h2>Archivadas</h2></div>
    <div class="list">
      <?php foreach ($archived as $a): ?>
        <a class="list-item" href="<?= e(app_url('/carteras/' . $a['id'])) ?>"><span class="title"><?= e($a['name']) ?></span><span class="meta"><?= e(short_date($a['updated_at'])) ?></span></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
