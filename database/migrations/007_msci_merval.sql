-- ============================================================
-- 007 · MSCI Merval
--   Desde octubre de 2026 BYMA reemplazó al S&P Merval por el MSCI Merval,
--   que arranca en otra base. Se borran los precios del índice anterior para
--   no mezclar dos series distintas: el MSCI Merval se toma de BYMA.
-- ============================================================

DELETE FROM price_history WHERE instrument_id IN (
    SELECT id FROM (SELECT i.id FROM instruments i JOIN markets m ON m.id = i.market_id WHERE i.symbol = 'MERV' AND m.code = 'INDEX') AS merv
);
DELETE FROM quotes WHERE instrument_id IN (
    SELECT id FROM (SELECT i.id FROM instruments i JOIN markets m ON m.id = i.market_id WHERE i.symbol = 'MERV' AND m.code = 'INDEX') AS merv
);
DELETE FROM api_cache;
