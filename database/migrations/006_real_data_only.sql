-- ============================================================
-- 006 · Solo datos reales
--   · Puntas de compra/venta en la cotización (pizarra estilo bróker)
--   · Marca de nombre pendiente para los instrumentos sumados desde el panel de BYMA
--   · Limpieza de todo lo que generaba el modo demostración
-- ============================================================

-- (IF NOT EXISTS: si la migración se corta a mitad de camino, se puede volver a ejecutar)
ALTER TABLE quotes
    ADD COLUMN IF NOT EXISTS bid       DECIMAL(20,8) NULL AFTER change_pct,
    ADD COLUMN IF NOT EXISTS ask       DECIMAL(20,8) NULL AFTER bid,
    ADD COLUMN IF NOT EXISTS bid_size  DECIMAL(24,4) NULL AFTER ask,
    ADD COLUMN IF NOT EXISTS ask_size  DECIMAL(24,4) NULL AFTER bid_size,
    ADD COLUMN IF NOT EXISTS trades    INT UNSIGNED  NULL AFTER ask_size;

ALTER TABLE instruments
    ADD COLUMN IF NOT EXISTS needs_name TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD INDEX IF NOT EXISTS idx_instruments_needs_name (needs_name),
    ADD INDEX IF NOT EXISTS idx_instruments_active_category (is_active, category_id);

-- La cuenta de demostración y todo lo que colgaba de ella (carteras, movimientos, alertas, diario)
DELETE FROM users WHERE email = 'demo@galpex.local';

-- Carteras que operaron con precios simulados: sus saldos no representan nada real
DELETE FROM portfolios WHERE id IN (
    SELECT portfolio_id FROM (SELECT DISTINCT portfolio_id FROM transactions WHERE price_simulated = 1) AS sim
);

-- Precios generados por el simulador
DELETE FROM price_history WHERE is_simulated = 1;
DELETE FROM quotes WHERE is_simulated = 1;
DELETE FROM api_providers WHERE code = 'simulated';
DELETE FROM api_cache;

-- Instrumentos sin una fuente pública de precios
UPDATE instruments SET is_active = 0
WHERE symbol IN ('FIMA-PREM', 'FCI-RF-AR', 'TLC1O');

-- Nuevo diseño: el tema claro pasa a ser el predeterminado
ALTER TABLE user_profiles MODIFY theme ENUM('dark','light') NOT NULL DEFAULT 'light';
UPDATE user_profiles SET theme = 'light';
