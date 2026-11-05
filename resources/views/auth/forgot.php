<?php $resetLink = session()->get('reset_link'); ?>
<h1>Recuperar contraseña</h1>
<p class="sub">Te generamos un enlace para elegir una contraseña nueva.</p>
<?php if ($resetLink): ?>
  <div class="notice notice-demo"><?= icon('link') ?>
    <div><p><strong>Entorno local:</strong> XAMPP no envía correos, así que el enlace se muestra acá (y queda en storage/logs).</p>
    <p><a class="link" href="<?= e($resetLink) ?>">Abrir enlace de restablecimiento</a></p></div>
  </div>
<?php endif; ?>
<form method="post" action="<?= e(app_url('/recuperar')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= App\Http\View::partial('components/field', ['name' => 'email', 'label' => 'Email de tu cuenta', 'type' => 'email', 'attrs' => 'autocomplete="email" required']) ?>
  <button class="btn btn-primary btn-block" type="submit">Generar enlace</button>
</form>
<div class="auth-links"><a class="link" href="<?= e(app_url('/login')) ?>">Volver a ingresar</a></div>
