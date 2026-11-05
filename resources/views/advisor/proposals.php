<?php
$p = $profile;
$alloc = $p['answers']['allocation'] ?? [];
$classColors = ['Renta variable' => 'var(--c1)', 'Renta fija' => 'var(--c2)', 'Liquidez' => 'var(--c7)', 'Cripto' => 'var(--c3)'];
?>
<div class="page-head">
  <div>
    <p class="sub" style="margin:0 0 2px"><a class="link" href="<?= e(app_url('/asesor')) ?>">Asesor inversor</a></p>
    <h1>Tus propuestas</h1>
    <p class="sub">Perfil calculado el <?= e(local_date($p['created_at'], 'd/m/Y H:i')) ?>. Podés ajustar los pesos y elegir qué activos incorporar antes de crear la cartera.</p>
  </div>
  <a class="btn" href="<?= e(app_url('/asesor')) ?>">Responder de nuevo</a>
</div>

<dl class="profile-bar">
  <div><dt>Perfil</dt><dd><?= e($p['label']) ?> (<?= (int) $p['risk_score'] ?>/100)</dd></div>
  <div><dt>Tolerancia / capacidad</dt><dd><?= (int) $p['risk_tolerance'] ?> / <?= (int) $p['risk_capacity'] ?></dd></div>
  <div><dt>Capital y horizonte</dt><dd><?= e(money($p['capital'], $p['currency'], 0)) ?>, <?= (int) $p['horizon_years'] ?> años</dd></div>
  <div><dt>Asignación estratégica</dt><dd style="font-size:var(--fs-md)"><?= e(pct(($alloc['equity'] ?? 0) * 100, 0, false)) ?> variable, <?= e(pct(($alloc['fixed_income'] ?? 0) * 100, 0, false)) ?> fija, <?= e(pct(($alloc['liquidity'] ?? 0) * 100, 0, false)) ?> liquidez, <?= e(pct(($alloc['crypto'] ?? 0) * 100, 0, false)) ?> cripto</dd></div>
</dl>

