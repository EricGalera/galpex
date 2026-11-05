<?php
/**
 * Diálogo de operación (compra/venta virtual).
 * Variables: $portfolios (lista), $instrument (opcional, fijo), $portfolio (opcional, fijo)
 */
$fixedInstrument = $instrument ?? null;
$fixedPortfolio = $portfolio ?? null;
?>
<dialog class="dialog" id="trade-dialog" aria-labelledby="trade-title"
        data-preview-base="<?= e(app_url('/api/carteras/')) ?>" data-action-base="<?= e(app_url('/carteras/')) ?>">
  <form method="post" data-trade-form>
    <?= csrf_field() ?>
    <div class="dialog-head">
      <h2 id="trade-title">Operar <?= $fixedInstrument ? e($fixedInstrument['symbol']) : '' ?></h2>
      <button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="dialog-body">
      <div class="ticket-side" role="radiogroup" aria-label="Tipo de operación">
        <label><input type="radio" name="side" value="buy" checked><span>Comprar</span></label>
        <label><input type="radio" name="side" value="sell"><span>Vender</span></label>
      </div>

      <?php if ($fixedPortfolio): ?>
        <input type="hidden" name="portfolio_id" value="<?= (int) $fixedPortfolio['id'] ?>">
      <?php else: ?>
        <div class="field">
          <label for="t-portfolio">Cartera donde se registra la operación</label>
          <select class="select" id="t-portfolio" name="portfolio_id" required>
            <?php foreach ($portfolios as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?>: efectivo <?= e(money($p['cash_balance'], $p['base_currency'], 2)) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($fixedInstrument): ?>
        <input type="hidden" name="instrument_id" value="<?= (int) $fixedInstrument['id'] ?>">
      <?php else: ?>
        <div class="field" data-instrument-picker>
          <label for="t-instrument">Instrumento</label>
          <div style="position:relative">
            <input class="input" id="t-instrument" type="search" autocomplete="off" placeholder="Buscá por símbolo o nombre" required>
            <div class="popover search-results" hidden></div>
          </div>
          <input type="hidden" name="instrument_id">
        </div>
      <?php endif; ?>

      <div class="form-grid">
        <div class="field">
          <label for="t-qty">Cantidad</label>
          <input class="input input-num" id="t-qty" name="quantity" inputmode="decimal" autocomplete="off" required>
          <span class="hint" data-held></span>
        </div>
        <div class="field">
          <label for="t-amount">o monto a invertir</label>
          <input class="input input-num" id="t-amount" inputmode="decimal" autocomplete="off" placeholder="Opcional">
          <span class="hint" data-max></span>
        </div>
      </div>

      <dl class="summary" data-summary>
        <dt>Precio</dt><dd data-f="price">—</dd>
        <dt>Tipo de cambio</dt><dd data-f="fx">—</dd>
        <dt>Importe</dt><dd data-f="gross">—</dd>
        <dt>Comisión</dt><dd data-f="commission">—</dd>
        <dt class="total" data-f="total-label">Total a pagar</dt><dd class="total" data-f="total">—</dd>
        <dt>Efectivo disponible</dt><dd data-f="cash">—</dd>
      </dl>
      <p class="error" data-problems role="alert" style="color:var(--down);margin-top:var(--s3)"></p>
    </div>
    <div class="dialog-foot">
      <button class="btn" type="button" data-close>Cancelar</button>
      <button class="btn btn-buy" type="submit" data-submit disabled>Confirmar compra</button>
    </div>
  </form>
</dialog>
