<?php
use App\Http\View;
use App\Models\Instrument;
$cur = $i['currency'];
$d = smart_decimals((float) ($i['price'] ?? 0));
$unit = Instrument::quoteUnit($i);
$isBond = $unit > 1;
$tradable = !in_array($i['category_code'], ['index'], true) && $i['asset_class'] !== 'fx';
$groupKey = 'acciones';
foreach (App\Http\Controllers\MarketController::GROUPS as $k => $g) {
    if (in_array($i['category_code'], $g[1], true)) {
        $groupKey = $k;
    }
}
$hasProfile = count($profile) > 1;
?>
<div class="page-head quote-head">
  <div>
    <div class="sym-line">
      <a class="link" href="<?= e(app_url('/mercados', ['panel' => $groupKey])) ?>"><?= e($i['category_name']) ?></a>
      <span><?= e($i['market_name']) ?></span><span><?= e($cur) ?></span>
      <?= View::partial('components/data-status', ['instrument' => $i]) ?>
    </div>
    <h1><?= e($i['symbol']) ?> <span class="muted" style="font-weight:400"><?= e($i['name']) ?></span></h1>
    <div class="quote-price">
      <span class="price-big"><?= $i['price'] !== null ? e(money($i['price'], $cur)) : 'Sin cotización' ?></span>
      <span class="chg <?= trend_class($i['change_pct']) ?>" style="font-size:16px"><?= e(signed_money($i['change_abs'], $cur)) ?> (<?= e(pct($i['change_pct'])) ?>)</span>
      <span class="faint"><?= $i['quoted_at'] ? 'Actualizado ' . e(time_ago($i['fetched_at'])) . ($i['quote_source'] ? ', fuente: ' . e(App\Support\Labels::source($i['quote_source'])) : '') : '' ?></span>
    </div>
    <?php if ($isBond): ?><p class="faint" style="margin:4px 0 0">Precio cada 100 de valor nominal.</p><?php endif; ?>
  </div>
  <div class="page-head-actions">
    <?php if ($watchlists): ?>
      <form method="post" action="<?= e(app_url('/listas/' . $watchlists[0]['id'] . '/alternar/' . $i['id'])) ?>">
        <?= csrf_field() ?>
        <button class="btn" type="submit"><?= icon('star') ?><?= $watched ? 'Quitar de la lista' : 'Seguir' ?></button>
      </form>
    <?php endif; ?>
    <a class="btn" href="<?= e(app_url('/alertas', ['instrumento' => $i['id']])) ?>"><?= icon('bell') ?>Crear alerta</a>
    <?php if ($tradable && $portfolios): ?>
      <button class="btn btn-primary" type="button" data-open-trade><?= icon('plus') ?>Operar</button>
    <?php elseif ($tradable): ?>
      <a class="btn btn-primary" href="<?= e(app_url('/carteras/nueva')) ?>">Crear una cartera para operar</a>
    <?php endif; ?>
  </div>
</div>

<section class="panel" data-instrument-chart data-endpoint="<?= e(app_url('/api/instrumentos/' . $i['id'] . '/historial')) ?>" data-currency="<?= e($cur) ?>" data-symbol="<?= e($i['symbol']) ?>">
  <div class="panel-head">
    <div class="seg" role="group" aria-label="Período">
      <?php foreach (['1M', '3M', '6M', '1A', '3A', '5A'] as $r): ?>
        <button type="button" data-range="<?= $r ?>" aria-pressed="<?= $r === '1A' ? 'true' : 'false' ?>"><?= $r ?></button>
      <?php endforeach; ?>
    </div>
    <div class="row">
      <label class="faint" for="cmp">Comparar con</label>
      <select class="select" id="cmp" data-compare style="min-height:30px;width:auto">
        <option value="">Ninguno</option>
        <?php foreach ($benchmarks as $b): if ((int) $b['id'] === (int) $i['id']) { continue; } ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="panel-body">
    <div class="chart-legend-line" data-chart-summary></div>
    <div class="chart-box lg"><canvas role="img" aria-label="Gráfico histórico de <?= e($i['symbol']) ?>"></canvas><div class="chart-state" data-chart-state>Cargando…</div></div>
  </div>
  <dl class="stat-strip">
    <?php if ($i['bid'] !== null || $i['ask'] !== null): ?>
      <div><dt>Compra (punta)</dt><dd class="up"><?= e(num($i['bid'], $d)) ?> <span class="faint">x <?= e(compact_number($i['bid_size'])) ?></span></dd></div>
      <div><dt>Venta (punta)</dt><dd class="down"><?= e(num($i['ask'], $d)) ?> <span class="faint">x <?= e(compact_number($i['ask_size'])) ?></span></dd></div>
    <?php else: ?>
      <div><dt>Apertura</dt><dd><?= e(num($i['open_price'], $d)) ?></dd></div>
    <?php endif; ?>
    <?php if ($i['day_high'] !== null): ?><div><dt>Máximo del día</dt><dd><?= e(num($i['day_high'], $d)) ?></dd></div><?php endif; ?>
    <?php if ($i['day_low'] !== null): ?><div><dt>Mínimo del día</dt><dd><?= e(num($i['day_low'], $d)) ?></dd></div><?php endif; ?>
    <div><dt>Cierre anterior</dt><dd><?= e(num($i['prev_close'], $d)) ?></dd></div>
    <?php if ($i['volume'] !== null): ?><div><dt>Volumen</dt><dd><?= e(compact_number($i['volume'])) ?></dd></div><?php endif; ?>
    <?php if ($i['trades'] !== null): ?><div><dt>Operaciones</dt><dd><?= e(num($i['trades'], 0)) ?></dd></div><?php endif; ?>
    <?php if (isset($profile['market_cap'])): ?><div><dt>Capitalización</dt><dd><?= e(compact_number($profile['market_cap'])) ?></dd></div><?php endif; ?>
  </dl>
