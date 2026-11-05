<?php
/** @var string $content */
$theme = $authUser['theme'] ?? 'light';
$pageTitle = $pageTitle ?? 'GALPEX';
$pageScripts = $pageScripts ?? [];
?>
<!doctype html>
<html lang="es-AR" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(base_path_url()) ?>">
  <title><?= e($pageTitle) ?> · GALPEX</title>
  <link rel="icon" href="<?= e(app_url('/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/pages.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenido">Ir al contenido</a>
<div class="shell">
  <?= App\Http\View::partial('partials/header') ?>
  <?= App\Http\View::partial('partials/tape') ?>
  <div class="main">
    <main class="content" id="contenido">
      <?= App\Http\View::partial('partials/flash') ?>
      <?= $content ?>
    </main>
    <footer class="site-foot">
      <span>Cotizaciones reales: <?= e(implode(', ', $dataMode['providers'] ?? [])) ?>. Los precios de BYMA tienen 20 minutos de demora.</span>
      <span>GALPEX opera con dinero virtual. No es asesoramiento financiero.</span>
    </footer>
  </div>
</div>
<div class="toast-zone" id="toasts" aria-live="polite"></div>
<script src="<?= e(asset_url('vendor/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset_url('js/core/app.js')) ?>"></script>
<script src="<?= e(asset_url('js/core/charts.js')) ?>"></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= e(asset_url('js/pages/' . $script . '.js')) ?>"></script>
<?php endforeach; ?>
</body>
</html>
