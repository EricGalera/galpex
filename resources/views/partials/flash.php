<?php
$success = session()->get('success');
$error = session()->get('error');
$errors = session()->get('form_errors', []);
?>
<?php if ($success): ?>
  <div class="notice notice-success" role="status"><?= icon('check') ?><p><?= e($success) ?></p></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="notice notice-error" role="alert"><?= icon('alert') ?><p><?= e($error) ?></p></div>
<?php endif; ?>
<?php if (is_array($errors) && count($errors) > 1): ?>
  <div class="notice notice-error" role="alert"><?= icon('alert') ?>
    <div><strong>Revisá estos campos:</strong><p><?= e(implode(' ', $errors)) ?></p></div>
  </div>
<?php endif; ?>
