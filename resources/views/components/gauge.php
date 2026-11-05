<?php
/** Medidor semicircular de riesgo. Variables: $score (1–10|null), $label */
$segments = ['var(--up)', 'color-mix(in srgb, var(--up) 50%, var(--brand))', 'var(--brand)', 'color-mix(in srgb, var(--brand) 50%, var(--down))', 'var(--down)'];
$cx = 100; $cy = 100; $r = 80;
$arc = function (float $a0, float $a1) use ($cx, $cy, $r): string {
    $p = fn ($a) => [round($cx + $r * cos(M_PI * (1 - $a)), 2), round($cy - $r * sin(M_PI * (1 - $a)), 2)];
    [$x0, $y0] = $p($a0);
    [$x1, $y1] = $p($a1);
    return "M$x0 $y0 A$r $r 0 0 1 $x1 $y1";
};
$angle = $score !== null ? max(0, min(1, ($score - 1) / 9)) : null;
?>
<div class="gauge" role="img" aria-label="Índice de riesgo <?= $score !== null ? e(num($score, 1)) . ' de 10, ' . e($label) : 'sin datos' ?>">
  <svg viewBox="0 0 200 108">
    <?php foreach ($segments as $k => $color): ?>
      <path d="<?= $arc($k / 5 + 0.006, ($k + 1) / 5 - 0.006) ?>" stroke="<?= $color ?>" stroke-width="14" fill="none"/>
    <?php endforeach; ?>
    <?php if ($angle !== null):
        $nx = round($cx + 66 * cos(M_PI * (1 - $angle)), 2);
        $ny = round($cy - 66 * sin(M_PI * (1 - $angle)), 2); ?>
      <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= $nx ?>" y2="<?= $ny ?>" stroke="var(--text)" stroke-width="3" stroke-linecap="round"/>
      <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="5" fill="var(--text)"/>
    <?php endif; ?>
  </svg>
  <div class="gauge-value">
    <strong><?= $score !== null ? e(num($score, 1)) : '—' ?><small class="faint"> /10</small></strong>
    <span><?= e($label ?? 'Sin datos suficientes') ?></span>
  </div>
</div>