</section>

<section class="panel" data-whatif data-endpoint="<?= e(app_url('/api/instrumentos/' . $i['id'] . '/historial')) ?>" data-currency="<?= e($cur) ?>" data-symbol="<?= e($i['symbol']) ?>">
  <div class="panel-head"><h2>¿Cuánto tendrías hoy?</h2><span class="meta">Con precios reales de <?= e($i['symbol']) ?></span></div>
  <div class="panel-body">
    <form class="whatif" data-whatif-form>
      <div class="field"><label for="wi-amount">Si hubieras invertido (<?= e($cur) ?>)</label><input class="input input-num" id="wi-amount" name="amount" value="<?= $cur === 'ARS' ? '100.000' : '1.000' ?>" inputmode="decimal"></div>
      <div class="field"><label for="wi-when">Hace</label>
        <select class="select" id="wi-when" name="when"><option value="1M">1 mes</option><option value="6M">6 meses</option><option value="1A" selected>1 año</option><option value="3A">3 años</option><option value="5A">5 años</option></select></div>
      <div class="field"><label for="wi-what">Comparado con</label>
        <select class="select" id="wi-what" name="vs"><option value="">Nada (solo <?= e($i['symbol']) ?>)</option>
          <?php foreach ($benchmarks as $b): if ((int) $b['id'] === (int) $i['id']) { continue; } ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><button class="btn btn-primary" type="submit">Calcular</button></div>
    </form>
    <div class="whatif-result" data-whatif-result hidden aria-live="polite"></div>
    <p class="disclaimer" style="margin-top:var(--s2)">Compra al cierre del primer día del período y valuación al último precio. No incluye comisiones ni dividendos cobrados<?= $cur === 'ARS' ? ', y está en pesos nominales (sin descontar la inflación)' : '' ?>.</p>
  </div>
</section>

