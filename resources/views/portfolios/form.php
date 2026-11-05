<?php
use App\Http\View;
$editing = $portfolio !== null;
$val = fn (string $k, $default = '') => $portfolio[$k] ?? $default;
?>
<div class="page-head">
  <div>
    <h1><?= $editing ? 'Editar cartera' : 'Nueva cartera' ?></h1>
    <p class="sub"><?= $editing ? 'La moneda base y el capital inicial no se pueden cambiar una vez creada.' : 'Definí cómo se va a comportar: con cuánto empezás, en qué moneda y qué buscás.' ?></p>
  </div>
</div>
<form class="panel" method="post" action="<?= e(app_url($editing ? '/carteras/' . $portfolio['id'] : '/carteras')) ?>" novalidate style="max-width:860px">
  <?= csrf_field() ?>
  <div class="panel-body form-grid">
    <?= View::partial('components/field', ['name' => 'name', 'label' => 'Nombre', 'value' => $val('name'), 'attrs' => 'required maxlength="80"', 'class' => 'full']) ?>
    <?= View::partial('components/field', ['name' => 'description', 'label' => 'Descripción', 'value' => $val('description'), 'attrs' => 'maxlength="255"', 'class' => 'full', 'hint' => 'Opcional. Por ejemplo: "Ahorro para un auto en 3 años".']) ?>
    <?php if (!$editing): ?>
      <?= View::partial('components/field', ['name' => 'initial_capital', 'label' => 'Capital inicial (virtual)', 'value' => '10000', 'numeric' => true, 'attrs' => 'inputmode="decimal" required']) ?>
      <?= View::partial('components/field', ['name' => 'base_currency', 'label' => 'Moneda base', 'type' => 'select', 'value' => $authUser['base_currency'] ?? 'USD', 'options' => array_combine($currencies, array_map(fn ($c) => $c === 'USD' ? 'Dólar estadounidense (USD)' : 'Peso argentino (ARS)', $currencies)), 'hint' => 'Todo se valúa en esta moneda; los activos en otra moneda se convierten.']) ?>
    <?php endif; ?>
    <?= View::partial('components/field', ['name' => 'objective', 'label' => 'Objetivo', 'type' => 'select', 'value' => $val('objective', 'balanced'), 'options' => $objectives]) ?>
    <?= View::partial('components/field', ['name' => 'horizon_years', 'label' => 'Horizonte de inversión (años)', 'value' => $val('horizon_years', 5), 'numeric' => true, 'attrs' => 'inputmode="numeric" required']) ?>
    <?= View::partial('components/field', ['name' => 'target_risk', 'label' => 'Nivel de riesgo objetivo (1 a 10)', 'value' => $val('target_risk', 5), 'numeric' => true, 'attrs' => 'inputmode="numeric" required', 'hint' => '1 = muy conservador, 10 = muy agresivo. Se compara con el riesgo medido.']) ?>
    <div class="field full">
      <span class="label">Comisiones del bróker</span>
      <?= View::partial('components/commissions', ['commissions' => $commissions]) ?>
      <span class="hint">Las fija GALPEX según el tipo de instrumento y se cobran sobre el importe de cada compra y cada venta.</span>
    </div>
    <?php if ($editing): ?>
      <fieldset class="field full" style="border:0;padding:0;margin:0 0 var(--s4)">
        <legend class="label" style="margin-bottom:6px">Benchmarks para comparar</legend>
        <div class="checks">
          <?php foreach ($benchmarks as $b): ?>
            <label class="check"><input type="checkbox" name="benchmarks[]" value="<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $selectedBenchmarks, true) ? 'checked' : '' ?>><?= e($b['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endif; ?>
  </div>
  <div class="panel-foot row-between">
    <a class="btn btn-ghost" href="<?= e(app_url($editing ? '/carteras/' . $portfolio['id'] : '/carteras')) ?>">Cancelar</a>
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Crear cartera' ?></button>
  </div>
</form>
