/* GALPEX · Alertas: campos según la condición y selector de instrumento */
(function () {
  'use strict';
  const G = window.Galpex;
  const form = document.querySelector('[data-alert-form]');
  if (!form) return;
  const type = form.querySelector('[name="type"]');
  const instrumentTypes = ['price_above', 'price_below', 'change_pct'];
  const sync = () => {
    const t = type.value;
    form.querySelector('[data-for="instrument"]').hidden = !(instrumentTypes.includes(t) || t === 'position_return');
    form.querySelector('[data-for="portfolio"]').hidden = instrumentTypes.includes(t);
  };
  type.addEventListener('change', sync);
  sync();

  const picker = form.querySelector('[data-alert-picker]');
  const input = picker.querySelector('input');
  const box = picker.querySelector('.search-results');
  const hidden = form.querySelector('[name="instrument_id"]');
  input.addEventListener('input', G.debounce(async () => {
    hidden.value = '';
    const q = input.value.trim();
    if (!q) { box.hidden = true; return; }
    const data = await G.api('/api/buscar?q=' + encodeURIComponent(q));
    box.innerHTML = data.results.map((i) => `<button type="button" class="result" data-id="${i.id}" data-label="${G.esc(i.symbol + ' · ' + i.name)}" data-price="${i.price !== null ? G.esc(G.money(i.price, i.currency)) : ''}" style="width:100%;border:0;background:none;color:inherit;text-align:left"><span class="sym">${G.esc(i.symbol)}</span><span class="ellipsis">${G.esc(i.name)}</span><span class="num">${i.price !== null ? G.esc(G.money(i.price, i.currency)) : '—'}</span></button>`).join('') || '<p class="empty">Sin resultados.</p>';
    box.hidden = false;
    box.querySelectorAll('[data-id]').forEach((b) => b.addEventListener('click', () => {
      hidden.value = b.dataset.id; input.value = b.dataset.label; box.hidden = true;
      form.querySelector('[data-current-price]').textContent = b.dataset.price ? 'Precio actual: ' + b.dataset.price : '';
      form.querySelector('[name="threshold"]').focus();
    }));
  }, 200));
})();
