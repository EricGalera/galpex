<?php /* Primera visita o actualización: el navegador muestra esta pantalla mientras /preparar trabaja. */ ?>
<div class="setup">
  <span class="brand"><svg class="brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M24 7H7v18h17v-8" stroke="currentColor" stroke-width="3.6" stroke-linecap="square"/><path d="M15 17h15" stroke="#0b5fbf" stroke-width="3.6" stroke-linecap="square"/></svg><span class="brand-word">GALPEX</span></span>
  <?php if (!empty($upgrading)): ?>
    <h1>Actualizando GALPEX</h1>
    <p class="muted">Se aplican las mejoras de la nueva versión a la base de datos y se actualiza el panel completo de BYMA y Nueva York con cotizaciones reales. Puede tardar hasta un minuto.</p>
  <?php else: ?>
    <h1>Preparando GALPEX</h1>
    <p class="muted">Es la primera vez que se abre en esta computadora. Se están creando la base de datos y el catálogo con todo lo que cotiza en BYMA y Nueva York. Puede tardar hasta un minuto.</p>
  <?php endif; ?>
  <div class="setup-bar" aria-hidden="true"><span></span></div>
  <p class="faint"><small>Si la página no avanza sola, <a class="link" href="<?= e(app_url('/preparar')) ?>">tocá acá</a>.</small></p>
</div>
