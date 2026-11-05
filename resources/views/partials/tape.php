<?php use App\Services\MarketData\MarketDataService; ?>
<div class="tape" data-tape aria-label="Cotizaciones de referencia">
  <div class="tape-inner">
  <?php foreach ($tape as $i): $status = MarketDataService::dataStatus($i); ?>
    <a class="tape-cell" href="<?= e(app_url('/mercados/instrumento/' . $i['id'])) ?>" data-tape-id="<?= (int) $i['id'] ?>"
       title="<?= e($i['name'] . ' (' . App\Support\Labels::DATA_STATUS[$status][0] . ')') ?>">
      <span class="tape-sym"><?= e(App\Support\Labels::tapeName($i)) ?></span>
      <span class="tape-px" data-px><?= $i['price'] !== null ? e(num($i['price'], smart_decimals((float) $i['price']))) : '—' ?></span>
      <span class="<?= trend_class($i['change_pct']) ?>" data-chg><?= e(pct($i['change_pct'])) ?></span>
    </a>
  <?php endforeach; ?>
  </div>
</div>
