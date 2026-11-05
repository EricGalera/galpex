<h1>Crear cuenta</h1>
<p class="sub">Operás con dinero virtual: nada de lo que hagas acá mueve dinero real.</p>
<form method="post" action="<?= e(app_url('/registro')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= App\Http\View::partial('components/field', ['name' => 'name', 'label' => 'Nombre y apellido', 'attrs' => 'autocomplete="name" required maxlength="100"']) ?>
  <?= App\Http\View::partial('components/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'attrs' => 'autocomplete="email" required']) ?>
  <?= App\Http\View::partial('components/field', ['name' => 'password', 'label' => 'Contraseña', 'type' => 'password', 'hint' => 'Mínimo 8 caracteres, con letras y números.', 'attrs' => 'autocomplete="new-password" required minlength="8"']) ?>
  <?= App\Http\View::partial('components/field', ['name' => 'password_confirmation', 'label' => 'Repetí la contraseña', 'type' => 'password', 'attrs' => 'autocomplete="new-password" required']) ?>
  <?= App\Http\View::partial('components/field', ['name' => 'base_currency', 'label' => 'Moneda principal', 'type' => 'select', 'value' => 'USD', 'options' => ['USD' => 'Dólar estadounidense (USD)', 'ARS' => 'Peso argentino (ARS)'], 'hint' => 'Se usa para consolidar tu patrimonio. Podés cambiarla después.']) ?>
  <button class="btn btn-primary btn-block" type="submit">Crear cuenta</button>
</form>
<div class="auth-links"><span class="muted">¿Ya tenés cuenta? <a class="link" href="<?= e(app_url('/login')) ?>">Ingresar</a></span></div>
