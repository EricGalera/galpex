<?php use App\Services\Advisor\RiskProfiler; ?>
<div class="page-head">
  <div>
    <h1>Asesor inversor</h1>
    <p class="sub">Respondé nueve preguntas. El motor de reglas calcula tu perfil y arma varias carteras posibles, con la explicación de cada decisión.</p>
  </div>
</div>
<div class="notice"><?= icon('info') ?><p>Las propuestas son <strong>simulaciones educativas</strong> basadas en reglas y en datos históricos. No son asesoramiento financiero personalizado ni garantizan rendimientos.</p></div>

<div class="grid g-main-side">
  <form class="panel" method="post" action="<?= e(app_url('/asesor')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="question">
      <fieldset>
        <legend>¿Cuánto capital pensás invertir?</legend>
        <div class="form-grid">
          <?= App\Http\View::partial('components/field', ['name' => 'capital', 'label' => 'Monto', 'value' => '10000', 'numeric' => true, 'attrs' => 'inputmode="decimal" required']) ?>
          <?= App\Http\View::partial('components/field', ['name' => 'currency', 'label' => 'Moneda', 'type' => 'select', 'value' => $currency, 'options' => ['USD' => 'Dólares (USD)', 'ARS' => 'Pesos (ARS)']]) ?>
        </div>
      </fieldset>
    </div>
    <?php foreach ($questions as $key => $q): $multi = !empty($q['multiple']); $old = old($key, $multi ? [] : ''); ?>
      <div class="question">
        <fieldset>
          <legend><?= e($q['label']) ?></legend>
          <div class="options">
            <?php foreach ($q['options'] as $value => $label): ?>
              <label class="option">
                <input type="<?= $multi ? 'checkbox' : 'radio' ?>" name="<?= e($key) ?><?= $multi ? '[]' : '' ?>" value="<?= e((string) $value) ?>"
                  <?= ($multi ? in_array((string) $value, (array) $old, true) : (string) $old === (string) $value) ? 'checked' : '' ?> <?= $multi ? '' : 'required' ?>>
                <span><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($err = field_error($key)): ?><p class="error" style="color:var(--down);margin-top:6px"><?= e($err) ?></p><?php endif; ?>
        </fieldset>
      </div>
    <?php endforeach; ?>
    <div class="panel-foot row-between"><span>Tus respuestas quedan guardadas para comparar perfiles en el tiempo.</span><button class="btn btn-primary" type="submit" data-loading-text="Calculando propuestas…">Ver mis propuestas</button></div>
  </form>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Cómo decide el motor</h2></div>
      <div class="panel-body">
        <ol class="howto">
          <li><strong>Tolerancia</strong>: tu reacción ante caídas, la pérdida máxima aceptable y el objetivo.</li>
          <li><strong>Capacidad</strong>: horizonte, necesidad de liquidez y experiencia.</li>
          <li>El puntaje final pondera más la menor de las dos y define cuánto va a renta variable, renta fija, liquidez y cripto.</li>
          <li>Cada estrategia elige instrumentos concretos, respetando tus restricciones.</li>
          <li>La volatilidad y la caída máxima de cada propuesta se miden con datos históricos.</li>
        </ol>
      </div>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Perfiles anteriores</h2></div>
      <div class="list">
        <?php foreach ($history as $h): ?>
          <a class="list-item" href="<?= e(app_url('/asesor/perfil/' . $h['id'])) ?>">
            <span><span class="title"><?= e(RiskProfiler::label((int) $h['risk_score'])) ?>, <?= (int) $h['risk_score'] ?>/100</span><br><span class="meta"><?= e(money($h['capital'], $h['currency'], 0)) ?> a <?= (int) $h['horizon_years'] ?> años, <?= (int) $h['proposals'] ?> propuestas</span></span>
            <span class="meta"><?= e(local_date($h['created_at'], 'd/m/Y')) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if ($history === []): ?><p class="empty">Todavía no completaste el cuestionario.</p><?php endif; ?>
      </div>
    </section>
  </aside>
</div>
