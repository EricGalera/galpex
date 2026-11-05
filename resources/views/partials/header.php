<?php
$nav = [
    ['/inicio', 'home', 'Inicio'],
    ['/mercados', 'markets', 'Mercados'],
    ['/carteras', 'portfolio', 'Carteras'],
    ['/asesor', 'advisor', 'Asesor'],
    ['/riesgo', 'risk', 'Riesgo'],
    ['/cripto', 'crypto', 'Cripto'],
    ['/noticias', 'news', 'Noticias'],
    ['/informes', 'reports', 'Informes'],
    ['/diario', 'journal', 'Diario'],
    ['/alertas', 'bell', 'Alertas'],
];
$initials = App\Models\User::initials((string) ($authUser['name'] ?? ''));
$isAdmin = ($authUser['role'] ?? '') === 'admin';
?>
<header class="header">
  <div class="header-inner">
    <button class="btn btn-ghost btn-icon menu-toggle" type="button" data-toggle-sidebar aria-controls="sidebar" aria-label="Abrir menú"><?= icon('menu') ?></button>
    <a class="brand" href="<?= e(app_url('/inicio')) ?>" aria-label="GALPEX, ir al inicio">
      <svg class="brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M24 7H7v18h17v-8" stroke="currentColor" stroke-width="3.6" stroke-linecap="square"/><path d="M15 17h15" stroke="#6fb0ff" stroke-width="3.6" stroke-linecap="square"/></svg>
      <span class="brand-word">GALPEX</span>
    </a>
    <nav class="mainnav" id="sidebar" aria-label="Principal">
      <?php foreach ($nav as [$href, $ic, $label]): ?>
        <a href="<?= e(app_url($href)) ?>" <?= is_active_nav($href) ? 'aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($label) ?></span>
          <?php if ($href === '/alertas' && ($unreadCount ?? 0) > 0): ?><span class="badge badge-count"><?= (int) $unreadCount ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="mainnav-more">
        <a href="<?= e(app_url('/configuracion')) ?>" <?= is_active_nav('/configuracion') ? 'aria-current="page"' : '' ?>><?= icon('settings') ?><span>Configuración</span></a>
        <?php if ($isAdmin): ?><a href="<?= e(app_url('/admin')) ?>" <?= is_active_nav('/admin') ? 'aria-current="page"' : '' ?>><?= icon('admin') ?><span>Administración</span></a><?php endif; ?>
      </div>
    </nav>
    <div class="header-tools">
      <div class="topbar-search" data-global-search>
        <span class="search-icon"><?= icon('search', 'icon icon-sm') ?></span>
        <label class="sr-only" for="global-search">Buscar instrumentos</label>
        <input id="global-search" class="input search-input" type="search" autocomplete="off" placeholder="Buscar especie: GGAL, AL30, AAPL…" aria-controls="search-results" aria-expanded="false">
        <span class="kbd">/</span>
        <div class="popover search-results" id="search-results" role="listbox" hidden></div>
      </div>
      <div class="dropdown" data-dropdown>
        <button class="btn btn-ghost btn-icon icon-btn" type="button" data-notifications aria-label="Notificaciones" aria-haspopup="true">
          <?= icon('bell') ?>
          <span class="badge badge-count" data-unread <?= ($unreadCount ?? 0) > 0 ? '' : 'hidden' ?>><?= (int) ($unreadCount ?? 0) ?></span>
        </button>
        <div class="popover popover-right" data-dropdown-panel hidden>
          <div class="popover-head">Notificaciones <a class="link" href="<?= e(app_url('/alertas')) ?>">Ver todas</a></div>
          <div data-notification-list><p class="empty">Cargando…</p></div>
        </div>
      </div>
      <div class="dropdown" data-dropdown>
        <button class="btn btn-ghost" type="button" aria-haspopup="true" aria-label="Menú de usuario">
          <span class="avatar"><?= e($initials) ?></span>
        </button>
        <div class="popover popover-right menu" data-dropdown-panel hidden>
          <div class="popover-head"><span><?= e($authUser['name'] ?? '') ?><br><small class="faint"><?= e($authUser['email'] ?? '') ?></small></span></div>
          <a href="<?= e(app_url('/configuracion')) ?>"><?= icon('settings') ?>Configuración</a>
          <?php if ($isAdmin): ?><a href="<?= e(app_url('/admin')) ?>"><?= icon('admin') ?>Administración</a><?php endif; ?>
          <button type="button" data-theme-toggle><?= icon(($authUser['theme'] ?? 'light') === 'dark' ? 'sun' : 'moon') ?><span data-theme-label><?= ($authUser['theme'] ?? 'light') === 'dark' ? 'Tema claro' : 'Tema oscuro' ?></span></button>
          <form method="post" action="<?= e(app_url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?>Cerrar sesión</button></form>
        </div>
      </div>
    </div>
  </div>
</header>
