-- ============================================================
-- 002 · Mercados, categorías, instrumentos y precios
-- ============================================================

CREATE TABLE markets (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(16)  NOT NULL,
    name        VARCHAR(100) NOT NULL,
    country     CHAR(2)      NULL,
    currency    CHAR(4)      NOT NULL,
    timezone    VARCHAR(64)  NOT NULL,
    opens_at    TIME         NULL,
    closes_at   TIME         NULL,
    UNIQUE KEY uq_markets_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_categories (
    id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(24)  NOT NULL,
    name          VARCHAR(60)  NOT NULL,
    asset_class   ENUM('equity','fixed_income','fund','crypto','cash','index','fx') NOT NULL,
    -- Beta y duración por defecto para el simulador de escenarios cuando falta historia
    default_beta  DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    sort_order    SMALLINT     NOT NULL DEFAULT 0,
    UNIQUE KEY uq_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instruments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol           VARCHAR(24)  NOT NULL,
    name             VARCHAR(150) NOT NULL,
    market_id        SMALLINT UNSIGNED NOT NULL,
    category_id      SMALLINT UNSIGNED NOT NULL,
    currency         CHAR(4)      NOT NULL,
    -- Unidad de cotización: 1 para acciones; 100 para bonos (precio cada 100 de valor nominal)
    quote_unit       DECIMAL(10,2) NOT NULL DEFAULT 1,
    -- Permite fracciones (cripto, FCI, divisas); si es 0 solo se operan unidades enteras
    fractional       TINYINT(1)   NOT NULL DEFAULT 0,
    country          CHAR(2)      NULL,
    sector           VARCHAR(60)  NULL,
    description      TEXT         NULL,
    -- Símbolos específicos por proveedor (ej. "BTCUSDT" en Binance, "GGAL:BCBA" en Twelve Data)
    provider_symbols TEXT         NULL,
    -- Renta fija: cupón anual y duración modificada estimada (para escenarios de tasa)
    coupon_rate      DECIMAL(6,4) NULL,
    duration_years   DECIMAL(6,2) NULL,
    maturity_date    DATE         NULL,
    -- Parámetros del generador de precios simulados (solo modo demostración)
    sim_base_price   DECIMAL(20,8) NULL,
    sim_volatility   DECIMAL(6,4)  NULL,
    sim_drift        DECIMAL(6,4)  NULL,
    sim_beta         DECIMAL(5,2)  NULL,
    is_benchmark     TINYINT(1)   NOT NULL DEFAULT 0,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_instruments_symbol_market (symbol, market_id),
    KEY idx_instruments_category (category_id),
    KEY idx_instruments_sector (sector),
    KEY idx_instruments_country (country),
    FULLTEXT KEY ft_instruments_search (symbol, name),
    CONSTRAINT fk_instruments_market   FOREIGN KEY (market_id)   REFERENCES markets(id),
    CONSTRAINT fk_instruments_category FOREIGN KEY (category_id) REFERENCES asset_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Última cotización conocida de cada instrumento (con origen y antigüedad)
CREATE TABLE quotes (
    instrument_id  INT UNSIGNED PRIMARY KEY,
    price          DECIMAL(20,8) NOT NULL,
    prev_close     DECIMAL(20,8) NULL,
    open_price     DECIMAL(20,8) NULL,
    day_high       DECIMAL(20,8) NULL,
    day_low        DECIMAL(20,8) NULL,
    volume         DECIMAL(24,4) NULL,
    market_cap     DECIMAL(24,2) NULL,
    change_abs     DECIMAL(20,8) NULL,
    change_pct     DECIMAL(10,4) NULL,
    source         VARCHAR(24)   NOT NULL,
    is_simulated   TINYINT(1)    NOT NULL DEFAULT 0,
    is_delayed     TINYINT(1)    NOT NULL DEFAULT 0,
    quoted_at      DATETIME      NULL,
    fetched_at     DATETIME      NOT NULL,
    CONSTRAINT fk_quotes_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serie diaria de precios (OHLCV). adj_close considera dividendos y splits si el proveedor lo informa.
CREATE TABLE price_history (
    instrument_id  INT UNSIGNED  NOT NULL,
    price_date     DATE          NOT NULL,
    open_price     DECIMAL(20,8) NULL,
    high_price     DECIMAL(20,8) NULL,
    low_price      DECIMAL(20,8) NULL,
    close_price    DECIMAL(20,8) NOT NULL,
    adj_close      DECIMAL(20,8) NULL,
    volume         DECIMAL(24,4) NULL,
    source         VARCHAR(24)   NOT NULL,
    is_simulated   TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (instrument_id, price_date),
    KEY idx_history_date (price_date),
    CONSTRAINT fk_history_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dividends (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instrument_id    INT UNSIGNED  NOT NULL,
    ex_date          DATE          NOT NULL,
    pay_date         DATE          NULL,
    amount_per_unit  DECIMAL(20,8) NOT NULL,
    currency         CHAR(4)       NOT NULL,
    source           VARCHAR(24)   NOT NULL,
    UNIQUE KEY uq_dividends (instrument_id, ex_date),
    CONSTRAINT fk_dividends_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE watchlists (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(80)  NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_watchlists_user (user_id),
    CONSTRAINT fk_watchlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE watchlist_items (
    watchlist_id   INT UNSIGNED NOT NULL,
    instrument_id  INT UNSIGNED NOT NULL,
    added_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (watchlist_id, instrument_id),
    CONSTRAINT fk_wl_items_list FOREIGN KEY (watchlist_id) REFERENCES watchlists(id) ON DELETE CASCADE,
    CONSTRAINT fk_wl_items_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
