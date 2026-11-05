/* GALPEX · Mercados: estrellas de seguimiento y actualización de cotizaciones visibles */
(function () {
  'use strict';
  const G = window.Galpex;

  document.querySelectorAll('form[data-watch-toggle]').forEach((form) => {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = form.querySelector('button');
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Error');
        btn.classList.toggle('is-watched', data.added);
        btn.setAttribute('aria-pressed', String(data.added));
        G.toast(data.message);
      } catch (e) { G.toast(e.message, 'error'); }
    });
  });

  const table = document.querySelector('[data-quotes-table]');
  if (!table) return;
  const ids = Array.from(table.querySelectorAll('tbody tr[data-id]')).map((r) => r.dataset.id);
  const labels = { live: 'Tiempo real', delayed: 'Diferido', stale: 'Desactualizado', none: 'Sin cotización' };

  async function refresh(batch) {
    if (document.hidden || !batch.length) return;
    try {
      const data = await G.api('/api/cotizaciones?ids=' + batch.join(','));
      data.items.forEach((i) => {
        const row = table.querySelector(`tr[data-id="${i.id}"]`);
        if (!row) return;
        const px = row.querySelector('[data-px]');
        px.textContent = i.price !== null ? G.money(i.price, i.currency) : '—';
        px.dataset.value = i.price ?? '';
        const chg = row.querySelector('[data-chg]');
        chg.textContent = G.pct(i.change_pct);
        chg.className = 'chg ' + G.trend(i.change_pct);
        chg.parentElement.dataset.value = i.change_pct ?? '';
        row.querySelector('[data-ds]').innerHTML = `<span class="ds ds-${i.status}">${labels[i.status]}</span>`;
      });
    } catch (e) { /* se mantiene lo mostrado */ }
  }
  // Actualiza de a 40 instrumentos por llamada, cada 60 segundos.
  setInterval(() => { for (let k = 0; k < Math.min(ids.length, 120); k += 40) refresh(ids.slice(k, k + 40)); }, 60000);
})();
