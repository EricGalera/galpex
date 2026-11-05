/* GALPEX · Informes: gráficos de rentabilidad mensual y evolución, impresión a PDF */
(function () {
  'use strict';
  const G = window.Galpex;
  const monthly = document.querySelector('[data-monthly-chart]');
  if (monthly) {
    const data = JSON.parse(monthly.dataset.monthlyChart);
    if (data.length) G.barChart(monthly, data.map((d) => d.p), data.map((d) => d.r), { signed: true, format: (v) => G.pct(v, 2) });
  }
  const capital = document.querySelector('[data-capital-chart]');
  if (capital) {
    const pts = JSON.parse(capital.dataset.capitalChart);
    const cur = capital.dataset.currency;
    if (pts.length > 1) {
      G.lineChart(capital, [
        { label: 'Patrimonio', data: pts.map((p) => ({ x: p.d, y: p.v })), fill: true },
        { label: 'Aportes netos', data: pts.map((p) => ({ x: p.d, y: p.c })), dashed: true, color: G.colors().text3, stepped: true, width: 1 },
      ], { format: (v) => G.money(v, cur, 2), axisFormat: (v) => G.money(v, cur, 0) });
    }
  }
  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  if (document.body.classList.contains('print-page')) {
    if (window.Chart) Chart.defaults.animation = false;
    setTimeout(() => window.print(), 900);
  }
})();
