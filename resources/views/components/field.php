<?php
/**
 * Campo de formulario con etiqueta, error y valor anterior.
 * Variables: $name, $label, $type='text', $value='', $hint=null, $attrs='', $options=[] (para select)
 */
$type = $type ?? 'text';
$id = 'f-' . $name;
$err = field_error($name);
$val = old($name, $value ?? '');
?>
<div class="field <?= e($class ?? '') ?>">
  <label for="<?= e($id) ?>"><?= e($label) ?></label>
  <?php if ($type === 'select'): ?>
    <select class="select" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $attrs ?? '' ?>>
      <?php foreach ($options as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= (string) $k === (string) $val ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  <?php elseif ($type === 'textarea'): ?>
    <textarea class="textarea" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $attrs ?? '' ?>><?= e($val) ?></textarea>
  <?php else: ?>
    <input class="input <?= in_array($type, ['number'], true) || !empty($numeric) ? 'input-num' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
           type="<?= e($type) ?>" value="<?= $type === 'password' ? '' : e($val) ?>" <?= $err ? 'aria-invalid="true"' : '' ?> <?= $attrs ?? '' ?>>
  <?php endif; ?>
  <?php if (!empty($hint)): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
  <?php if ($err): ?><span class="error"><?= e($err) ?></span><?php endif; ?>
</div>
