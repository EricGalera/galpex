/* GALPEX · Diálogo de operación: búsqueda de instrumento, previsualización y validación */
(function () {
  'use strict';
  const G = window.Galpex;
  const dialog = document.getElementById('trade-dialog');
  if (!dialog) return;
  const form = dialog.querySelector('[data-trade-form]');
  const qty = form.querySelector('[name="quantity"]');
  const amount = form.querySelector('#t-amount');
  const submit = form.querySelector('[data-submit]');
  const problems = form.querySelector('[data-problems]');
  const f = (k) => form.querySelector(`[data-f="${k}"]`);
  let last = null;

  const portfolioId = () => form.querySelector('[name="portfolio_id"]').value;
  const instrumentId = () => form.querySelector('[name="instrument_id"]').value;
  const side = () => form.querySelector('[name="side"]:checked').value;

  // Recuerda la última cartera elegida (solo en este navegador)
  const portfolioSelect = form.querySelector('select[name="portfolio_id"]');
  const KEY = 'galpex.trade.portfolio';
  function restorePortfolio() {
    if (!portfolioSelect) return;
    try {
      const saved = localStorage.getItem(KEY);
      if (saved && portfolioSelect.querySelector(`option[value="${saved}"]`)) portfolioSelect.value = saved;
    } catch (e) { /* almacenamiento no disponible */ }
  }
  portfolioSelect?.addEventListener('change', () => { try { localStorage.setItem(KEY, portfolioSelect.value); } catch (e) { /* sin almacenamiento */ } });

  function open(opts = {}) {
    restorePortfolio();
    if (opts.side) form.querySelector(`[name="side"][value="${opts.side}"]`).checked = true;
    if (opts.instrumentId) {
      form.querySelector('[name="instrument_id"]').value = opts.instrumentId;
      const label = form.querySelector('#t-instrument');
      if (label) label.value = opts.instrumentLabel || '';
    }
    if (opts.quantity) qty.value = opts.quantity;
    dialog.showModal();
    syncSide();
    preview();
    (form.querySelector('#t-instrument:not([value=""])') && !opts.instrumentId ? form.querySelector('#t-instrument') : qty).focus();
  }
  G.openTrade = open;
  document.querySelectorAll('[data-open-trade]').forEach((b) => b.addEventListener('click', () => open({
    side: b.dataset.side, instrumentId: b.dataset.instrument, instrumentLabel: b.dataset.label, quantity: b.dataset.quantity,
  })));
  dialog.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => dialog.close()));

  function syncSide() {
    const buy = side() === 'buy';
    submit.className = 'btn ' + (buy ? 'btn-buy' : 'btn-sell');
    submit.textContent = buy ? 'Confirmar compra' : 'Confirmar venta';
    f('total-label').textContent = buy ? 'Total a pagar' : 'Total a cobrar';
  }
  form.querySelectorAll('[name="side"]').forEach((r) => r.addEventListener('change', () => { syncSide(); preview(); }));

  const preview = G.debounce(async () => {
    submit.disabled = true;
    problems.textContent = '';
    if (!instrumentId() || !portfolioId()) return;
    const qs = new URLSearchParams({ instrumento: instrumentId(), lado: side() });
    if (qty.value.trim()) qs.set('cantidad', qty.value.trim());
    else if (amount.value.trim()) qs.set('monto', amount.value.trim());
    try {
      const d = await G.api(dialog.dataset.previewBase + portfolioId() + '/previsualizar?' + qs);
      last = d;
      const cur = d.currency;
      f('price').textContent = G.money(d.price, d.instrument.currency) + (d.unit > 1 ? ' c/100 VN' : '');
      f('fx').textContent = d.fx_rate === 1 ? '—' : G.num(d.fx_rate, 4);
      f('gross').textContent = G.money(d.gross, cur, 2);
      f('commission').textContent = G.money(d.commission, cur, 2) + ' (' + G.pct(d.commission_rate * 100, 2, false) + ')';
      f('total').textContent = G.money(d.total, cur, 2);
      f('cash').textContent = G.money(d.cash, cur, 2);
      form.querySelector('[data-held]').textContent = d.held > 0 ? 'Tenencia: ' + G.num(d.held, d.fractional ? 6 : 0) : '';
      form.querySelector('[data-max]').textContent = side() === 'buy' ? 'Máximo comprable: ' + G.num(d.max_buy, d.fractional ? 6 : 0) : '';
      if (!qty.value.trim() && amount.value.trim()) qty.placeholder = G.num(d.quantity, d.fractional ? 6 : 0);
      problems.textContent = d.problems.join(' ');
      submit.disabled = d.problems.length > 0;
    } catch (e) { problems.textContent = e.message; }
  }, 250);

  qty.addEventListener('input', () => { if (qty.value) amount.value = ''; preview(); });
  amount.addEventListener('input', () => { if (amount.value) qty.value = ''; preview(); });
  portfolioSelect?.addEventListener('change', preview);

  form.addEventListener('submit', (ev) => {
    if (!qty.value.trim() && last && last.quantity > 0) qty.value = String(last.quantity).replace('.', ',');
    form.action = dialog.dataset.actionBase + portfolioId() + '/operar';
    if (!qty.value.trim()) { ev.preventDefault(); problems.textContent = 'Ingresá la cantidad o el monto.'; }
  });

  // Selector de instrumento (cuando la cartera es fija)
  const picker = form.querySelector('[data-instrument-picker]');
  if (picker) {
    const input = picker.querySelector('input[type="search"]');
    const box = picker.querySelector('.search-results');
    const run = G.debounce(async () => {
      const q = input.value.trim();
      if (!q) { box.hidden = true; return; }
      const data = await G.api('/api/buscar?q=' + encodeURIComponent(q));
      const items = data.results.filter((i) => i.category !== 'Índices bursátiles');
      box.innerHTML = items.length ? items.map((i) => `<button type="button" class="result" data-id="${i.id}" data-label="${G.esc(i.symbol + ', ' + i.name)}" style="width:100%;border:0;background:none;color:inherit;text-align:left">
        <span class="sym">${G.esc(i.symbol)}</span><span class="ellipsis">${G.esc(i.name)}<br><span class="meta">${G.esc(i.market)}</span></span><span class="num">${i.price !== null ? G.esc(G.money(i.price, i.currency)) : '—'}</span></button>`).join('') : '<p class="empty">Sin resultados.</p>';
      box.hidden = false;
      box.querySelectorAll('[data-id]').forEach((b) => b.addEventListener('click', () => {
        form.querySelector('[name="instrument_id"]').value = b.dataset.id;
        input.value = b.dataset.label;
        box.hidden = true;
        qty.focus();
        preview();
      }));
    }, 200);
    input.addEventListener('input', () => { form.querySelector('[name="instrument_id"]').value = ''; run(); });
  }
})();
