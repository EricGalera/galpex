/* GALPEX · Ficha de instrumento: gráfico histórico con rangos y comparación base 100 */
(function () {
  'use strict';
  const G = window.Galpex;
  const panel = document.querySelector('[data-instrument-chart]');
  if (!panel) return;
  const canvas = panel.querySelector('canvas');
  const box = canvas.parentElement;
  const summary = panel.querySelector('[data-chart-summary]');
  const currency = panel.dataset.currency;
  const compare = panel.querySelector('[data-compare]');
  let chart = null;
  let range = '1A';

  async function load() {
    G.chartState(box, 'Cargando…');
    panel.querySelectorAll('[data-range]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.range === range)));
    try {
      const qs = new URLSearchParams({ rango: range });
      if (compare.value) qs.set('vs', compare.value);
      const data = await G.api(panel.dataset.endpoint + '?' + qs);
      if (data.bars.length < 2) { G.chartState(box, 'No hay historia disponible para este período.'); return; }
      G.chartState(box, '');
      const bars = data.bars;
      const first = bars[0].close;
      const last = bars[bars.length - 1].close;
      let series;
      if (data.compare && data.compare.points.length > 1) {
        const map = Object.fromEntries(data.compare.points.map((p) => [p.date, p.close]));
        const base = data.compare.points[0].close;
        let lastVal = base;
        series = [
          { label: panel.dataset.symbol, data: bars.map((b) => ({ x: b.date, y: (b.close / first) * 100 })), fill: true },
          { label: data.compare.symbol, data: bars.map((b) => { if (map[b.date] !== undefined) lastVal = map[b.date]; return { x: b.date, y: (lastVal / base) * 100 }; }), color: G.colors().series[1] },
        ];
        if (chart) chart.destroy();
        chart = G.lineChart(canvas, series, { format: (v) => G.num(v, 2) + ' (base 100)' });
      } else {
        series = [{ label: panel.dataset.symbol, data: bars.map((b) => ({ x: b.date, y: b.close })), fill: true, color: last >= first ? G.colors().up : G.colors().down }];
        if (chart) chart.destroy();
        chart = G.lineChart(canvas, series, { format: (v) => G.money(v, currency) });
      }
      const change = (last / first - 1) * 100;
      const highs = bars.map((b) => b.high ?? b.close), lows = bars.map((b) => b.low ?? b.close);
      summary.innerHTML = `<span class="muted">Variación del período</span> <strong class="${G.trend(change)}">${G.pct(change)}</strong>
        <span class="muted">Máximo</span> <strong>${G.num(Math.max(...highs))}</strong>
        <span class="muted">Mínimo</span> <strong>${G.num(Math.min(...lows))}</strong>
`;
    } catch (e) { G.chartState(box, 'No se pudo cargar el historial: ' + e.message); }
  }
  panel.querySelectorAll('[data-range]').forEach((b) => b.addEventListener('click', () => { range = b.dataset.range; load(); }));
  compare.addEventListener('change', load);
  load();
})();

/* GALPEX · "¿Cuánto tendrías hoy?": cuenta con la historia real de precios */
(function () {
  'use strict';
  const G = window.Galpex;
  const panel = document.querySelector('[data-whatif]');
  if (!panel) return;
  const form = panel.querySelector('[data-whatif-form]');
  const out = panel.querySelector('[data-whatif-result]');
  const currency = panel.dataset.currency;
  const symbol = panel.dataset.symbol;
  const labels = { '1M': '1 mes', '6M': '6 meses', '1A': '1 año', '3A': '3 años', '5A': '5 años' };

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const amount = G.parseNum(form.amount.value);
    if (!(amount > 0)) { out.hidden = false; out.innerHTML = '<span class="down">Ingresá un monto mayor que cero.</span>'; return; }
    const when = form.when.value;
    const qs = new URLSearchParams({ rango: when });
    if (form.vs.value) qs.set('vs', form.vs.value);
    out.hidden = false;
    out.textContent = 'Calculando…';
    try {
      const data = await G.api(panel.dataset.endpoint + '?' + qs);
      const bars = data.bars.filter((b) => b.close > 0);
      if (bars.length < 2) { out.textContent = 'No hay historia de precios suficiente para ese período.'; return; }
      const start = bars[0], end = bars[bars.length - 1];
      const value = amount / start.close * end.close;
      const change = (end.close / start.close - 1) * 100;
      const since = new Date(start.date + 'T12:00:00').toLocaleDateString('es-AR', { day: 'numeric', month: 'long', year: 'numeric' });
      let html = `Si el ${G.esc(since)} hubieras puesto <strong>${G.esc(G.money(amount, currency, 2))}</strong> en ${G.esc(symbol)}, hoy tendrías
        <strong class="${G.trend(change)}">${G.esc(G.money(value, currency, 2))}</strong> (${G.esc(G.pct(change, 1))} en ${labels[when] || when}).`;
      if (data.compare && data.compare.points.length > 1) {
        const pts = data.compare.points;
        const c = (pts[pts.length - 1].close / pts[0].close - 1) * 100;
        html += `<br><span class="muted">En el mismo período, ${G.esc(data.compare.symbol)} varió <span class="${G.trend(c)}">${G.esc(G.pct(c, 1))}</span>: ${change > c ? G.esc(symbol) + ' le ganó por ' + G.esc(G.num(change - c, 1)) + ' puntos' : G.esc(symbol) + ' quedó ' + G.esc(G.num(c - change, 1)) + ' puntos abajo'}.</span>`;
      }
      out.innerHTML = html;
    } catch (e) {
      out.textContent = 'No se pudo calcular: ' + e.message;
    }
  });
})();
