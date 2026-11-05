<?php /** @var string $reason off|denied|other  @var string $detail */ ?>
<div class="setup">
  <img src="<?= e(asset_url('img/brand/logo.svg')) ?>" alt="GALPEX" width="168" height="32">
  <?php if ($reason === 'off'): ?>
    <h1>Falta encender MySQL</h1>
    <p class="muted">GALPEX guarda los datos en MySQL y no lo encontró funcionando.</p>
    <ol class="setup-steps">
      <li>Abrí el <strong>XAMPP Control Panel</strong>.</li>
      <li>En la fila <strong>MySQL</strong> tocá <strong>Start</strong> y esperá a que el nombre se ponga verde.</li>
      <li>Volvé acá y tocá <strong>Reintentar</strong>.</li>
    </ol>
  <?php elseif ($reason === 'denied'): ?>
    <h1>MySQL no aceptó el usuario</h1>
    <p class="muted">GALPEX se conecta como <code>root</code> sin contraseña, que es lo que trae XAMPP. Si a tu MySQL le pusiste contraseña, abrí el archivo <code>.env</code> de la carpeta del proyecto, escribila en <code>DB_PASSWORD=</code> y tocá Reintentar.</p>
  <?php else: ?>
    <h1>No se pudo preparar la base de datos</h1>
    <p class="muted">Ocurrió un problema inesperado al conectarse con MySQL. El detalle quedó guardado en <code>storage/logs</code>.</p>
  <?php endif; ?>
  <?php if ($detail !== ''): ?><p class="faint"><small>Mensaje de MySQL: <?= e($detail) ?></small></p><?php endif; ?>
  <p><a class="btn btn-primary" href="<?= e(app_url($retry)) ?>">Reintentar</a></p>
</div>