<?php foreach ($proposals as $k => $pr): $a = $pr['analysis'] ?? []; ?>
  <section class="panel" id="propuesta-<?= (int) $pr['id'] ?>">
    <div class="panel-head">
      <h2><?= e($pr['name']) ?></h2>
      <div class="row">
        <span class="fit" title="Afinidad con tu perfil">Afinidad <span class="fit-bar"><span style="width:<?= (int) $pr['fit_score'] ?>%"></span></span> <?= (int) $pr['fit_score'] ?>%</span>
        <?php if ($pr['accepted_portfolio_id']): ?><a class="badge badge-up" href="<?= e(app_url('/carteras/' . $pr['accepted_portfolio_id'])) ?>">Cartera creada</a><?php endif; ?>
      </div>
    </div>
    <div class="proposal">
      <form class="proposal-main" method="post" action="<?= e(app_url('/asesor/propuesta/' . $pr['id'] . '/crear')) ?>" data-proposal-form>
        <?= csrf_field() ?>
        <div class="mix" aria-hidden="true">
          <?php foreach (($pr['class_mix'] ?? []) as $cls => $w): ?><span style="width:<?= e((string) $w) ?>%;background:<?= $classColors[$cls] ?? 'var(--c8)' ?>" title="<?= e($cls . ' ' . $w . '%') ?>"></span><?php endforeach; ?>
        </div>
        <p class="faint" style="font-size:var(--fs-sm)"><?php foreach (($pr['class_mix'] ?? []) as $cls => $w): ?><span class="dot-swatch" style="background:<?= $classColors[$cls] ?? 'var(--c8)' ?>"></span><?= e($cls) ?> <?= e(pct($w, 1, false)) ?>&nbsp;&nbsp; <?php endforeach; ?></p>
        <div class="table-wrap">
          <table class="table table-compact">
            <thead><tr><th><span class="sr-only">Incluir</span></th><th>Activo</th><th>Por qué</th><th class="num">Peso %</th></tr></thead>
            <tbody>
            <?php foreach ($pr['allocation'] as $row): ?>
              <tr>
                <td><input type="checkbox" checked data-include aria-label="Incluir <?= e($row['symbol']) ?>" style="accent-color:var(--brand)"></td>
                <td><a href="<?= e(app_url('/mercados/instrumento/' . $row['instrument_id'])) ?>"><span class="sym"><?= e($row['symbol']) ?></span></a><span class="sym-name"><?= e($row['role']) ?> · <?= e($row['category']) ?></span></td>
                <td class="muted" style="max-width:360px"><?= e($row['reason']) ?></td>
                <td class="num"><input class="input input-num weight-input" name="weights[<?= (int) $row['instrument_id'] ?>]" value="<?= e(num($row['weight'], 1)) ?>" inputmode="decimal" aria-label="Peso de <?= e($row['symbol']) ?>"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td></td><td colspan="2">Total (lo que falte hasta 100% queda en efectivo)</td><td class="num" data-weight-total>100%</td></tr></tfoot>
          </table>
        </div>
        <div class="form-grid" style="margin-top:var(--s4)">
          <div class="field"><label for="n-<?= (int) $pr['id'] ?>">Nombre de la cartera</label><input class="input" id="n-<?= (int) $pr['id'] ?>" name="name" value="<?= e($pr['name']) ?>" maxlength="80" required></div>
          <div class="field"><label for="c-<?= (int) $pr['id'] ?>">Capital (<?= e($p['currency']) ?>)</label><input class="input input-num" id="c-<?= (int) $pr['id'] ?>" name="capital" value="<?= e(num($p['capital'], 2)) ?>" inputmode="decimal" required></div>
        </div>
        <div class="row-between">
          <span class="faint">La cartera se crea con este capital virtual y las compras se hacen a precios de mercado de hoy.</span>
          <button class="btn btn-primary" type="submit" data-loading-text="Creando cartera…">Crear cartera virtual con esta propuesta</button>
        </div>
      </form>
      <aside class="proposal-side">
        <dl class="kv">
          <dt>Volatilidad histórica</dt><dd><?= $pr['expected_volatility'] !== null ? e(pct($pr['expected_volatility'] * 100, 1, false)) : '—' ?></dd>
          <dt>Rentabilidad histórica anual</dt><dd class="<?= trend_class($pr['expected_return']) ?>"><?= $pr['expected_return'] !== null ? e(pct($pr['expected_return'] * 100, 1)) : '—' ?></dd>
          <dt>Peor caída del período</dt><dd class="down"><?= isset($pr['max_drawdown']) && $pr['max_drawdown'] !== null ? e(pct($pr['max_drawdown'] * 100, 1)) : '—' ?></dd>
        </dl>
        <p class="disclaimer">Medido con la composición propuesta <?= !empty($pr['stats_window']) ? 'entre ' . e($pr['stats_window']) : '' ?>. El pasado no anticipa el futuro.</p>
        <div><h4>Por qué esta combinación</h4><p class="muted" style="font-size:var(--fs-md);margin:4px 0 0"><?= e($a['why'] ?? '') ?></p></div>
        <div><h4>Horizonte</h4><p class="muted" style="font-size:var(--fs-md);margin:4px 0 0"><?= e($a['horizon'] ?? '') ?></p></div>
        <div><h4>Ventajas</h4><ul><?php foreach (($a['advantages'] ?? []) as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div>
        <div><h4>Riesgos y limitaciones</h4><ul><?php foreach (($a['risks'] ?? []) as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div>
        <?php if (!empty($a['comparison'])): ?><div><h4>Frente a las otras propuestas</h4><ul><?php foreach ($a['comparison'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      </aside>
    </div>
  </section>
<?php endforeach; ?>
