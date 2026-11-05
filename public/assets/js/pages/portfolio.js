/* GALPEX · Detalle de cartera: efectivo, distribución objetivo */
(function () {
  'use strict';
  const G = window.Galpex;

  const cash = document.getElementById('cash-dialog');
  document.querySelectorAll('[data-open-cash]').forEach((b) => b.addEventListener('click', () => { cash.showModal(); cash.querySelector('[name="amount"]').focus(); }));
  cash?.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => cash.close()));

  const form = document.querySelector('[data-targets-form]');
  if (!form) return;
  const total = form.querySelector('[data-targets-total]');
  const recalc = () => {
    let sum = 0;
    form.querySelectorAll('input[name^="targets"], input[name="new_target_pct"]').forEach((i) => { const v = G.parseNum(i.value); if (!isNaN(v)) sum += v; });
    total.textContent = G.pct(sum, 2, false) + (sum < 100 ? ` (efectivo ${G.pct(100 - sum, 2, false)})` : '');
    total.classList.toggle('down', sum > 100.0001);
  };
  form.addEventListener('input', recalc);
  recalc();

  const picker = form.querySelector('[data-instrument-picker-inline]');
  if (picker) {
    const input = picker.querySelector('input[type="search"]');
    const box = picker.querySelector('.search-results');
    const hidden = picker.querySelector('[name="new_instrument_id"]');
    input.addEventListener('input', G.debounce(async () => {
      hidden.value = '';
      const q = input.value.trim();
      if (!q) { box.hidden = true; return; }
      const data = await G.api('/api/buscar?q=' + encodeURIComponent(q));
      box.innerHTML = data.results.filter((i) => i.category !== 'Índices bursátiles').map((i) =>
        `<button type="button" class="result" data-id="${i.id}" data-label="${G.esc(i.symbol + ' · ' + i.name)}" style="width:100%;border:0;background:none;color:inherit;text-align:left"><span class="sym">${G.esc(i.symbol)}</span><span class="ellipsis">${G.esc(i.name)}</span><span class="meta">${G.esc(i.market)}</span></button>`).join('') || '<p class="empty">Sin resultados.</p>';
      box.hidden = false;
      box.querySelectorAll('[data-id]').forEach((b) => b.addEventListener('click', () => { hidden.value = b.dataset.id; input.value = b.dataset.label; box.hidden = true; form.querySelector('[name="new_target_pct"]').focus(); }));
    }, 200));
  }
})();
