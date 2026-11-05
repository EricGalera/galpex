-- ============================================================
-- 008 · Limpieza del esquema
--   · Se quitan las columnas del antiguo modo demostración (precios simulados)
--   · La comisión deja de ser un dato de cada cartera: depende del tipo de
--     instrumento y se define en config/commissions.php
-- (IF EXISTS: la migración se puede volver a ejecutar sin errores)
-- ============================================================

ALTER TABLE instruments
    DROP COLUMN IF EXISTS sim_base_price,
    DROP COLUMN IF EXISTS sim_volatility,
    DROP COLUMN IF EXISTS sim_drift,
    DROP COLUMN IF EXISTS sim_beta;

ALTER TABLE quotes DROP COLUMN IF EXISTS is_simulated;

ALTER TABLE price_history DROP COLUMN IF EXISTS is_simulated;

ALTER TABLE transactions DROP COLUMN IF EXISTS price_simulated;

ALTER TABLE portfolios DROP COLUMN IF EXISTS commission_rate;
