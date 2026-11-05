/* ==========================================================================
   GALPEX · Núcleo del frontend (sin dependencias)
   - fetch con CSRF, formato es-AR, toasts
   - tema, menú lateral, menús desplegables
   - buscador global, pizarra y notificaciones con actualización periódica
   - tablas ordenables y filtrables
   ========================================================================== */
(function () {
  'use strict';

  const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
  const BASE = meta('base-url');

  /* ---------- Utilidades ---------- */
  const G = (window.Galpex = window.Galpex || {});

  // Acepta rutas relativas a la app ("/api/x") o ya completas ("/Galpex/api/x", generadas con url() en PHP).
  G.url = (path) => (BASE && (path === BASE || path.startsWith(BASE + '/')) ? path : BASE + path);
  // Solo rutas internas ("/carteras/3"): descarta "//otro-sitio", "javascript:" y similares.
  G.safePath = (path, fallback = '/alertas') => (typeof path === 'string' && /^\/(?![\/\\])[\w\-./?=&%~]*$/.test(path) ? path : fallback);

  G.api = async function (path, options = {}) {
    const opts = {
      method: options.method || 'GET',
      headers: { Accept: 'application/json', 'X-CSRF-Token': meta('csrf-token'), 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    };
    if (options.body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(options.body);
    }
    const res = await fetch(path.startsWith('http') ? path : G.url(path), opts);
    let data = null;
    try { data = await res.json(); } catch (e) { /* sin cuerpo JSON */ }
    if (!res.ok) {
      const err = new Error((data && data.error) || `Error ${res.status}`);
      err.status = res.status;
      err.errors = data && data.errors;
      throw err;
    }
    return data;
  };

  const nf = (d) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d });
  G.decimals = (v) => { const a = Math.abs(v); return a === 0 ? 2 : a < 0.01 ? 6 : a < 1 ? 4 : 2; };
  G.num = (v, d) => (v === null || v === undefined || isNaN(v) ? '—' : nf(d ?? G.decimals(v)).format(v));
  G.symbol = (c) => ({ ARS: '$', USD: 'US$', USDT: 'US$', USDC: 'US$', EUR: '€', BRL: 'R$' }[c] || c + ' ');
  G.money = (v, c = 'USD', d) => (v === null || v === undefined || isNaN(v) ? '—' : (v < 0 ? '−' : '') + G.symbol(c) + ' ' + G.num(Math.abs(v), d ?? G.decimals(v)));
  G.signedMoney = (v, c = 'USD') => (v > 0 ? '+' : v < 0 ? '−' : '') + G.symbol(c) + ' ' + G.num(Math.abs(v), 2);
  G.pct = (v, d = 2, signed = true) => (v === null || v === undefined || isNaN(v) ? '—' : (signed ? (v > 0 ? '+' : v < 0 ? '−' : '') : v < 0 ? '−' : '') + G.num(Math.abs(v), d) + '%');
  G.trend = (v) => (v > 0 ? 'up' : v < 0 ? 'down' : 'flat');
  G.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // Formato argentino: "1.234,56" y "25.000" (miles); también acepta "0.5".
  G.parseNum = (s) => {
    s = String(s ?? '').trim().replace(/\s/g, '');
    if (!s.includes(',') && /^-?[1-9]\d{0,2}(\.\d{3})+$/.test(s)) return parseFloat(s.replace(/\./g, ''));
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    return parseFloat(s);
  };
  G.debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  G.toast = function (message, type = 'ok') {
    const zone = document.getElementById('toasts');
    if (!zone) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'error' ? 'is-error' : 'is-ok');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.textContent = message;
    zone.appendChild(el);
    setTimeout(() => el.remove(), 5000);
  };

  /* ---------- Tema claro / oscuro ---------- */
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const root = document.documentElement;
      const next = root.dataset.theme === 'light' ? 'dark' : 'light';
      root.dataset.theme = next;
      btn.querySelector('use')?.setAttribute('href', btn.querySelector('use').getAttribute('href').replace(/#i-\w+/, next === 'dark' ? '#i-sun' : '#i-moon'));
      const label = btn.querySelector('[data-theme-label]');
      if (label) label.textContent = next === 'dark' ? 'Tema claro' : 'Tema oscuro';
      document.dispatchEvent(new CustomEvent('galpex:theme'));
      try { await G.api('/api/preferencias/tema', { method: 'POST', body: { theme: next } }); } catch (e) { /* se mantiene local */ }
    });
  });

  /* ---------- Menú de navegación en pantallas chicas ---------- */
  const sidebar = document.getElementById('sidebar');
  document.querySelectorAll('[data-toggle-sidebar]').forEach((b) => b.addEventListener('click', (ev) => {
    ev.stopPropagation();
    sidebar?.classList.toggle('is-open');
  }));
  document.addEventListener('click', (ev) => {
    if (sidebar?.classList.contains('is-open') && !sidebar.contains(ev.target)) sidebar.classList.remove('is-open');
  });

  /* ---------- Menús desplegables ---------- */
  document.querySelectorAll('[data-dropdown]').forEach((dd) => {
    const btn = dd.querySelector('button');
    const panel = dd.querySelector('[data-dropdown-panel]');
    btn.addEventListener('click', (ev) => {
      ev.stopPropagation();
      const open = panel.hidden;
      document.querySelectorAll('[data-dropdown-panel]').forEach((p) => (p.hidden = true));
      panel.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
      if (open) dd.dispatchEvent(new CustomEvent('galpex:open'));
    });
  });
  document.addEventListener('click', (ev) => {
    document.querySelectorAll('[data-dropdown-panel]').forEach((p) => { if (!p.parentElement.contains(ev.target)) p.hidden = true; });
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') document.querySelectorAll('[data-dropdown-panel], .search-results').forEach((p) => (p.hidden = true));
  });

  /* ---------- Filas que funcionan como enlace ---------- */
  document.addEventListener('click', (ev) => {
    const row = ev.target.closest('tr[data-href]');
    if (row && !ev.target.closest('a, button, input, select, form')) window.location.href = row.dataset.href;
  });

  /* ---------- Formularios que se envían al cambiar ---------- */
  document.querySelectorAll('form[data-autosubmit]').forEach((f) => f.addEventListener('change', () => f.submit()));

  /* ---------- Confirmación y estado de envío ---------- */
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    const msg = form.dataset.confirm;
    if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
    const btn = form.querySelector('button[type="submit"][data-loading-text]');
    if (btn) { btn.classList.add('is-loading'); btn.textContent = btn.dataset.loadingText; }
  });

  /* ---------- Buscador con sugerencias por ticker ----------
     Cualquier contenedor [data-global-search] con un <input> y un .search-results
     muestra sugerencias mientras se escribe: primero el ticker exacto, después los
     que empiezan con lo tipeado y por último los que lo contienen en el nombre. */
  G.tickerSearch = function (wrap) {
    const input = wrap.querySelector('input');
    const box = wrap.querySelector('.search-results');
    const form = input.form;
    let items = [];
    let active = -1;
    let query = '';
    // Resalta lo tipeado. Se escapa cada tramo por separado para que el HTML nunca se arme con datos sin escapar.
    const mark = (raw) => {
      const text = String(raw ?? '');
      const i = query ? text.toUpperCase().indexOf(query.toUpperCase()) : -1;
      if (i < 0) return G.esc(text);
      return G.esc(text.slice(0, i)) + '<mark>' + G.esc(text.slice(i, i + query.length)) + '</mark>' + G.esc(text.slice(i + query.length));
    };
    const go = (i) => { window.location.href = G.url('/mercados/instrumento/' + i.id); };
    const render = () => {
      if (!items.length) {
        box.innerHTML = '<p class="empty">Sin resultados para «' + G.esc(query) + '». Probá con el ticker: GGAL, AL30, AAPL, BTC.</p>';
      } else {
        box.innerHTML = items.map((i, k) => `
          <a class="result ${k === active ? 'is-active' : ''}" role="option" aria-selected="${k === active}" href="${G.url('/mercados/instrumento/' + i.id)}">
            <span class="sym">${mark(i.symbol)}</span>
            <span><span class="ellipsis">${mark(i.name)}</span><br><span class="meta">${G.esc(i.category)}, ${G.esc(i.currency)}</span></span>
            <span class="num">${i.price !== null ? G.esc(G.money(i.price, i.currency)) : '—'}<br><span class="chg ${G.trend(i.change_pct)}">${G.pct(i.change_pct)}</span></span>
          </a>`).join('')
          + `<a class="result-all" href="${G.url('/mercados')}?q=${encodeURIComponent(query)}">Ver todos los resultados de «${G.esc(query)}»</a>`;
      }
      box.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };
    let seq = 0;
    const run = G.debounce(async () => {
      query = input.value.trim();
      if (query.length < 1) { box.hidden = true; return; }
      const mine = ++seq;
      try {
        const data = await G.api('/api/buscar?q=' + encodeURIComponent(query));
        if (mine !== seq) return; // llegó una respuesta vieja
        items = data.results;
        active = -1;
        render();
      } catch (e) { /* silencioso */ }
    }, 150);
    input.addEventListener('input', run);
    input.addEventListener('focus', () => { if (items.length && input.value.trim()) box.hidden = false; });
    input.addEventListener('keydown', (ev) => {
      if (box.hidden) return;
      if (ev.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); ev.preventDefault(); }
      if (ev.key === 'ArrowUp') { active = Math.max(-1, active - 1); render(); ev.preventDefault(); }
      if (ev.key === 'Enter') {
        // Con una sugerencia marcada se abre esa especie; si no, el formulario busca en todo el catálogo
        if (active >= 0 && items[active]) { go(items[active]); ev.preventDefault(); }
        else if (!form && items[0]) { go(items[0]); ev.preventDefault(); }
      }
    });
    document.addEventListener('click', (ev) => { if (!wrap.contains(ev.target)) box.hidden = true; });
  };
  document.querySelectorAll('[data-global-search]').forEach(G.tickerSearch);
  document.addEventListener('keydown', (ev) => {
    if (ev.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
      ev.preventDefault();
      document.getElementById('global-search')?.focus();
    }
  });

  /* ---------- Franja de cotizaciones: actualización periódica ---------- */
  const tape = document.querySelector('[data-tape]');
  async function refreshTape() {
    if (!tape || document.hidden) return;
    try {
      const data = await G.api('/api/pizarra');
      data.items.forEach((i) => {
        const cell = tape.querySelector(`[data-tape-id="${i.id}"]`);
        if (!cell) return;
        const px = cell.querySelector('[data-px]');
        const chg = cell.querySelector('[data-chg]');
        const text = i.price !== null ? G.num(i.price) : '—';
        if (px.textContent !== text) {
          // Marca breve en verde o rojo cuando el precio cambia, como en una pantalla de operaciones
          const before = G.parseNum(px.textContent);
          px.textContent = text;
          px.classList.remove('tick-up', 'tick-down'); void px.offsetWidth;
          if (!isNaN(before) && i.price !== null) px.classList.add(i.price >= before ? 'tick-up' : 'tick-down');
        }
        chg.textContent = G.pct(i.change_pct);
        chg.className = G.trend(i.change_pct);
      });
      updateUnread(data.unread);
    } catch (e) { /* sin conexión: se mantiene el último valor */ }
  }
  if (tape) setInterval(refreshTape, 60000);

  /* ---------- Notificaciones ---------- */
  function updateUnread(n) {
    const b = document.querySelector('[data-unread]');
    if (!b || n === undefined) return;
    b.textContent = n;
    b.hidden = !n;
  }
  const notif = document.querySelector('[data-notifications]')?.closest('[data-dropdown]');
  if (notif) {
    notif.addEventListener('galpex:open', async () => {
      const list = notif.querySelector('[data-notification-list]');
      try {
        const data = await G.api('/api/notificaciones');
        list.innerHTML = data.items.length
          ? data.items.map((n) => `<a class="notif ${n.read_at ? '' : 'is-unread'}" href="${G.esc(G.url(G.safePath(n.link)))}"><strong>${G.esc(n.title)}</strong>${n.body ? '<br>' + G.esc(n.body) : ''}<br><time>${G.esc(n.ago)}</time></a>`).join('')
          : '<p class="empty">No hay notificaciones.</p>';
        updateUnread(data.unread);
      } catch (e) { list.innerHTML = '<p class="empty">No se pudieron cargar las notificaciones.</p>'; }
    });
  }

  /* ---------- Tablas ordenables y filtrables ---------- */
  G.enhanceTable = function (table) {
    const tbody = table.tBodies[0];
    if (!tbody) return;
    table.querySelectorAll('th[data-sort]').forEach((th, idx) => {
      th.tabIndex = 0;
      const col = Array.from(th.parentElement.children).indexOf(th);
      const sort = () => {
        const dir = th.getAttribute('aria-sort') === 'descending' ? 'ascending' : 'descending';
        table.querySelectorAll('th[data-sort]').forEach((o) => o.removeAttribute('aria-sort'));
        th.setAttribute('aria-sort', dir);
        const rows = Array.from(tbody.rows);
        const type = th.dataset.sort;
        const val = (r) => {
          const cell = r.cells[col];
          const raw = cell?.dataset.value ?? cell?.textContent.trim() ?? '';
          return type === 'num' ? parseFloat(raw) || (G.parseNum(raw.replace(/[^\d,.\-−]/g, '').replace('−', '-')) || 0) : raw.toLowerCase();
        };
        rows.sort((a, b) => { const x = val(a), y = val(b); return (x > y ? 1 : x < y ? -1 : 0) * (dir === 'ascending' ? 1 : -1); });
        rows.forEach((r) => tbody.appendChild(r));
      };
      th.addEventListener('click', sort);
      th.addEventListener('keydown', (e) => { if (e.key === 'Enter') sort(); });
    });
    const filter = document.querySelector(`[data-filter-for="${table.id}"]`);
    if (filter) {
      filter.addEventListener('input', () => {
        const q = filter.value.trim().toLowerCase();
        let shown = 0;
        Array.from(tbody.rows).forEach((r) => {
          if (r.dataset.empty) return;
          const hit = !q || r.textContent.toLowerCase().includes(q);
          r.hidden = !hit;
          if (hit) shown++;
        });
        const empty = table.parentElement.querySelector('[data-filter-empty]');
        if (empty) empty.hidden = shown > 0;
      });
    }
  };
  document.querySelectorAll('table[data-enhance]').forEach(G.enhanceTable);
})();
