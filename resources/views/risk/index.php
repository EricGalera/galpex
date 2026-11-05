<?php
use App\Http\View;
$cur = $v['currency'];
$m = $risk['metrics'];
$heat = function (?float $c): string {
    if ($c === null) { return 'background:var(--bg-sunken)'; }
    $a = min(1, abs($c));
    $color = $c >= 0 ? 'var(--down)' : 'var(--info)';
    return 'background:color-mix(in srgb, ' . $color . ' ' . round($a * 70) . '%, var(--bg-sunken))';
};
$barList = function (array $groups): string {
    $html = '<div class="bars">';
    $k = 0;
    foreach (array_slice($groups, 0, 8) as $g) {
        $k++;
        $html .= '<div class="bar-row" style="--c:var(--c' . (($k - 1) % 8 + 1) . ')"><span class="ellipsis">' . e($g['label']) . '</span><span class="bar-track"><span class="bar-fill" style="width:' . max(0.5, round($g['weight'], 2)) . '%"></span></span><span class="num">' . e(pct($g['weight'], 1, false)) . '</span></div>';
    }
    return $html . '</div>';
};
?>
<?php
$benchInfo = [
    'SPX' => 'el índice de las 500 empresas más grandes de EE. UU. Es la referencia de "el mercado" en dólares.',
    'NDX' => 'el índice de las 100 mayores empresas no financieras del Nasdaq (sobre todo tecnológicas).',
    'DJI' => 'el índice de 30 grandes empresas industriales de EE. UU.',
    'MERV' => 'el índice de las acciones líderes de la bolsa argentina (MSCI Merval), en pesos.',
    'URTH' => 'un fondo que replica las acciones de los países desarrollados (índice MSCI World).',
];
$benchName = fn (array $b) => ['SPX' => 'S&P 500', 'NDX' => 'Nasdaq 100', 'DJI' => 'Dow Jones', 'MERV' => 'MSCI Merval', 'URTH' => 'MSCI World'][$b['symbol']] ?? $b['name'];
$bName = $bench ? $benchName($bench) : 'el índice';
$top = array_slice($v['positions'], 0, 8);
?>
<div class="page-head">
  <div>
    <h1>Análisis de riesgo</h1>
    <p class="sub">Cuánto se mueve tu cartera, cuánto puede caer y qué tanto depende del mercado. Se calcula con los precios reales de los últimos meses de los activos que tenés hoy.</p>
  </div>
  <form class="page-head-actions" method="get" action="<?= e(app_url('/riesgo')) ?>" data-autosubmit>
    <label class="sr-only" for="r-cartera">Cartera a analizar</label>
    <select class="select" id="r-cartera" name="cartera" style="width:auto">
      <option value="">Todas mis carteras (<?= e($cur) ?>)</option>
      <?php foreach ($portfolios as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $selected === (int) $p['id'] ? 'selected' : '' ?>>Cartera: <?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="r-bench">Índice de comparación</label>
    <select class="select" id="r-bench" name="benchmark" style="width:auto">
      <?php foreach ($benchmarks as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === (int) ($bench['id'] ?? 0) ? 'selected' : '' ?>>Comparar con <?= e($benchName($b)) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="r-dias">Período</label>
    <select class="select" id="r-dias" name="dias" style="width:auto">
      <?php foreach ([90 => 'Últimos 3 meses', 180 => 'Últimos 6 meses', 365 => 'Último año', 730 => 'Últimos 2 años'] as $d => $l): ?><option value="<?= $d ?>" <?= $lookback === $d ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($v['positions'] === []): ?>
  <section class="panel"><div class="empty"><h3>No hay nada para analizar todavía</h3><p>El análisis mide el riesgo de los activos que tenés en cartera. Cuando compres tu primera especie, vas a ver acá cuánto se mueve y cómo se compara con el mercado.</p><a class="btn btn-primary" href="<?= e(app_url($portfolios ? '/mercados' : '/carteras/nueva')) ?>"><?= $portfolios ? 'Ir a Mercados' : 'Crear una cartera' ?></a></div></section>
<?php else: ?>

<section class="explain" aria-label="Qué se está analizando">
  <div>
    <h2>Qué se analiza</h2>
    <p><?= $selected ? 'La cartera elegida, valuada' : 'Todas tus carteras juntas, valuadas' ?> en <?= e(money($v['total_value'], $cur, 0)) ?>, con los pesos que tiene hoy:</p>
    <div class="holdings">
      <?php foreach ($top as $p): ?><span class="holding"><strong><?= e($p['symbol']) ?></strong><span class="muted"><?= e(pct($p['weight'], 1, false)) ?></span></span><?php endforeach; ?>
      <?php if (count($v['positions']) > count($top)): ?><span class="holding muted">y <?= count($v['positions']) - count($top) ?> más</span><?php endif; ?>
      <?php if ($v['cash'] > 0): ?><span class="holding"><strong>Efectivo</strong><span class="muted"><?= e(pct($v['total_value'] > 0 ? $v['cash'] / $v['total_value'] * 100 : 0, 1, false)) ?></span></span><?php endif; ?>
    </div>
  </div>
  <div>
    <h2>Contra qué se compara</h2>
    <p>Contra el <strong><?= e($bName) ?></strong>: <?= e($benchInfo[$bench['symbol'] ?? ''] ?? 'un índice de referencia.') ?></p>
    <p>No es una acción ni una inversión tuya: es la vara para saber si tu cartera se mueve más o menos que el mercado. Podés cambiarla arriba, por ejemplo por el Merval si invertís en acciones argentinas.</p>
    <?php if (!empty($risk['benchmark_short'])): ?><p class="notice"><?= icon('info') ?><span>El <?= e($bName) ?> todavía no tiene historia suficiente en GALPEX (BYMA no publica la serie, se va guardando el cierre de cada día), así que la sensibilidad al índice no se puede calcular por ahora. Para verla, elegí el S&P 500.</span></p><?php endif; ?>
  </div>
</section>

<div class="grid g-side-main">
  <section class="panel">
    <div class="panel-head"><h2>Nivel de riesgo</h2></div>
    <div class="panel-body">
      <?= View::partial('components/gauge', ['score' => $risk['score']['value'] ?? null, 'label' => $risk['score']['label'] ?? null]) ?>
      <?php if ($targetRisk !== null && $risk['score']): $diff = $risk['score']['value'] - $targetRisk; ?>
        <p class="muted" style="text-align:center;margin-top:var(--s3)">Objetivo de la cartera: <?= $targetRisk ?>/10.
          <?= abs($diff) < 1 ? 'El riesgo medido está alineado.' : ($diff > 0 ? 'La cartera asume más riesgo que el objetivo.' : 'La cartera asume menos riesgo que el objetivo.') ?></p>
      <?php endif; ?>
      <p class="disclaimer" style="text-align:center">De 1 a 10 según cuánto suben y bajan los precios en un año (3% ≈ 1, 30% ≈ 10). Suma un punto si casi todo está en pocos activos.</p>
    </div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Qué dicen los números</h2><span class="meta"><?= $m ? 'Del ' . e(short_date($m['from'])) . ' al ' . e(short_date($m['to'])) : '' ?></span></div>
    <div class="panel-body">
      <?php if ($m): ?>
        <dl class="plain-metrics">
          <div class="plain-metric"><dt>Rendimiento en el período</dt><dd class="<?= trend_class($m['return_period']) ?>"><?= e(pct($m['return_period'] * 100, 1)) ?></dd>
            <p>Lo que habría ganado o perdido esta misma cartera en el período elegido.</p></div>
          <div class="plain-metric"><dt>Cuánto se mueve (volatilidad anual)</dt><dd><?= e(pct($m['volatility'] * 100, 1, false)) ?></dd>
            <p><?= $m['benchmark_volatility'] !== null ? 'El ' . e($bName) . ' se movió ' . e(pct($m['benchmark_volatility'] * 100, 1, false)) . '. ' . ($m['volatility'] > $m['benchmark_volatility'] ? 'Tu cartera es más inestable que el índice.' : 'Tu cartera es más estable que el índice.') : 'Cuanto más alto, más grandes las subas y bajas.' ?></p></div>
          <div class="plain-metric"><dt>Peor caída</dt><dd class="down"><?= e(pct($m['max_drawdown'] * 100, 1)) ?></dd>
            <p>La mayor baja desde un máximo hasta el punto más bajo siguiente.</p></div>
          <div class="plain-metric"><dt>Sensibilidad al <?= e($bName) ?> (beta)</dt><dd><?= $m['beta'] !== null ? e(num($m['beta'], 2)) : '—' ?></dd>
            <p><?= $m['beta'] !== null ? 'Si el ' . e($bName) . ' sube o baja 10%, tu cartera tiende a moverse ' . e(pct(abs($m['beta']) * 10, 1, false)) . ($m['beta'] < 0 ? ' en sentido contrario.' : ' en el mismo sentido.') : 'Sin datos suficientes del índice.' ?></p></div>
          <div class="plain-metric"><dt>Pérdida de un mal día (VaR 95%)</dt><dd class="down"><?= $m['var_95_daily'] !== null ? e(pct($m['var_95_daily'] * 100, 2)) : '—' ?></dd>
            <p><?= $m['var_95_daily'] !== null ? 'En 19 de cada 20 días la cartera no perdió más que esto: ' . e(money(abs($m['var_95_daily']) * $v['total_value'], $cur, 0)) . ' sobre el valor actual.' : '' ?></p></div>
          <div class="plain-metric"><dt>Rendimiento por unidad de riesgo (Sharpe)</dt><dd><?= $m['sharpe'] !== null ? e(num($m['sharpe'], 2)) : '—' ?></dd>
            <p>Mayor a 1 es bueno: la ganancia compensó las subas y bajas. Negativo: no superó una tasa sin riesgo del <?= e(pct(($authUser['risk_free_rate'] ?? 0.04) * 100, 1, false)) ?>.</p></div>
          <?php if ($performance): ?>
            <div class="plain-metric"><dt>Tu rentabilidad real</dt><dd class="<?= trend_class($performance['twr']) ?>"><?= e(pct($performance['twr'] * 100, 1)) ?></dd>
              <p>Desde tu primera operación, sin contar depósitos ni retiros.</p></div>
          <?php endif; ?>
        </dl>
        <p class="disclaimer" style="margin-top:var(--s3)">Se aplican los pesos de hoy a los precios reales de cada día del período (<?= (int) $m['observations'] ?> ruedas), convertidos a <?= e($cur) ?>. El pasado no garantiza resultados futuros.</p>
      <?php else: ?>
        <p class="muted">Todavía no hay suficiente historia de precios en común entre tus activos (se necesitan al menos 30 ruedas). Las letras y ONs sin historia pública van sumando su precio de cierre cada día.</p>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="panel" data-compare-panel data-equity="<?= e(app_url('/api/patrimonio')) ?>" data-compare="<?= e(app_url('/api/comparar')) ?>" data-portfolio="<?= e((string) ($selected ?? '')) ?>" data-benchmarks="<?= e(implode(',', array_map(fn ($b) => $b['id'], array_slice($benchmarks, 0, 4)))) ?>">
  <div class="panel-head"><h2>Tu cartera contra los índices</h2>
    <div class="seg" role="group" aria-label="Período"><?php foreach (['3M', '6M', '1A'] as $r): ?><button type="button" data-range="<?= $r ?>" aria-pressed="<?= $r === '1A' ? 'true' : 'false' ?>"><?= $r ?></button><?php endforeach; ?></div>
  </div>
  <div class="panel-body">
    <div class="chart-box"><canvas role="img" aria-label="Comparación base 100"></canvas><div class="chart-state" data-chart-state>Cargando…</div></div>
    <div class="table-wrap"><table class="table table-compact" data-compare-table><thead><tr><th>Línea</th><th class="num">Rentabilidad</th><th class="num">Volatilidad</th><th class="num">Máx. caída</th></tr></thead><tbody></tbody></table></div>
    <p class="disclaimer">Todas las líneas arrancan en 100 para compararlas: si una termina en 110, ganó 10%. La cartera se mide sin contar depósitos ni retiros.</p>
  </div>
</section>

<div class="grid g-3">
  <section class="panel">
    <div class="panel-head"><h2>Concentración</h2></div>
    <div class="panel-body">
      <dl class="kv">
        <dt>Activo de mayor peso</dt><dd><?= e($risk['concentration']['top_asset']['label'] ?? '—') ?> (<?= e(pct($risk['concentration']['top_asset']['weight'] ?? 0, 1, false)) ?>)</dd>
        <dt>Sector principal</dt><dd><?= e($risk['concentration']['top_sector']['label'] ?? '—') ?> (<?= e(pct($risk['concentration']['top_sector']['weight'] ?? 0, 1, false)) ?>)</dd>
        <dt>Índice Herfindahl</dt><dd><?= e(num($risk['concentration']['hhi'], 3)) ?></dd>
        <dt>Activos efectivos</dt><dd><?= e(num($risk['concentration']['effective_assets'], 1)) ?></dd>
      </dl>
      <?php foreach ($risk['concentration']['warnings'] as $w): ?><p class="notice" style="margin-top:var(--s3)"><?= icon('alert') ?><span><?= e($w) ?></span></p><?php endforeach; ?>
      <?php if (!$risk['concentration']['warnings']): ?><p class="faint" style="margin-top:var(--s3)">Sin concentraciones excesivas.</p><?php endif; ?>
    </div>
  </section>
  <section class="panel"><div class="panel-head"><h2>Exposición sectorial</h2></div><div class="panel-body"><?= $barList($risk['concentration']['sectors']) ?></div></section>
  <section class="panel"><div class="panel-head"><h2>Exposición geográfica y por moneda</h2></div><div class="panel-body"><?= $barList($risk['concentration']['countries']) ?><hr><?= $barList($risk['concentration']['currencies']) ?></div></section>
</div>

<div class="grid g-main-side">
  <section class="panel">
    <div class="panel-head"><h2>Riesgo por activo</h2></div>
    <div class="table-wrap">
      <table class="table table-compact" data-enhance id="risk-assets">
        <thead><tr><th data-sort="text">Activo</th><th class="num" data-sort="num">Peso</th><th class="num" data-sort="num">Volatilidad</th><th class="num" data-sort="num">Beta</th><th class="num" data-sort="num">Rent. período</th><th class="num" data-sort="num">Máx. caída</th><th class="num" data-sort="num">Aporte al riesgo</th></tr></thead>
        <tbody>
        <?php foreach ($risk['positions'] as $r): ?>
          <tr>
            <td><span class="sym"><?= e($r['symbol']) ?></span><span class="sym-name"><?= e($r['name']) ?></span></td>
            <td class="num" data-value="<?= e((string) $r['weight']) ?>"><?= e(pct($r['weight'], 1, false)) ?></td>
            <td class="num" data-value="<?= e((string) $r['volatility']) ?>"><?= $r['volatility'] !== null ? e(pct($r['volatility'] * 100, 1, false)) : '—' ?></td>
            <td class="num" data-value="<?= e((string) $r['beta']) ?>"><?= $r['beta'] !== null ? e(num($r['beta'], 2)) : '—' ?></td>
            <td class="num <?= trend_class($r['return_1y']) ?>" data-value="<?= e((string) $r['return_1y']) ?>"><?= $r['return_1y'] !== null ? e(pct($r['return_1y'] * 100, 1)) : '—' ?></td>
            <td class="num down" data-value="<?= e((string) $r['max_drawdown']) ?>"><?= $r['max_drawdown'] !== null ? e(pct($r['max_drawdown'] * 100, 1)) : '—' ?></td>
            <td class="num" data-value="<?= e((string) $r['risk_contribution']) ?>"><?= $r['risk_contribution'] !== null ? e(pct($r['risk_contribution'], 1, false)) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="panel-foot">Aporte al riesgo: qué parte de la volatilidad total explica cada activo, considerando su peso y su correlación con el resto.</div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Correlaciones</h2></div>
    <div class="panel-body table-wrap">
      <?php if ($risk['correlation']['labels']): ?>
        <table class="heat">
          <thead><tr><th></th><?php foreach ($risk['correlation']['labels'] as $l): ?><th><?= e($l) ?></th><?php endforeach; ?></tr></thead>
          <tbody><?php foreach ($risk['correlation']['labels'] as $i => $l): ?><tr><th style="text-align:right"><?= e($l) ?></th>
            <?php foreach ($risk['correlation']['matrix'][$i] as $c): ?><td style="<?= $heat($c) ?>"><?= $c !== null ? e(num($c, 2)) : '—' ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
        </table>
        <p class="disclaimer" style="margin-top:var(--s3)">Cerca de 1: se mueven juntos (diversifican poco). Cerca de 0 o negativa: diversifican.</p>
      <?php else: ?><p class="muted">Sin datos suficientes.</p><?php endif; ?>
    </div>
  </section>
</div>

<section class="panel" data-scenarios data-endpoint="<?= e(app_url('/api/riesgo/escenario')) ?>" data-portfolio="<?= e((string) ($selected ?? '')) ?>" data-currency="<?= e($cur) ?>">
  <div class="panel-head"><h2>¿Qué pasaría si…?</h2><span class="meta">Valor actual: <?= e(money($v['total_value'], $cur, 2)) ?></span></div>
  <div class="panel-body">
    <div class="scenario-presets">
      <?php foreach ([5, 10, 20, 30] as $s): ?><button class="btn btn-sm" type="button" data-preset='{"type":"market_drop","shock":<?= $s ?>}'>Mercado −<?= $s ?>%</button><?php endforeach; ?>
      <button class="btn btn-sm" type="button" data-preset='{"type":"rate_hike","shock":100}'>Tasas +1 pp</button>
      <button class="btn btn-sm" type="button" data-preset='{"type":"rate_hike","shock":300}'>Tasas +3 pp</button>
      <button class="btn btn-sm" type="button" data-preset='{"type":"devaluation","shock":30,"currency":"ARS"}'>Devaluación del peso 30%</button>
    </div>
    <form class="scenario-form" data-scenario-form>
      <div class="field"><label for="sc-type">Escenario</label>
        <select class="select" id="sc-type" name="type">
          <option value="market_drop">Caída del mercado</option><option value="rate_hike">Suba de tasas</option>
          <option value="devaluation">Devaluación de una moneda</option><option value="sector_drop">Caída de un sector</option><option value="asset_drop">Caída de un activo</option>
        </select></div>
      <div class="field"><label for="sc-shock" data-shock-label>Magnitud (%)</label><input class="input input-num" id="sc-shock" name="shock" value="20" inputmode="decimal"></div>
      <div class="field" data-for="devaluation" hidden><label for="sc-ccy">Moneda</label><select class="select" id="sc-ccy" name="currency"><option>ARS</option><option>BRL</option><option>EUR</option><option>USD</option></select></div>
      <div class="field" data-for="sector_drop" hidden><label for="sc-sector">Sector</label><select class="select" id="sc-sector" name="sector"><?php foreach ($sectors as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div class="field" data-for="asset_drop" hidden><label for="sc-asset">Activo</label><select class="select" id="sc-asset" name="instrument_id"><?php foreach ($v['positions'] as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['symbol']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><button class="btn btn-primary" type="submit">Simular</button></div>
    </form>
    <div data-scenario-result hidden>
      <div class="kpis" style="--cols:3;margin-bottom:var(--s4)">
        <div class="kpi"><div class="kpi-label">Valor actual</div><div class="kpi-value" data-r="before"></div></div>
        <div class="kpi"><div class="kpi-label">Valor estimado</div><div class="kpi-value" data-r="after"></div></div>
        <div class="kpi"><div class="kpi-label">Impacto</div><div class="kpi-value" data-r="impact"></div></div>
      </div>
      <div class="table-wrap"><table class="table table-compact"><thead><tr><th>Activo</th><th>Categoría</th><th class="num">Valor</th><th class="num">Variación</th><th class="num">Impacto</th><th>Supuesto aplicado</th></tr></thead><tbody data-r="rows"></tbody></table></div>
      <ul class="disclaimer" data-r="assumptions"></ul>
    </div>
  </div>
</section>
<?php endif; ?>
