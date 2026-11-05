<?php
use App\Http\View;
use App\Support\Labels;
$cur = $currency;
$first = explode(' ', (string) ($authUser['name'] ?? ''))[0];
$hasPortfolios = $portfolios !== [];
$palette = ['--c1', '--c2', '--c3', '--c4', '--c5', '--c6', '--c7', '--c8'];
?>
<div class="page-head">
  <div>
    <h1>Hola, <?= e($first) ?></h1>
    <p class="sub">Patrimonio consolidado en <?= e($cur) ?><?= $selected ? ' de la cartera elegida' : '' ?>, calculado a las <?= e(date('H:i')) ?>.</p>
  </div>
  <div class="page-head-actions">
    <?php if ($hasPortfolios): ?>
      <form method="get" action="<?= e(app_url('/inicio')) ?>" data-autosubmit>
        <label class="sr-only" for="cartera">Cartera</label>
        <select class="select" id="cartera" name="cartera">
          <option value="">Todas las carteras</option>
          <?php foreach ($portfolios as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $selected === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(app_url('/carteras/nueva')) ?>"><?= icon('plus') ?>Nueva cartera</a>
  </div>
</div>

<?php if (!$hasPortfolios): ?>
  <section class="panel start">
    <div class="panel-head"><h2>Tu cuenta está en cero</h2><span class="meta">Todo lo que aparezca acá va a ser lo que vos hagas</span></div>
    <ol class="start-steps">
      <li><strong>Creá una cartera</strong><span>Elegí la moneda (pesos o dólares) y cuánto dinero virtual depositás para empezar.</span><a class="btn btn-primary btn-sm" href="<?= e(app_url('/carteras/nueva')) ?>">Crear cartera</a></li>
      <li><strong>Buscá una especie</strong><span>Acciones, CEDEARs, bonos, ONs, ETFs o cripto, a precio real de mercado.</span><a class="btn btn-sm" href="<?= e(app_url('/mercados')) ?>">Ir a Mercados</a></li>
      <li><strong>Comprá y seguí el resultado</strong><span>Cada operación se registra en tu historial, con su comisión y su ganancia o pérdida.</span><a class="btn btn-sm" href="<?= e(app_url('/asesor')) ?>">Pedir una propuesta al asesor</a></li>
    </ol>
  </section>
<?php else: ?>


<section class="kpis" style="--cols:5" aria-label="Resumen patrimonial">
  <?= View::partial('components/kpi', ['lead' => true, 'label' => 'Patrimonio total', 'value' => money_parts($summary['total_value'], $cur),
      'sub' => '<span class="chg ' . trend_class($summary['day_change']) . '">' . e(pct($summary['day_change_pct'])) . '</span> <span class="faint">hoy</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Capital invertido', 'value' => money_parts($summary['cost_basis'], $cur),
      'sub' => '<span class="faint">Costo de las posiciones abiertas</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Efectivo disponible', 'value' => money_parts($summary['cash'], $cur),
      'sub' => '<span class="faint">' . e(pct($summary['total_value'] > 0 ? $summary['cash'] / $summary['total_value'] * 100 : 0, 1, false)) . ' del patrimonio</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Ganancia / pérdida', 'value' => '<span class="' . trend_class($summary['total_pnl']) . '">' . e(signed_money($summary['total_pnl'], $cur)) . '</span>',
      'sub' => '<span class="chg ' . trend_class($summary['total_return_pct']) . '">' . e(pct($summary['total_return_pct'])) . '</span> <span class="faint">sobre lo aportado</span>']) ?>
  <?= View::partial('components/kpi', ['label' => 'Resultado del día', 'value' => '<span class="' . trend_class($summary['day_change']) . '">' . e(signed_money($summary['day_change'], $cur)) . '</span>',
      'sub' => '<span class="faint">Realizado acumulado: ' . e(signed_money($summary['realized_pnl'], $cur)) . '</span>']) ?>
</section>

<div class="grid g-main-side">
  <section class="panel" data-equity-chart data-endpoint="<?= e(app_url('/api/patrimonio')) ?>" data-portfolio="<?= e((string) ($selected ?? '')) ?>" data-currency="<?= e($cur) ?>">
    <div class="panel-head">
      <h2>Evolución del patrimonio</h2>
      <div class="seg" role="group" aria-label="Período">
        <button type="button" disabled title="No hay datos intradiarios">1D</button>
        <?php foreach (['1S', '1M', '3M', '6M', '1A', 'MAX'] as $r): ?>
          <button type="button" data-range="<?= $r ?>" aria-pressed="<?= $r === '3M' ? 'true' : 'false' ?>"><?= $r === 'MAX' ? 'Máx.' : $r ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="panel-body">
      <div class="chart-legend-line" data-chart-summary></div>
      <div class="chart-box"><canvas aria-label="Gráfico de evolución patrimonial" role="img"></canvas><div class="chart-state" data-chart-state>Cargando…</div></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Riesgo de la cartera</h2><a class="link" href="<?= e(app_url('/riesgo', $selected ? ['cartera' => $selected] : [])) ?>">Ver análisis</a></div>
    <div class="panel-body">
      <?= View::partial('components/gauge', ['score' => $risk['score']['value'] ?? null, 'label' => $risk['score']['label'] ?? null]) ?>
      <?php if ($risk['metrics']): $m = $risk['metrics']; ?>
        <dl class="kv risk-kv">
          <dt>Volatilidad anual</dt><dd><?= e(pct($m['volatility'] * 100, 1, false)) ?></dd>
          <dt>Máxima caída (1 año)</dt><dd class="down"><?= e(pct($m['max_drawdown'] * 100, 1)) ?></dd>
          <dt>Sharpe</dt><dd><?= $m['sharpe'] !== null ? e(num($m['sharpe'], 2)) : '—' ?></dd>
          <dt>Beta vs <?= e($risk['benchmark']['symbol'] ?? '—') ?></dt><dd><?= $m['beta'] !== null ? e(num($m['beta'], 2)) : '—' ?></dd>
        </dl>
        <p class="disclaimer">Con la composición actual sobre el último año.</p>
      <?php else: ?>
        <p class="muted">Se necesita al menos un mes de historia de precios de tus activos para medir el riesgo.</p>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="grid g-3">
  <section class="panel" data-allocation='<?= json_attr(['category' => $summary['allocation']['category'], 'asset' => $summary['allocation']['asset'], 'currency' => $summary['allocation']['currency'], 'country' => $summary['allocation']['country']]) ?>' data-currency="<?= e($cur) ?>">
    <div class="panel-head">
      <h2>Distribución</h2>
      <div class="seg" role="group" aria-label="Agrupar por">
        <button type="button" data-dim="category" aria-pressed="true">Tipo</button>
        <button type="button" data-dim="asset" aria-pressed="false">Activo</button>
        <button type="button" data-dim="currency" aria-pressed="false">Moneda</button>
        <button type="button" data-dim="country" aria-pressed="false">País</button>
      </div>
    </div>
    <div class="panel-body alloc">
      <div class="donut-box"><canvas aria-label="Gráfico de distribución" role="img"></canvas></div>
      <div class="legend" data-legend></div>
    </div>
    <div class="panel-foot" data-alloc-detail>Elegí un sector del gráfico para ver qué activos lo componen.</div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Principales posiciones</h2><a class="link" href="<?= e(app_url('/carteras')) ?>">Ver carteras</a></div>
    <div class="table-wrap">
      <table class="table table-compact">
        <thead><tr><th>Activo</th><th class="num">Valor</th><th class="num">Peso</th><th class="num">Resultado</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($summary['positions'], 0, 7) as $p): ?>
          <tr class="is-link" data-href="<?= e(app_url('/mercados/instrumento/' . $p['id'])) ?>">
            <td><span class="sym"><?= e($p['symbol']) ?></span><span class="sym-name"><?= e($p['name']) ?></span></td>
            <td class="num"><?= e(money($p['market_value'], $cur, 0)) ?></td>
            <td class="num muted"><?= e(pct($p['weight'], 1, false)) ?></td>
            <td class="num <?= trend_class($p['unrealized_pct']) ?>"><?= e(pct($p['unrealized_pct'], 1)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($summary['positions'] === []): ?><tr><td colspan="4" class="muted">Sin posiciones abiertas. Todo el capital está en efectivo.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Tus carteras</h2><a class="link" href="<?= e(app_url('/carteras')) ?>">Administrar</a></div>
    <div class="list">
      <?php foreach ($cards as $c): ?>
        <a class="list-item portfolio-line" href="<?= e(app_url('/carteras/' . $c['id'])) ?>" style="--pc: <?= e($c['color'] ?? 'var(--c1)') ?>">
          <span><span class="title"><?= e($c['name']) ?></span><br><span class="meta"><?= e(money($c['total_value'], $c['currency'], 0)) ?>, <span class="<?= trend_class($c['total_return_pct']) ?>"><?= e(pct($c['total_return_pct'], 1)) ?></span> total</span></span>
          <?= sparkline($c['spark'] ?? []) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php endif; ?>

<div class="grid g-3">
  <section class="panel">
    <div class="panel-head"><h2>Referencias del mercado</h2><a class="link" href="<?= e(app_url('/mercados')) ?>">Ver mercados</a></div>
    <div class="table-wrap">
      <table class="table table-compact">
        <tbody>
        <?php foreach ($indices as $i): ?>
          <tr class="is-link" data-href="<?= e(app_url('/mercados/instrumento/' . $i['id'])) ?>">
            <td><span class="sym"><?= e(Labels::tapeName($i)) ?></span><span class="sym-name"><?= View::partial('components/data-status', ['instrument' => $i]) ?></span></td>
            <td class="spark-cell"><?= sparkline($i['spark'], 64, 24) ?></td>
            <td class="num"><?= $i['price'] !== null ? e(num($i['price'], smart_decimals((float) $i['price']))) : '—' ?><br><span class="chg <?= trend_class($i['change_pct']) ?>"><?= e(pct($i['change_pct'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Mayores variaciones</h2><span class="meta">Especies destacadas, hoy</span></div>
    <div class="list">
      <?php foreach ($movers as $m): ?>
        <a class="list-item" href="<?= e(app_url('/mercados/instrumento/' . $m['id'])) ?>">
          <span><span class="title"><?= e($m['symbol']) ?></span> <span class="meta"><?= e($m['category_name']) ?></span><br><span class="meta"><?= e($m['name']) ?></span></span>
          <span class="num"><?= e(money($m['price'], $m['currency'])) ?><br><span class="chg <?= trend_class($m['change_pct']) ?>"><?= e(pct($m['change_pct'])) ?></span></span>
        </a>
      <?php endforeach; ?>
      <?php if ($movers === []): ?><p class="empty">Las variaciones aparecen cuando se actualizan las cotizaciones del día.</p><?php endif; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Noticias</h2><a class="link" href="<?= e(app_url('/noticias')) ?>">Ver todas</a></div>
    <?php if ($newsError): ?>
      <div class="empty"><p><?= e($newsError) ?></p></div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($news as $n): ?>
          <a class="list-item" href="<?= e($n['url']) ?>" target="_blank" rel="noopener noreferrer">
            <span><span class="title"><?= e($n['headline']) ?></span><br><span class="meta"><?= e($n['source']) ?>, <?= e(time_ago($n['datetime'])) ?></span></span>
            <?= icon('external', 'icon icon-sm') ?>
          </a>
        <?php endforeach; ?>
        <?php if ($news === []): ?><p class="empty">No hay noticias de los últimos días.</p><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if ($hasPortfolios): ?>
<div class="grid g-2">
  <section class="panel">
    <div class="panel-head"><h2>Últimos movimientos</h2><a class="link" href="<?= e(app_url('/informes')) ?>">Historial</a></div>
    <div class="list">
      <?php foreach ($recent as $t): ?>
        <div class="list-item">
          <span><span class="title"><?= e(Labels::txType($t['type'])) ?> <?= e($t['symbol'] ?? '') ?></span><br><span class="meta"><?= e($t['portfolio_name']) ?>, <?= e(local_date($t['executed_at'], 'd/m/Y')) ?></span></span>
          <span class="num <?= trend_class($t['cash_effect']) ?>"><?= e(signed_money($t['cash_effect'], $t['base_currency'])) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if ($recent === []): ?><p class="empty">Todavía no hay movimientos.</p><?php endif; ?>
    </div>
  </section>
</div>

  <section class="panel">
    <div class="panel-head"><h2>Alertas activas</h2><a class="link" href="<?= e(app_url('/alertas')) ?>">Gestionar</a></div>
    <div class="list">
      <?php foreach ($alerts as $a): ?>
        <div class="list-item">
          <span><span class="title"><?= e(App\Models\Alert::TYPES[$a['type']]) ?> <?= in_array($a['type'], ['price_above', 'price_below'], true) ? e(money($a['threshold'], $a['instrument_currency'] ?? 'USD')) : e(num($a['threshold'], 2)) ?></span><br>
            <span class="meta"><?= e($a['symbol'] ?? $a['portfolio_name'] ?? '') ?><?= $a['note'] ? ': ' . e($a['note']) : '' ?></span></span>
          <?php if ($a['current_price'] !== null && in_array($a['type'], ['price_above', 'price_below'], true)): ?>
            <span class="num"><span class="faint">Actual</span><br><?= e(money($a['current_price'], $a['instrument_currency'])) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($alerts === []): ?><div class="empty"><p>No tenés alertas activas.</p><a class="btn btn-sm" href="<?= e(app_url('/alertas')) ?>">Crear alerta</a></div><?php endif; ?>
    </div>
  </section>
</div>
<?php endif; ?>
