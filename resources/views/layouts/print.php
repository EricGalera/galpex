<!doctype html>
<html lang="es-AR" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Informe') ?> · GALPEX</title>
  <link rel="icon" href="<?= e(app_url('/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/pages.css')) ?>">
</head>
<body class="print-page">
  <main class="content print-content"><?= $content ?></main>
  <script src="<?= e(asset_url('vendor/chart.umd.min.js')) ?>"></script>
  <script src="<?= e(asset_url('js/core/app.js')) ?>"></script>
  <script src="<?= e(asset_url('js/core/charts.js')) ?>"></script>
  <script src="<?= e(asset_url('js/pages/reports.js')) ?>"></script>
</body>
</html>
