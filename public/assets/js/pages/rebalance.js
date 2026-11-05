/* GALPEX · Rebalanceo: recalcula importes y pesos finales al editar cantidades */
(function () {
  'use strict';
  const G = window.Galpex;
  const form = document.querySelector('[data-rebalance-form]');
  if (!form) return;
  const cur = form.dataset.currency;
  const cash = parseFloat(form.dataset.cash);

  function recalc() {
    let buys = 0, sells = 0, fees = 0;
    const rows = Array.from(form.querySelectorAll('[data-row]'));
    const values = rows.map((r) => {
      const q = G.parseNum(r.querySelector('input').value) || 0;
      const trade = q * parseFloat(r.dataset.unit);
      if (trade > 0) buys += trade; else sells += -trade;
      fees += Math.abs(trade) * (parseFloat(r.dataset.rate) || 0);
      r.querySelector('[data-trade-value]').textContent = G.signedMoney(trade, cur);
      return parseFloat(r.dataset.current) + trade;
    });
    const cashAfter = cash + sells - buys - fees;
    const total = values.reduce((a, b) => a + b, 0) + Math.max(0, cashAfter);
    rows.forEach((r, k) => { r.querySelector('[data-final-pct]').textContent = G.pct(total > 0 ? (values[k] / total) * 100 : 0, 1, false); });
    form.querySelector('[data-sum="buys"]').textContent = G.money(buys, cur, 2);
    form.querySelector('[data-sum="sells"]').textContent = G.money(sells, cur, 2);
    form.querySelector('[data-sum="required"]').textContent = G.money(Math.max(0, -cashAfter), cur, 2);
    const ca = form.querySelector('[data-cash-after]');
    ca.textContent = G.money(cashAfter, cur, 2);
    ca.classList.toggle('down', cashAfter < 0);
  }
  form.addEventListener('input', recalc);
  recalc();
})();
