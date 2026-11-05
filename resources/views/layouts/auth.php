<!doctype html>
<html lang="es-AR" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle ?? 'Ingresar') ?> · GALPEX</title>
  <link rel="icon" href="<?= e(app_url('/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/pages.css')) ?>">
</head>
<body>
<div class="auth">
  <aside class="auth-aside">
    <a class="brand" href="<?= e(app_url('/login')) ?>">
      <svg class="brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M24 7H7v18h17v-8" stroke="currentColor" stroke-width="3.6" stroke-linecap="square"/><path d="M15 17h15" stroke="#6fb0ff" stroke-width="3.6" stroke-linecap="square"/></svg>
      <span class="brand-word">GALPEX</span>
    </a>
    <div class="auth-copy">
      <h2>Tu bróker de práctica, con el mercado de verdad.</h2>
      <p>Comprá y vendé acciones, CEDEARs, bonos, ONs y cripto con dinero virtual, a los precios reales de BYMA y Nueva York. Medí la rentabilidad y el riesgo de cada decisión.</p>
    </div>
    <?php if (!empty($boardRows)): ?>
      <table class="auth-quotes" aria-label="Cotizaciones de referencia">
        <thead><tr><th>Especie</th><th class="num">Último</th><th class="num">Var.</th></tr></thead>
        <tbody>
        <?php foreach ($boardRows as $r): ?>
          <tr><td><?= e(App\Support\Labels::tapeName($r)) ?></td>
            <td class="num"><?= e(num($r['price'], smart_decimals((float) $r['price']))) ?></td>
            <td class="num <?= trend_class($r['change_pct']) ?>"><?= e(pct($r['change_pct'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <p class="auth-legal">Plataforma educativa con dinero virtual. Precios de BYMA con 20 minutos de demora. Nada de lo que se muestra es una recomendación de inversión.</p>
  </aside>
  <main class="auth-main">
    <div class="auth-card">
      <?= App\Http\View::partial('partials/flash') ?>
      <?= $content ?>
    </div>
  </main>
</div>
</body>
</html>
