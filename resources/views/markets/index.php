<?php
use App\Http\View;
use App\Support\Labels;

$hasPuntas = $q === '' && in_array($group, ['acciones', 'cedears', 'bonos', 'letras', 'on', 'eeuu', 'dolar'], true);
$pageUrl = fn (int $p) => app_url('/mercados', array_filter(['panel' => $q === '' ? $group : null, 'q' => $q !== '' ? $q : null, 'pagina' => $p > 1 ? $p : null]));
$qty = fn ($v) => $v === null ? '—' : compact_number((float) $v);
$px = fn ($v, array $i) => $v === null ? '—' : num((float) $v, smart_decimals((float) $v));
?>
<div class="page-head">
  <div>
    <h1>Mercados</h1>
    <p class="sub"><?= $q !== '' ? 'Resultados para «' . e($q) . '» en todo el catálogo.' : e($groups[$group][2]) ?></p>
  </div>
  <form class="page-head-actions" method="get" action="<?= e(app_url('/mercados')) ?>" role="search">
    <div class="ticker-search" data-global-search>
      <label class="sr-only" for="mq">Buscar especie</label>
      <input class="input" id="mq" type="search" name="q" value="<?= e($q) ?>" placeholder="Ticker o nombre" autocomplete="off" aria-controls="mq-results" aria-expanded="false">
      <div class="popover search-results" id="mq-results" role="listbox" hidden></div>
    </div>
    <button class="btn" type="submit"><?= icon('search', 'icon icon-sm') ?>Buscar</button>
  </form>
</div>

