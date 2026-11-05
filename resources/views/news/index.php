<div class="page-head">
  <div>
    <h1>Noticias</h1>
    <p class="sub">Noticias económicas y financieras de medios argentinos e internacionales, con el enlace a la nota original<?= $held ? ', y las de los activos que tenés en cartera' : '' ?>.</p>
  </div>
</div>

<div class="grid <?= $earningsAvailable ? 'g-main-side' : '' ?>">
  <section class="panel">
    <nav class="board-tabs" aria-label="Tema">
      <?php foreach ($tabs as $k => $l): ?><a href="<?= e(app_url('/noticias', ['tema' => $k])) ?>" class="<?= $tab === $k ? 'is-active' : '' ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
    </nav>
    <?php if ($error): ?><div class="panel-body"><div class="notice notice-error"><?= icon('alert') ?><p><?= e($error) ?> Revisá la conexión a internet y volvé a cargar la página.</p></div></div><?php endif; ?>
    <?php if ($tab === 'mine' && !$held): ?>
      <div class="empty"><h3>Todavía no tenés activos en cartera</h3><p>Cuando compres acciones, CEDEARs, ETFs o cripto, acá vas a ver las noticias de esas empresas.</p><a class="btn btn-primary" href="<?= e(app_url('/mercados')) ?>">Ir a Mercados</a></div>
    <?php elseif (!$items && !$error): ?>
      <div class="empty"><p>No hay noticias de los últimos días en este tema.</p></div>
    <?php endif; ?>
    <div class="news-list">
      <?php foreach ($items as $n): ?>
        <a class="news-item" href="<?= e($n['url']) ?>" target="_blank" rel="noopener noreferrer">
          <h3><?= e($n['headline']) ?></h3>
          <div class="meta"><span class="source"><?= e($n['source']) ?></span><time datetime="<?= e($n['datetime']) ?>Z"><?= e(time_ago($n['datetime'])) ?></time></div>
          <?php if ($n['related']): ?><span class="tags"><?php foreach (array_slice($n['related'], 0, 3) as $s): ?><span class="badge badge-brand"><?= e($s) ?></span><?php endforeach; ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="panel-foot">Fuente: Google Noticias. Cada titular abre la nota en el sitio del medio que la publicó.</div>
  </section>

  <?php if ($earningsAvailable): ?>
  <section class="panel">
    <div class="panel-head"><h2>Presentación de resultados</h2><span class="meta">Próximos 30 días</span></div>
    <?php if ($earnings['error']): ?><div class="panel-body"><p class="down"><?= e($earnings['error']) ?></p></div><?php endif; ?>
    <div class="table-wrap">
      <table class="table table-compact">
        <thead><tr><th>Fecha</th><th>Empresa</th><th class="num">Ganancia por acción estimada</th><th>Momento</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($earnings['items'], 0, 30) as $e): ?>
          <tr><td class="nowrap"><?= e(short_date($e['date'])) ?></td><td class="sym"><?= e($e['symbol']) ?></td><td class="num"><?= isset($e['epsEstimate']) ? e(num($e['epsEstimate'], 2)) : '—' ?></td>
            <td class="muted"><?= e(['bmo' => 'Antes de la apertura', 'amc' => 'Después del cierre', 'dmh' => 'Durante la rueda'][$e['hour'] ?? ''] ?? '—') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$earnings['items'] && !$earnings['error']): ?><p class="empty">Sin presentaciones de resultados próximas.</p><?php endif; ?>
    </div>
    <div class="panel-foot">Fuente: Finnhub.</div>
  </section>
  <?php endif; ?>
</div>
