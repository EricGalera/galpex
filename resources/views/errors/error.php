<div class="error-page">
  <svg class="brand-mark" viewBox="0 0 32 32" fill="none" width="40" height="40" aria-hidden="true"><path d="M24 7H7v18h17v-8" stroke="currentColor" stroke-width="3.6" stroke-linecap="square"/><path d="M15 17h15" stroke="var(--brand)" stroke-width="3.6" stroke-linecap="square"/></svg>
  <p class="error-code mono"><?= (int) $status ?></p>
  <h1><?= e($message) ?></h1>
  <p class="muted"><a class="link" href="<?= e(app_url('/inicio')) ?>">Volver al inicio</a></p>
  <?php if (!empty($debug)): ?><pre class="debug"><?= e($debug) ?></pre><?php endif; ?>
</div>
