<?php use App\Http\View; $u = $authUser; ?>
<div class="page-head"><div><h1>Configuración</h1><p class="sub">Perfil, preferencias de visualización y seguridad de la cuenta.</p></div></div>
<?php if (!empty($u['must_change_password'])): ?>
  <div class="notice notice-error" role="alert"><?= icon('alert') ?><p><strong>Elegí una contraseña nueva.</strong> Tu cuenta tiene una contraseña inicial generada por el sistema: cambiala abajo para poder usar GALPEX.</p></div>
<?php endif; ?>
<div class="grid g-3">
  <form class="panel" method="post" action="<?= e(app_url('/configuracion/perfil')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2>Perfil</h2></div>
    <div class="panel-body">
      <?= View::partial('components/field', ['name' => 'name', 'label' => 'Nombre', 'value' => $u['name'], 'attrs' => 'required maxlength="100"']) ?>
      <?= View::partial('components/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $u['email'], 'attrs' => 'required']) ?>
      <?= View::partial('components/field', ['name' => 'current_password_profile', 'label' => 'Contraseña actual', 'type' => 'password', 'attrs' => 'autocomplete="current-password"', 'hint' => 'Solo si cambiás el email: con el email se puede recuperar la cuenta.']) ?>
    </div>
    <div class="panel-foot"><button class="btn btn-primary" type="submit">Guardar perfil</button></div>
  </form>

  <form class="panel" method="post" action="<?= e(app_url('/configuracion/preferencias')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2>Preferencias</h2></div>
    <div class="panel-body">
      <?= View::partial('components/field', ['name' => 'base_currency', 'label' => 'Moneda principal', 'type' => 'select', 'value' => $u['base_currency'], 'options' => array_combine($currencies, $currencies), 'hint' => 'Consolida el patrimonio de todas las carteras.']) ?>
      <?= View::partial('components/field', ['name' => 'timezone', 'label' => 'Zona horaria', 'type' => 'select', 'value' => $u['timezone'], 'options' => array_combine($timezones, $timezones)]) ?>
      <?= View::partial('components/field', ['name' => 'theme', 'label' => 'Tema', 'type' => 'select', 'value' => $u['theme'], 'options' => ['dark' => 'Oscuro', 'light' => 'Claro']]) ?>
      <?= View::partial('components/field', ['name' => 'risk_free_rate', 'label' => 'Tasa libre de riesgo anual (%)', 'value' => num((float) $u['risk_free_rate'] * 100, 2), 'numeric' => true, 'hint' => 'Se usa en Sharpe y Sortino.']) ?>
      <?= View::partial('components/field', ['name' => 'default_portfolio_id', 'label' => 'Cartera predeterminada', 'type' => 'select', 'value' => $u['default_portfolio_id'] ?? '', 'options' => ['' => 'Ninguna'] + array_column($portfolios, 'name', 'id')]) ?>
    </div>
    <div class="panel-foot"><button class="btn btn-primary" type="submit">Guardar preferencias</button></div>
  </form>

  <div class="stack">
    <form class="panel" method="post" action="<?= e(app_url('/configuracion/contrasena')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="panel-head"><h2>Contraseña</h2></div>
      <div class="panel-body">
        <?= View::partial('components/field', ['name' => 'current_password', 'label' => 'Contraseña actual', 'type' => 'password', 'attrs' => 'autocomplete="current-password" required']) ?>
        <?= View::partial('components/field', ['name' => 'password', 'label' => 'Contraseña nueva', 'type' => 'password', 'attrs' => 'autocomplete="new-password" required', 'hint' => 'Mínimo 8 caracteres, con letras y números. Al cambiarla se cierran tus sesiones en otras computadoras.']) ?>
        <?= View::partial('components/field', ['name' => 'password_confirmation', 'label' => 'Repetila', 'type' => 'password', 'attrs' => 'autocomplete="new-password" required']) ?>
      </div>
      <div class="panel-foot"><button class="btn btn-primary" type="submit">Cambiar contraseña</button></div>
    </form>
    <section class="panel">
      <div class="panel-head"><h2>Actividad reciente</h2></div>
      <div class="list">
        <?php foreach ($activity as $a): ?>
          <div class="list-item"><span class="mono" style="font-size:var(--fs-sm)"><?= e($a['event']) ?></span><span class="meta"><?= e(local_date($a['created_at'], 'd/m H:i')) ?> · <?= e($a['ip'] ?? '') ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
