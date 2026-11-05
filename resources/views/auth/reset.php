<h1>Nueva contraseña</h1>
<?php if (!$valid): ?>
  <p class="sub">Este enlace venció o ya se usó.</p>
  <a class="btn btn-primary btn-block" href="<?= e(app_url('/recuperar')) ?>">Pedir un enlace nuevo</a>
<?php else: ?>
  <p class="sub">Elegí una contraseña que no uses en otros sitios.</p>
  <form method="post" action="<?= e(app_url('/restablecer/' . $token)) ?>" novalidate>
    <?= csrf_field() ?>
    <?= App\Http\View::partial('components/field', ['name' => 'password', 'label' => 'Contraseña nueva', 'type' => 'password', 'hint' => 'Mínimo 8 caracteres, con letras y números.', 'attrs' => 'autocomplete="new-password" required']) ?>
    <?= App\Http\View::partial('components/field', ['name' => 'password_confirmation', 'label' => 'Repetila', 'type' => 'password', 'attrs' => 'autocomplete="new-password" required']) ?>
    <button class="btn btn-primary btn-block" type="submit">Guardar contraseña</button>
  </form>
<?php endif; ?>
