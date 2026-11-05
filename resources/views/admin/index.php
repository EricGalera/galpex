<div class="page-head"><div><h1>Administración</h1><p class="sub">Estado de los proveedores de datos, usuarios y registro de eventos.</p></div></div>
<div class="grid g-main-side">
  <section class="panel">
    <div class="panel-head"><h2>Proveedores de datos</h2></div>
    <div class="table-wrap"><table class="table table-compact">
      <thead><tr><th>Proveedor</th><th>Clave</th><th class="num">Límite</th><th class="num">Usadas hoy</th><th>Último éxito</th><th>Último error</th></tr></thead>
      <tbody><?php foreach ($providers as $p): ?>
        <tr><td><?= e($p['name']) ?></td>
          <td><?= !empty($configured[$p['code']]) ? '<span class="badge badge-up">Configurado</span>' : '<span class="badge badge-neutral">Sin clave</span>' ?></td>
          <td class="num muted"><?= (int) $p['requests_per_minute'] ?>/min, <?= e(compact_number($p['requests_per_day'])) ?>/día</td>
          <td class="num"><?= $p['calls_date'] === gmdate('Y-m-d') ? (int) $p['calls_today'] : 0 ?></td>
          <td class="muted"><?= e(time_ago($p['last_success_at'])) ?></td>
          <td class="muted" style="max-width:280px"><?= $p['last_error'] ? e(time_ago($p['last_error_at'])) . ': ' . e($p['last_error']) : '—' ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <div class="panel-foot">Las claves se configuran en el archivo <code>.env</code> del servidor; nunca se exponen en el navegador.</div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Datos almacenados</h2></div>
    <div class="panel-body"><dl class="kv">
      <?php foreach ($quoteStats as $q): ?><dt>Cotizaciones de <?= e($q['source']) ?></dt><dd><?= (int) $q['n'] ?></dd><?php endforeach; ?>
      <dt>Precios históricos</dt><dd><?= e(num($historyStats['rows_count'], 0)) ?></dd>
      <dt>Rango</dt><dd><?= e(short_date($historyStats['first_date'])) ?> a <?= e(short_date($historyStats['last_date'])) ?></dd>
    </dl></div>
  </section>
</div>
<section class="panel">
  <div class="panel-head"><h2>Seguridad, últimas 24 horas</h2></div>
  <div class="panel-body grid g-2">
    <dl class="kv">
      <dt>Ingresos fallidos</dt><dd><?= (int) ($security['failed'] ?? 0) ?></dd>
      <dt>Cuentas o conexiones bloqueadas</dt><dd><?= (int) ($security['locked'] ?? 0) ?></dd>
      <dt>Conexiones distintas con fallos</dt><dd><?= (int) ($security['ips'] ?? 0) ?></dd>
      <dt>Contraseñas restablecidas</dt><dd><?= (int) ($security['resets'] ?? 0) ?></dd>
      <dt>Cuentas que deben cambiar la contraseña</dt><dd><?= (int) $pendingPasswordChange ?></dd>
    </dl>
    <div>
      <p class="muted" style="margin-top:0">Conexiones con más intentos fallidos</p>
      <?php if ($topFailedIps === []): ?><p class="faint">Ninguna.</p><?php endif; ?>
      <dl class="kv">
        <?php foreach ($topFailedIps as $f): ?><dt class="mono"><?= e($f['ip'] ?? '—') ?></dt><dd><?= (int) $f['n'] ?> · <?= e(time_ago($f['last'])) ?></dd><?php endforeach; ?>
      </dl>
    </div>
  </div>
  <div class="panel-foot">Tras 5 fallos seguidos una cuenta se bloquea 15 minutos desde esa conexión (20 desde cualquier conexión y 50 por conexión). Los envíos rechazados por CSRF quedan en <code>storage/logs</code>.</div>
</section>
<div class="grid g-2">
  <section class="panel">
    <div class="panel-head"><h2>Usuarios</h2></div>
    <div class="table-wrap"><table class="table table-compact">
      <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th class="num">Carteras</th><th>Último acceso</th></tr></thead>
      <tbody><?php foreach ($users as $u): ?><tr><td><?= e($u['name']) ?></td><td class="muted"><?= e($u['email']) ?></td><td><span class="badge <?= $u['role'] === 'admin' ? 'badge-brand' : 'badge-neutral' ?>"><?= e($u['role']) ?></span></td><td class="num"><?= (int) $u['portfolios'] ?></td><td class="muted"><?= e(time_ago($u['last_login_at'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Registro de eventos</h2></div>
    <div class="table-wrap"><table class="table table-compact">
      <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>IP</th></tr></thead>
      <tbody><?php foreach ($audit as $a): ?><tr><td class="nowrap muted"><?= e(local_date($a['created_at'], 'd/m H:i')) ?></td><td class="mono" style="font-size:var(--fs-sm)"><?= e($a['event']) ?></td><td class="muted"><?= e($a['email'] ?? '—') ?></td><td class="muted"><?= e($a['ip'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
</div>
