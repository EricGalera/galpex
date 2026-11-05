<?php $exportBase = fn ($tipo) => app_url('/informes/exportar', $query + ['tipo' => $tipo]); ?>
<div class="page-head">
  <div>
    <h1>Informes</h1>
    <p class="sub">Rentabilidad, resultados, comisiones y movimientos en <?= e($r['currency']) ?>. Exportables a CSV y PDF.</p>
  </div>
  <div class="page-head-actions">
    <div class="dropdown" data-dropdown>
      <button class="btn" type="button" aria-haspopup="true"><?= icon('download') ?>Exportar CSV</button>
      <div class="popover popover-right menu" data-dropdown-panel hidden>
        <a href="<?= e($exportBase('movimientos')) ?>">Historial de movimientos</a>
        <a href="<?= e($exportBase('activos')) ?>">Rentabilidad por activo</a>
        <a href="<?= e($exportBase('mensual')) ?>">Rentabilidad mensual</a>
      </div>
    </div>
    <a class="btn btn-primary" href="<?= e(app_url('/informes/imprimir', $query)) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>Descargar PDF</a>
  </div>
</div>
<form class="panel table-tools" method="get" action="<?= e(app_url('/informes')) ?>">
  <select class="select" name="cartera" aria-label="Cartera" style="max-width:240px">
    <option value="">Todas las carteras</option>
    <?php foreach ($portfolios as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($query['cartera'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?><?= (int) $p['is_archived'] ? ' (archivada)' : '' ?></option><?php endforeach; ?>
  </select>
  <label class="faint" for="r-from">Desde</label><input class="input" type="date" id="r-from" name="desde" value="<?= e($query['desde'] ?? $r['from']) ?>" style="max-width:170px">
  <label class="faint" for="r-to">Hasta</label><input class="input" type="date" id="r-to" name="hasta" value="<?= e($query['hasta'] ?? $r['to']) ?>" style="max-width:170px">
  <button class="btn btn-sm" type="submit">Aplicar</button>
</form>
<?= App\Http\View::partial('reports/_body', ['r' => $r]) ?>
