<h1>Ingresar</h1>
<p class="sub">Entrá con tu cuenta para ver tus carteras y operar.</p>
<form method="post" action="<?= e(app_url('/login')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= App\Http\View::partial('components/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'attrs' => 'autocomplete="email" required']) ?>
  <?= App\Http\View::partial('components/field', ['name' => 'password', 'label' => 'Contraseña', 'type' => 'password', 'attrs' => 'autocomplete="current-password" required']) ?>
  <button class="btn btn-primary btn-block" type="submit">Ingresar</button>
</form>
<div class="auth-links">
  <a class="link" href="<?= e(app_url('/recuperar')) ?>">Olvidé mi contraseña</a>
</div>
<div class="auth-alt">
  <p>¿Primera vez en GALPEX?</p>
  <a class="btn btn-block" href="<?= e(app_url('/registro')) ?>">Crear una cuenta</a>
</div>
