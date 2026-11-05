/* ==========================================================================
   GALPEX · Gráficos (Chart.js con el estilo de la marca)
   Lee los colores de las variables CSS para respetar el tema claro/oscuro.
   ========================================================================== */
(function () {
  'use strict';
  const G = window.Galpex;
  if (!window.Chart) return;

  const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  G.colors = () => ({
    text: css('--text'), text2: css('--text-2'), text3: css('--text-3'), line: css('--line'),
    brand: css('--brand'), up: css('--up'), down: css('--down'), bg: css('--bg-raised'),
    series: ['--c1', '--c2', '--c3', '--c4', '--c5', '--c6', '--c7', '--c8'].map(css),
  });

  function applyDefaults() {
    const c = G.colors();
    Chart.defaults.font.family = css('--font');
    Chart.defaults.font.size = 16;
    Chart.defaults.color = c.text3;
    Chart.defaults.borderColor = c.line;
    Chart.defaults.animation.duration = 350;
    Chart.defaults.plugins.legend.display = false;
    Object.assign(Chart.defaults.plugins.tooltip, {
      backgroundColor: c.bg, titleColor: c.text, bodyColor: c.text2, borderColor: c.line, borderWidth: 1,
      padding: 10, cornerRadius: 4, displayColors: true, boxWidth: 8, boxHeight: 8, usePointStyle: true,
    });
  }
  applyDefaults();

  /* Línea vertical que sigue al cursor (crosshair) */
  const crosshair = {
    id: 'crosshair',
    afterDatasetsDraw(chart) {
      const a = chart.tooltip?.getActiveElements?.();
      if (!a || !a.length) return;
      const x = a[0].element.x;
      const { top, bottom } = chart.chartArea;
      const ctx = chart.ctx;
      ctx.save();
      ctx.strokeStyle = G.colors().text3;
      ctx.setLineDash([3, 3]);
      ctx.beginPath(); ctx.moveTo(x, top); ctx.lineTo(x, bottom); ctx.stroke();
      ctx.restore();
    },
  };
  Chart.register(crosshair);

  const charts = new Set();
  G.registerChart = (ch) => { charts.add(ch); return ch; };
  document.addEventListener('galpex:theme', () => {
    applyDefaults();
    charts.forEach((ch) => { if (ch.$restyle) ch.$restyle(); ch.update('none'); });
  });

  const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  G.shortDate = (iso) => { const [y, m, d] = iso.split('-').map(Number); return `${d} ${months[m - 1]}`; };
  G.longDate = (iso) => { const [y, m, d] = iso.split('-').map(Number); return `${d} ${months[m - 1]} ${y}`; };

  function areaGradient(ctx, area, color) {
    const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
    g.addColorStop(0, color + '38');
    g.addColorStop(1, color + '00');
    return g;
  }

  /**
   * Gráfico de línea/área temporal.
   * series: [{label, data:[{x:'2026-01-01', y:123}], color?, dashed?, fill?}]
   */
  G.lineChart = function (canvas, series, opts = {}) {
    const c = G.colors();
    const fmt = opts.format || ((v) => G.num(v));
    const ch = new Chart(canvas, {
      type: 'line',
      data: {
        datasets: series.map((s, i) => ({
          label: s.label,
          data: s.data,
          parsing: { xAxisKey: 'x', yAxisKey: 'y' },
          borderColor: s.color || (i === 0 ? c.brand : c.series[i % c.series.length]),
          borderWidth: s.width || (i === 0 ? 2 : 1.5),
          borderDash: s.dashed ? [4, 4] : [],
          pointRadius: 0, pointHoverRadius: 4, tension: 0.15,
          fill: s.fill ? { target: 'origin' } : false,
          backgroundColor: s.fill ? (ctx) => (ctx.chart.chartArea ? areaGradient(ctx.chart.ctx, ctx.chart.chartArea, s.color || c.brand) : 'transparent') : 'transparent',
          stepped: s.stepped || false,
        })),
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { type: 'category', labels: series[0].data.map((p) => p.x), grid: { display: false },
            ticks: { maxTicksLimit: canvas.parentElement.clientWidth < 520 ? 4 : 7, maxRotation: 0, autoSkipPadding: 12, callback(v) { const l = this.getLabelForValue(v); return l ? G.shortDate(l) : ''; } } },
          y: { position: 'right', grid: { color: c.line }, border: { display: false }, ticks: { maxTicksLimit: 6, callback: (v) => opts.axisFormat ? opts.axisFormat(v) : G.num(v, Math.abs(v) >= 1000 ? 0 : 2) } },
        },
        plugins: {
          legend: { display: series.length > 1, position: 'top', align: 'start', labels: { boxWidth: 10, boxHeight: 2, color: c.text2 } },
          tooltip: { callbacks: { title: (items) => G.longDate(items[0].label), label: (it) => ` ${it.dataset.label}: ${fmt(it.parsed.y)}` } },
        },
      },
    });
    ch.$restyle = () => {
      const k = G.colors();
      ch.options.scales.y.grid.color = k.line;
      ch.data.datasets.forEach((d, i) => { if (!series[i].color) d.borderColor = i === 0 ? k.brand : k.series[i % k.series.length]; });
    };
    return G.registerChart(ch);
  };

  /** Dona con selección de segmentos. items: [{label, value, weight}] */
  G.donutChart = function (canvas, items, opts = {}) {
    const c = G.colors();
    const ch = new Chart(canvas, {
      type: 'doughnut',
      data: { labels: items.map((i) => i.label), datasets: [{ data: items.map((i) => i.value), backgroundColor: items.map((_, k) => c.series[k % c.series.length]), borderColor: c.bg, borderWidth: 2, hoverOffset: 6 }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '68%',
        plugins: { tooltip: { callbacks: { label: (it) => ` ${it.label}: ${G.pct(items[it.dataIndex].weight, 1, false)}` } } },
        onClick: (ev, els) => { if (els.length && opts.onSelect) opts.onSelect(els[0].index); },
      },
    });
    ch.$restyle = () => { const k = G.colors(); ch.data.datasets[0].borderColor = k.bg; ch.data.datasets[0].backgroundColor = items.map((_, i) => k.series[i % k.series.length]); };
    return G.registerChart(ch);
  };

  /** Barras (verticales u horizontales). */
  G.barChart = function (canvas, labels, values, opts = {}) {
    const c = G.colors();
    const colors = values.map((v) => (opts.signed ? (v >= 0 ? c.up : c.down) : c.brand));
    const ch = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets: [{ data: values, backgroundColor: colors, borderRadius: 1, maxBarThickness: 26 }] },
      options: {
        responsive: true, maintainAspectRatio: false, indexAxis: opts.horizontal ? 'y' : 'x',
        scales: {
          x: { grid: { display: !!opts.horizontal, color: c.line }, ticks: { callback: opts.horizontal ? (v) => (opts.format || G.num)(v) : undefined } },
          y: { grid: { display: !opts.horizontal, color: c.line }, border: { display: false }, ticks: { callback: opts.horizontal ? undefined : (v) => (opts.format || G.num)(v) } },
        },
        plugins: { tooltip: { callbacks: { label: (it) => ' ' + (opts.format || G.num)(it.parsed[opts.horizontal ? 'x' : 'y']) } } },
      },
    });
    return G.registerChart(ch);
  };

  /** Estado del contenedor del gráfico: cargando, vacío o error. */
  G.chartState = function (box, text) {
    const s = box.querySelector('[data-chart-state]');
    if (!s) return;
    s.textContent = text || '';
    s.hidden = !text;
  };
})();
