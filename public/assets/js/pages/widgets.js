/* ==========================================================================
   GALPEX · Widgets reutilizables: evolución patrimonial y distribución
   Se inicializan solos sobre [data-equity-chart] y [data-allocation].
   ========================================================================== */
(function () {
  'use strict';
  const G = window.Galpex;

  /* ---------- Evolución patrimonial ---------- */
  document.querySelectorAll('[data-equity-chart]').forEach((panel) => {
    const canvas = panel.querySelector('canvas');
    const box = canvas.parentElement;
    const summary = panel.querySelector('[data-chart-summary]');
    const currency = panel.dataset.currency || 'USD';
    let chart = null;

    async function load(range) {
      G.chartState(box, 'Cargando…');
      panel.querySelectorAll('[data-range]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.range === range)));
      try {
        const qs = new URLSearchParams({ rango: range });
        if (panel.dataset.portfolio) qs.set('cartera', panel.dataset.portfolio);
        const data = await G.api(panel.dataset.endpoint + '?' + qs.toString());
        if (!data.points.length) { G.chartState(box, 'Todavía no hay historia para este período.'); if (chart) { chart.destroy(); chart = null; } return; }
        G.chartState(box, '');
        const series = [
          { label: 'Patrimonio', data: data.points.map((p) => ({ x: p.date, y: p.value })), fill: true },
          { label: 'Aportes netos', data: data.points.map((p) => ({ x: p.date, y: p.contributions })), dashed: true, color: G.colors().text3, stepped: true, width: 1 },
        ];
        if (chart) chart.destroy();
        chart = G.lineChart(canvas, series, { format: (v) => G.money(v, currency, 2), axisFormat: (v) => G.money(v, currency, 0) });
        if (summary) {
          const s = data.summary;
          summary.innerHTML = `<span class="muted">Rentabilidad del período (TWR)</span> <strong class="${G.trend(s.twr)}">${G.pct(s.twr * 100)}</strong>
            <span class="muted">Variación</span> <strong class="${G.trend(s.change)}">${G.esc(G.signedMoney(s.change, currency))}</strong>
            <span class="muted">Máx. caída</span> <strong class="down">${G.pct(s.max_drawdown * 100)}</strong>`;
        }
      } catch (e) {
        G.chartState(box, 'No se pudo cargar la evolución: ' + e.message);
      }
    }
    panel.querySelectorAll('[data-range]').forEach((b) => b.addEventListener('click', () => load(b.dataset.range)));
    load(panel.querySelector('[data-range][aria-pressed="true"]')?.dataset.range || '3M');
  });

  /* ---------- Distribución (dona + leyenda + barras + detalle) ---------- */
  document.querySelectorAll('[data-allocation]').forEach((panel) => {
    const data = JSON.parse(panel.dataset.allocation);
    const canvas = panel.querySelector('canvas');
    const legend = panel.querySelector('[data-legend]');
    const detail = panel.querySelector('[data-alloc-detail]');
    const bars = panel.querySelector('[data-alloc-bars]');
    const currency = panel.dataset.currency || 'USD';
    let chart = null;
    let dim = Object.keys(data)[0];

    function select(i) {
      const items = data[dim];
      legend.querySelectorAll('.legend-item').forEach((el, k) => el.classList.toggle('is-active', k === i));
      if (detail && items[i]) {
        const it = items[i];
        detail.innerHTML = `<strong>${G.esc(it.label)}</strong>: ${G.pct(it.weight, 1, false)} (${G.esc(G.money(it.value, currency, 0))}). Incluye: ${it.items.map(G.esc).join(', ')}.`;
      }
      if (bars) bars.querySelectorAll('.bar-row').forEach((el, k) => el.classList.toggle('is-dim', k !== i));
    }

    function draw() {
      const items = data[dim] || [];
      const colors = G.colors().series;
      if (chart) chart.destroy();
      if (!items.length) { legend.innerHTML = '<p class="muted">Sin datos.</p>'; return; }
      chart = G.donutChart(canvas, items, { onSelect: select });
      legend.innerHTML = items.slice(0, 9).map((it, k) => `
        <button type="button" class="legend-item" data-i="${k}">
          <span class="legend-swatch" style="background:${colors[k % colors.length]}"></span>
          <span class="ellipsis">${G.esc(it.label)}</span>
          <span class="num">${G.pct(it.weight, 1, false)}</span>
        </button>`).join('') + (items.length > 9 ? `<span class="faint">y ${items.length - 9} más</span>` : '');
      legend.querySelectorAll('.legend-item').forEach((b) => b.addEventListener('click', () => select(+b.dataset.i)));
      if (bars) {
        bars.innerHTML = items.map((it, k) => `
          <div class="bar-row" style="--c:${colors[k % colors.length]}">
            <span class="ellipsis">${G.esc(it.label)}</span>
            <span class="bar-track"><span class="bar-fill" style="width:${Math.max(0.5, it.weight).toFixed(2)}%"></span></span>
            <span class="num">${G.pct(it.weight, 1, false)}</span>
          </div>`).join('');
        bars.querySelectorAll('.bar-row').forEach((el, k) => el.addEventListener('click', () => select(k)));
      }
      if (detail) detail.textContent = 'Elegí un sector del gráfico para ver qué activos lo componen.';
    }

    panel.querySelectorAll('[data-dim]').forEach((b) => b.addEventListener('click', () => {
      dim = b.dataset.dim;
      panel.querySelectorAll('[data-dim]').forEach((o) => o.setAttribute('aria-pressed', String(o === b)));
      draw();
    }));
    document.addEventListener('galpex:theme', draw);
    draw();
  });
})();