<div class="grid <?= $hasProfile ? 'g-3' : 'g-2' ?>">
  <section class="panel">
    <div class="panel-head"><h2>Indicadores (1 año)</h2></div>
    <div class="panel-body">
      <?php if ($indicators): ?>
        <dl class="kv">
          <dt>Rentabilidad</dt><dd class="<?= trend_class($indicators['return_1y']) ?>"><?= e(pct($indicators['return_1y'] * 100)) ?></dd>
          <dt>Volatilidad anual</dt><dd><?= e(pct($indicators['volatility'] * 100, 1, false)) ?></dd>
          <dt>Máxima caída</dt><dd class="down"><?= e(pct($indicators['max_drawdown'] * 100, 1)) ?></dd>
          <dt>Sharpe</dt><dd><?= $indicators['sharpe'] !== null ? e(num($indicators['sharpe'], 2)) : '—' ?></dd>
          <dt>Beta vs <?= e($indicators['benchmark'] ?? 'índice') ?></dt><dd><?= $indicators['beta'] !== null ? e(num($indicators['beta'], 2)) : '—' ?></dd>
          <dt>Máximo 52 semanas</dt><dd><?= e(num($indicators['high_52'], $d)) ?></dd>
          <dt>Mínimo 52 semanas</dt><dd><?= e(num($indicators['low_52'], $d)) ?></dd>
          <dt>Media móvil 50 ruedas</dt><dd><?= e(num($indicators['sma50'], $d)) ?></dd>
          <dt>Media móvil 200 ruedas</dt><dd><?= e(num($indicators['sma200'], $d)) ?></dd>
        </dl>
      <?php else: ?>
        <p class="muted">No hay historia suficiente para calcular indicadores.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Información</h2></div>
    <div class="panel-body">
      <?php if ($i['description']): ?><p><?= e($i['description']) ?></p><?php endif; ?>
      <dl class="kv">
        <dt>Símbolo</dt><dd><?= e($i['symbol']) ?></dd>
        <dt>Mercado</dt><dd><?= e($i['market_name']) ?></dd>
        <dt>Sector</dt><dd><?= e($i['sector'] ?? '—') ?></dd>
        <dt>País</dt><dd><?= e($countryLabel) ?></dd>
        <dt>Moneda</dt><dd><?= e($cur) ?></dd>
        <dt>Operatoria</dt><dd><?= Instrument::isFractional($i) ? 'Admite fracciones' : 'Unidades enteras' ?></dd>
        <?php if ($i['coupon_rate'] !== null): ?><dt>Cupón anual</dt><dd><?= e(pct((float) $i['coupon_rate'] * 100, 2, false)) ?></dd><?php endif; ?>
        <?php if ($i['duration_years'] !== null): ?><dt>Duración estimada</dt><dd><?= e(num($i['duration_years'], 1)) ?> años</dd><?php endif; ?>
        <?php if ($i['maturity_date'] !== null): ?><dt>Vencimiento</dt><dd><?= e(short_date($i['maturity_date'])) ?></dd><?php endif; ?>
      </dl>
    </div>
  </section>

  <?php if ($hasProfile): ?>
  <section class="panel">
    <div class="panel-head"><h2>Fundamentales</h2><?php if (!empty($profile['_source'])): ?><span class="meta">Fuente: <?= e($profile['_source']) ?></span><?php endif; ?></div>
    <div class="panel-body">
        <dl class="kv">
          <?php if (isset($profile['pe_ratio'])): ?><dt>Relación precio/ganancia</dt><dd><?= e(num($profile['pe_ratio'], 1)) ?></dd><?php endif; ?>
          <?php if (isset($profile['eps'])): ?><dt>Ganancia por acción</dt><dd><?= e(num($profile['eps'], 2)) ?></dd><?php endif; ?>
          <?php if (isset($profile['dividend_yield'])): ?><dt>Rendimiento por dividendo</dt><dd><?= e(pct($profile['dividend_yield'], 2, false)) ?></dd><?php endif; ?>
          <?php if (isset($profile['beta'])): ?><dt>Beta informada</dt><dd><?= e(num($profile['beta'], 2)) ?></dd><?php endif; ?>
          <?php if (isset($profile['industry'])): ?><dt>Industria</dt><dd><?= e($profile['industry']) ?></dd><?php endif; ?>
          <?php if (isset($profile['ipo'])): ?><dt>Salida a bolsa</dt><dd><?= e($profile['ipo']) ?></dd><?php endif; ?>
        </dl>
    </div>
  </section>
  <?php endif; ?>
</div>

<div class="grid g-2">
  <section class="panel">
    <div class="panel-head"><h2>Tu posición</h2></div>
    <?php if ($positions): ?>
      <table class="table">
        <thead><tr><th>Cartera</th><th class="num">Cantidad</th><th class="num">Precio promedio</th><th class="num">Resultado realizado</th></tr></thead>
        <tbody>
        <?php foreach ($positions as $p): ?>
          <tr class="is-link" data-href="<?= e(app_url('/carteras/' . $p['portfolio_id'])) ?>">
            <td><?= e($p['portfolio_name']) ?></td>
            <td class="num"><?= e(num($p['quantity'], Instrument::isFractional($i) ? 6 : 0)) ?></td>
            <td class="num"><?= e(money($p['avg_cost'], $cur)) ?></td>
            <td class="num <?= trend_class($p['realized_pnl']) ?>"><?= e(signed_money($p['realized_pnl'], $p['base_currency'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty"><p>No tenés <?= e($i['symbol']) ?> en ninguna cartera.</p></div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Noticias de <?= e($i['symbol']) ?></h2></div>
    <?php if ($news): ?>
      <div class="list">
        <?php foreach ($news as $n): ?>
          <a class="list-item" href="<?= e($n['url']) ?>" target="_blank" rel="noopener noreferrer">
            <span><span class="title"><?= e($n['headline']) ?></span><br><span class="meta"><?= e($n['source']) ?>, <?= e(time_ago($n['datetime'])) ?></span></span>
            <?= icon('external', 'icon icon-sm') ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><p>Sin noticias de los últimos días sobre <?= e($i['symbol']) ?>.</p></div>
    <?php endif; ?>
  </section>
</div>

<?php if ($tradable && $portfolios): ?>
  <?= View::partial('partials/trade-dialog', ['portfolios' => $portfolios, 'instrument' => $i]) ?>
<?php endif; ?>
