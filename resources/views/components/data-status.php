<?php
/** Insignia de origen y frescura del dato. Espera $instrument. */
use App\Services\MarketData\MarketDataService;
use App\Support\Labels;
$status = MarketDataService::dataStatus($instrument);
[$label, $help] = Labels::DATA_STATUS[$status];
$when = $instrument['quoted_at'] ?? null;
?>
<span class="ds ds-<?= e($status) ?>" title="<?= e($help . ($when ? '. Último dato: ' . local_date($when) : '') . (!empty($instrument['quote_source']) ? '. Fuente: ' . Labels::source($instrument['quote_source']) : '')) ?>"><?= e($label) ?></span>
