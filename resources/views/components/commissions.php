<?php
/** Tabla de comisiones por tipo de instrumento. Espera $commissions (CommissionSchedule::table()). */
?>
<table class="table table-compact commission-table">
  <thead><tr><th>Instrumento</th><th class="num">Comisión</th></tr></thead>
  <tbody>
  <?php foreach ($commissions as $c): ?>
    <tr><td><?= e($c['label']) ?></td><td class="num"><?= e(pct($c['rate'] * 100, 2, false)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