<div class="grid g-main-side">
  <section class="panel">
    <nav class="board-tabs" aria-label="Paneles">
      <?php foreach ($groups as $k => [$label]): ?>
        <a href="<?= e(app_url('/mercados', ['panel' => $k])) ?>" class="<?= $q === '' && $group === $k ? 'is-active' : '' ?>" <?= $q === '' && $group === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?><span class="count"><?= e(num($counts[$k], 0)) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="table-wrap">
      <table class="table board" id="markets-table" data-enhance data-quotes-table>
        <thead>
          <?php if ($hasPuntas): ?>
            <tr class="group-head"><th colspan="1"></th><th colspan="2" class="col-bid">Compra</th><th colspan="2" class="col-ask">Venta</th><th colspan="5"></th></tr>
          <?php endif; ?>
          <tr>
            <th data-sort="text">Especie</th>
            <?php if ($hasPuntas): ?>
              <th class="num col-bid qty" data-sort="num">Cant.</th><th class="num col-bid" data-sort="num">Precio</th>
              <th class="num col-ask" data-sort="num">Precio</th><th class="num col-ask qty" data-sort="num">Cant.</th>
            <?php endif; ?>
            <th class="num" data-sort="num">Último</th><th class="num" data-sort="num">Var. %</th>
            <th class="num hide-sm" data-sort="num">Monto operado</th><th class="hide-sm">Moneda</th><th class="hide-sm">Dato</th>
            <?php if ($tradable || $q !== ''): ?><th><span class="sr-only">Operar</span></th><?php endif; ?>
            <th class="watch"><span class="sr-only">Seguir</span></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($instruments as $i): $unit = App\Models\Instrument::quoteUnit($i); ?>
          <tr class="is-link" data-href="<?= e(app_url('/mercados/instrumento/' . $i['id'])) ?>" data-id="<?= (int) $i['id'] ?>">
            <td><a class="sym" href="<?= e(app_url('/mercados/instrumento/' . $i['id'])) ?>"><?= e($i['symbol']) ?></a><span class="sym-name"><?= e($i['name'] !== $i['symbol'] ? $i['name'] : $i['category_name']) ?></span></td>
            <?php if ($hasPuntas): ?>
              <td class="num col-bid qty"><?= e($qty($i['bid_size'])) ?></td>
              <td class="num col-bid" data-value="<?= e((string) ($i['bid'] ?? '')) ?>"><?= e($px($i['bid'], $i)) ?></td>
              <td class="num col-ask" data-value="<?= e((string) ($i['ask'] ?? '')) ?>"><?= e($px($i['ask'], $i)) ?></td>
              <td class="num col-ask qty"><?= e($qty($i['ask_size'])) ?></td>
            <?php endif; ?>
            <td class="num col-last" data-value="<?= e((string) ($i['price'] ?? '')) ?>" data-px><?= e($px($i['price'], $i)) ?></td>
            <td class="num" data-value="<?= e((string) ($i['change_pct'] ?? '')) ?>"><span class="chg <?= trend_class($i['change_pct']) ?>" data-chg><?= e(pct($i['change_pct'])) ?></span></td>
            <td class="num muted hide-sm" data-value="<?= e((string) (($i['volume'] ?? 0) * (float) ($i['price'] ?? 0) / $unit)) ?>"><?= $i['volume'] !== null && $i['price'] !== null && !in_array($i['asset_class'], ['index', 'fx'], true) ? e(compact_number((float) $i['volume'] * (float) $i['price'] / $unit)) : '—' ?></td>
            <td class="muted hide-sm"><?= e($i['currency']) ?><?= $unit > 1 ? ' <span class="faint" title="Precio cada 100 de valor nominal">c/100</span>' : '' ?></td>
            <td class="hide-sm" data-ds><?= View::partial('components/data-status', ['instrument' => $i]) ?></td>
            <?php if ($tradable || $q !== ''): ?>
              <td class="num">
                <?php if (!in_array($i['asset_class'], ['index', 'fx'], true) && $i['price'] !== null): ?>
                  <?php if ($portfolios): ?>
                    <button class="btn btn-sm" type="button" data-open-trade data-side="buy" data-instrument="<?= (int) $i['id'] ?>" data-label="<?= e($i['symbol'] . ', ' . $i['name']) ?>">Operar</button>
                  <?php else: ?>
                    <a class="btn btn-sm" href="<?= e(app_url('/carteras/nueva')) ?>" title="Necesitás una cartera para operar">Operar</a>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td class="num watch">
              <?php if ($watchlists): ?>
                <form method="post" action="<?= e(app_url('/listas/' . $watchlists[0]['id'] . '/alternar/' . $i['id'])) ?>" data-watch-toggle>
                  <?= csrf_field() ?>
                  <button class="btn btn-ghost btn-icon btn-sm <?= in_array((int) $i['id'], $watchedIds, true) ? 'is-watched' : '' ?>" type="submit" aria-pressed="<?= in_array((int) $i['id'], $watchedIds, true) ? 'true' : 'false' ?>" title="Agregar o quitar de «<?= e($watchlists[0]['name']) ?>»"><?= icon('star', 'icon icon-sm') ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($instruments === []): ?>
        <div class="empty">
          <h3>Sin resultados</h3>
          <p><?= $q !== '' ? 'No encontramos especies con ese ticker o nombre. Probá con el ticker de BYMA (GGAL, AL30, YMCXO) o de Nueva York (AAPL).' : 'Todavía no se descargó este panel. Revisá la conexión a internet y volvé a abrir la página.' ?></p>
        </div>
      <?php endif; ?>
    </div>
    <div class="pager">
      <span><?= e(num($total, 0)) ?> especies<?= $pages > 1 ? ', página ' . $page . ' de ' . $pages : '' ?>. <?= $q === '' && !in_array($group, ['indices', 'dolar'], true) ? 'Ordenadas por monto operado hoy.' : '' ?></span>
      <?php if ($pages > 1): ?>
        <span class="pager-links">
          <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e($pageUrl($page - 1)) ?>">Anterior</a><?php endif; ?>
          <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e($pageUrl($page + 1)) ?>">Siguiente</a><?php endif; ?>
        </span>
      <?php endif; ?>
    </div>
  </section>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Mis listas</h2></div>
      <?php if (count($watchlists) > 1): ?>
        <nav class="tabs" style="padding:0 var(--s4)"><?php foreach ($watchlists as $w): ?>
          <a href="<?= e(app_url('/mercados', ['lista' => $w['id'], 'panel' => $group])) ?>" class="<?= (int) $w['id'] === $activeList ? 'is-active' : '' ?>"><?= e($w['name']) ?> <span class="faint"><?= (int) $w['items'] ?></span></a>
        <?php endforeach; ?></nav>
      <?php endif; ?>
      <div class="list">
        <?php foreach ($watchItems as $w): ?>
          <a class="list-item" href="<?= e(app_url('/mercados/instrumento/' . $w['id'])) ?>">
            <span><span class="title"><?= e($w['symbol']) ?></span><br><span class="meta"><?= e($w['name']) ?></span></span>
            <span class="num"><?= $w['price'] !== null ? e(money($w['price'], $w['currency'])) : '—' ?><br><span class="chg <?= trend_class($w['change_pct']) ?>"><?= e(pct($w['change_pct'])) ?></span></span>
          </a>
        <?php endforeach; ?>
        <?php if ($watchItems === []): ?><div class="empty"><p>Tocá la estrella de una especie para seguirla desde acá.</p></div><?php endif; ?>
      </div>
      <form class="panel-foot row" method="post" action="<?= e(app_url('/listas')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="wl-name">Nombre de la nueva lista</label>
        <input class="input" id="wl-name" name="name" placeholder="Nombre de una lista nueva" maxlength="80" required style="flex:1;min-height:32px">
        <button class="btn btn-sm" type="submit">Crear lista</button>
      </form>
      <?php if ($activeList && count($watchlists) > 1): ?>
        <form class="panel-foot" method="post" action="<?= e(app_url('/listas/' . $activeList . '/eliminar')) ?>" data-confirm="¿Eliminar esta lista?">
          <?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('trash', 'icon icon-sm') ?>Eliminar la lista actual</button>
        </form>
      <?php endif; ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Cómo leer la pizarra</h2></div>
      <div class="panel-body">
        <dl class="glossary">
          <dt>Compra</dt><dd>La mejor oferta para comprar: precio y cantidad de títulos.</dd>
          <dt>Venta</dt><dd>La mejor oferta para vender.</dd>
          <dt>Último</dt><dd>Precio de la última operación.</dd>
          <?php foreach (Labels::DATA_STATUS as $k => [$l, $h]): ?>
            <dt><span class="ds ds-<?= e($k) ?>"><?= e($l) ?></span></dt><dd><?= e($h) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
    </section>
  </aside>
</div>

<?php if ($portfolios): ?>
  <?= View::partial('partials/trade-dialog', ['portfolios' => $portfolios]) ?>
<?php endif; ?>
