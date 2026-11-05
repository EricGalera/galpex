<header class="print-head">
  <img src="<?= e(asset_url('img/brand/logo-light.svg')) ?>" alt="GALPEX" width="150" height="29">
  <div>
    <h1>Informe de inversiones</h1>
    <p class="muted"><?= e($user['name']) ?> (<?= e($user['email']) ?>). Período <?= e(short_date($r['from'])) ?> a <?= e(short_date($r['to'])) ?>. Moneda <?= e($r['currency']) ?>. Generado el <?= e(date('d/m/Y H:i')) ?>.</p>
  </div>
  <button class="btn btn-primary no-print" type="button" data-print><?= icon('printer') ?>Guardar como PDF</button>
</header>
<p class="disclaimer">Operaciones con dinero virtual, valuadas a precios reales de mercado.</p>
<?= App\Http\View::partial('reports/_body', ['r' => $r]) ?>
<p class="disclaimer">GALPEX es una plataforma educativa de simulación. Este informe no constituye asesoramiento financiero.</p>
