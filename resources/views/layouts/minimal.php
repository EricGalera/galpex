<!doctype html>
<html lang="es-AR" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($refreshTo)): ?>  <meta http-equiv="refresh" content="1;url=<?= e(app_url($refreshTo)) ?>">
<?php endif; ?>
  <title><?= e($pageTitle ?? 'GALPEX') ?> · GALPEX</title>
  <link rel="icon" href="<?= e(app_url('/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/pages.css')) ?>">
</head>
<body class="minimal">
  <main class="minimal-main"><?= $content ?></main>
</body>
</html>
