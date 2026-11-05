<?php /** $label, $value (HTML seguro), $sub (HTML seguro), $lead */ ?>
<div class="kpi <?= !empty($lead) ? 'is-lead' : '' ?>">
  <div class="kpi-label"><?= e($label) ?></div>
  <div class="kpi-value"><?= $value ?></div>
  <?php if (!empty($sub)): ?><div class="kpi-sub"><?= $sub ?></div><?php endif; ?>
</div>
