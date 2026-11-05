/* GALPEX · Propuestas del asesor: edición de pesos e inclusión de activos */
(function () {
  'use strict';
  const G = window.Galpex;
  document.querySelectorAll('[data-proposal-form]').forEach((form) => {
    const total = form.querySelector('[data-weight-total]');
    const rows = Array.from(form.querySelectorAll('tbody tr'));
    const recalc = () => {
      let sum = 0;
      rows.forEach((r) => {
        const on = r.querySelector('[data-include]').checked;
        const input = r.querySelector('input[name^="weights"]');
        input.disabled = !on;
        r.classList.toggle('faint', !on);
        if (on) sum += G.parseNum(input.value) || 0;
      });
      total.textContent = G.pct(sum, 1, false) + (sum < 99.95 ? ` (efectivo ${G.pct(100 - sum, 1, false)})` : '');
      total.classList.toggle('down', sum > 100.01);
      form.querySelector('[type="submit"]').disabled = sum > 100.01 || sum <= 0;
    };
    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    recalc();
  });
})();
