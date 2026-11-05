/* GALPEX · Riesgo: simulador de escenarios y comparación con benchmarks */
(function () {
  'use strict';
  const G = window.Galpex;

  const panel = document.querySelector('[data-scenarios]');
  if (panel) {
    const form = panel.querySelector('[data-scenario-form]');
    const out = panel.querySelector('[data-scenario-result]');
    const cur = panel.dataset.currency;
    const syncFields = () => {
      const t = form.type.value;
      form.querySelectorAll('[data-for]').forEach((f) => (f.hidden = f.dataset.for !== t));
      form.querySelector('[data-shock-label]').textContent = t === 'rate_hike' ? 'Magnitud (puntos básicos)' : 'Magnitud (%)';
    };
    form.type.addEventListener('change', syncFields);
    syncFields();

    async function run(body) {
      out.hidden = false;
      out.querySelector('[data-r="rows"]').innerHTML = '<tr><td colspan="6" class="muted">Calculando…</td></tr>';
      try {
        if (panel.dataset.portfolio) body.portfolio_id = panel.dataset.portfolio;
        const r = await G.api(panel.dataset.endpoint, { method: 'POST', body });
        const q = (k) => out.querySelector(`[data-r="${k}"]`);
        q('before').textContent = G.money(r.value_before, cur, 2);
        q('after').textContent = G.money(r.value_after, cur, 2);
        q('impact').innerHTML = `<span class="${G.trend(r.impact)}">${G.esc(G.signedMoney(r.impact, cur))} (${G.pct(r.impact_pct)})</span>`;
        q('rows').innerHTML = r.positions.map((p) => `<tr><td class="sym">${G.esc(p.symbol)}</td><td class="muted">${G.esc(p.category)}</td><td class="num">${G.money(p.value, cur, 2)}</td>
          <td class="num ${G.trend(p.change_pct)}">${G.pct(p.change_pct)}</td><td class="num ${G.trend(p.impact)}">${G.esc(G.signedMoney(p.impact, cur))}</td><td class="muted">${G.esc(p.reason)}</td></tr>`).join('');
        q('assumptions').innerHTML = `<li><strong>${G.esc(r.title)}</strong></li>` + r.assumptions.map((a) => `<li>${G.esc(a)}</li>`).join('');
      } catch (e) { out.querySelector('[data-r="rows"]').innerHTML = `<tr><td colspan="6" class="down">${G.esc(e.message)}</td></tr>`; }
    }
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const body = { type: form.type.value, shock: G.parseNum(form.shock.value) };
      if (body.type === 'devaluation') body.currency = form.currency.value;
      if (body.type === 'sector_drop') body.sector = form.sector.value;
      if (body.type === 'asset_drop') body.instrument_id = form.instrument_id.value;
      run(body);
    });
    panel.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => {
      const p = JSON.parse(b.dataset.preset);
      form.type.value = p.type; form.shock.value = p.shock; if (p.currency) form.currency.value = p.currency;
      syncFields();
      run(p);
    }));
  }

  const cmp = document.querySelector('[data-compare-panel]');
  if (cmp) {
    const canvas = cmp.querySelector('canvas');
    const box = canvas.parentElement;
    let chart = null;
    async function load(range) {
      cmp.querySelectorAll('[data-range]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.range === range)));
      G.chartState(box, 'Cargando…');
      try {
        const qs = new URLSearchParams({ rango: range });
        if (cmp.dataset.portfolio) qs.set('cartera', cmp.dataset.portfolio);
        const [eq, bm] = await Promise.all([
          G.api(cmp.dataset.equity + '?' + qs),
          G.api(cmp.dataset.compare + '?ids=' + cmp.dataset.benchmarks + '&rango=' + range),
        ]);
        if (eq.points.length < 2) { G.chartState(box, 'La cartera todavía no tiene historia suficiente.'); return; }
        G.chartState(box, '');
        // TWR base 100 de la cartera
        let level = 100; const pts = [];
        eq.points.forEach((p, i) => { if (i > 0 && eq.points[i - 1].value > 0) level *= (p.value - p.flow) / eq.points[i - 1].value; pts.push({ x: p.date, y: level }); });
        const dates = pts.map((p) => p.x);
        const series = [{ label: 'Cartera', data: pts, width: 2.4 }];
        bm.series.forEach((s) => {
          const map = Object.fromEntries(s.points.map((p) => [p.x, p.y]));
          let last = null, base = null;
          const data = dates.map((d) => { if (map[d] !== undefined) last = map[d]; if (last !== null && base === null) base = last; return { x: d, y: last !== null ? (last / base) * 100 : null }; });
          series.push({ label: s.symbol, data });
        });
        if (chart) chart.destroy();
        chart = G.lineChart(canvas, series, { format: (v) => G.num(v, 2) });
        const twr = pts[pts.length - 1].y / 100 - 1;
        const rets = pts.slice(1).map((p, i) => p.y / pts[i].y - 1);
        const mean = rets.reduce((a, b) => a + b, 0) / rets.length;
        const vol = Math.sqrt(rets.reduce((a, b) => a + (b - mean) ** 2, 0) / (rets.length - 1)) * Math.sqrt(365);
        let peak = -Infinity, mdd = 0; pts.forEach((p) => { peak = Math.max(peak, p.y); mdd = Math.min(mdd, p.y / peak - 1); });
        const rows = [{ name: 'Cartera', ret: twr, vol, mdd }].concat(bm.series.map((s) => ({ name: s.name || s.symbol, ret: s.return, vol: s.volatility, mdd: s.max_drawdown })));
        cmp.querySelector('[data-compare-table] tbody').innerHTML = rows.map((r) => `<tr><td class="sym">${G.esc(r.name)}</td><td class="num ${G.trend(r.ret)}">${G.pct(r.ret * 100)}</td><td class="num">${G.pct(r.vol * 100, 1, false)}</td><td class="num down">${G.pct(r.mdd * 100, 1)}</td></tr>`).join('');
      } catch (e) { G.chartState(box, 'No se pudo cargar la comparación: ' + e.message); }
    }
    cmp.querySelectorAll('[data-range]').forEach((b) => b.addEventListener('click', () => load(b.dataset.range)));
    load('1A');
  }
})();
