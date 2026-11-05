-- ============================================================
-- 003 · Carteras, posiciones, órdenes y transacciones
-- ============================================================

CREATE TABLE portfolios (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NOT NULL,
    name             VARCHAR(80)   NOT NULL,
    description      VARCHAR(255)  NULL,
    objective        ENUM('preservation','income','growth','balanced','speculation','custom') NOT NULL DEFAULT 'balanced',
    base_currency    CHAR(3)       NOT NULL DEFAULT 'USD',
    initial_capital  DECIMAL(20,2) NOT NULL,
    cash_balance     DECIMAL(20,2) NOT NULL,
    horizon_years    TINYINT UNSIGNED NOT NULL DEFAULT 5,
    target_risk      TINYINT UNSIGNED NOT NULL DEFAULT 5,
    commission_rate  DECIMAL(6,5)  NOT NULL DEFAULT 0.00500,
    color            VARCHAR(7)    NULL,
    is_archived      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_portfolios_user (user_id, is_archived),
    CONSTRAINT fk_portfolios_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_portfolios_cash CHECK (cash_balance >= 0),
    CONSTRAINT chk_portfolios_risk CHECK (target_risk BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Distribución objetivo (por instrumento o por categoría) usada para rebalanceo y alertas de desvío
CREATE TABLE portfolio_targets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id   INT UNSIGNED NOT NULL,
    instrument_id  INT UNSIGNED NOT NULL,
    target_pct     DECIMAL(7,4) NOT NULL,
    UNIQUE KEY uq_targets (portfolio_id, instrument_id),
    CONSTRAINT fk_targets_portfolio  FOREIGN KEY (portfolio_id)  REFERENCES portfolios(id) ON DELETE CASCADE,
    CONSTRAINT fk_targets_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id),
    CONSTRAINT chk_targets_pct CHECK (target_pct >= 0 AND target_pct <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portfolio_positions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id   INT UNSIGNED  NOT NULL,
    instrument_id  INT UNSIGNED  NOT NULL,
    quantity       DECIMAL(24,8) NOT NULL DEFAULT 0,
    -- Precio promedio ponderado de compra, en la moneda del instrumento
    avg_cost       DECIMAL(20,8) NOT NULL DEFAULT 0,
    -- Costo promedio expresado en la moneda base de la cartera (incluye comisiones)
    avg_cost_base  DECIMAL(20,8) NOT NULL DEFAULT 0,
    realized_pnl   DECIMAL(20,2) NOT NULL DEFAULT 0,
    opened_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_positions (portfolio_id, instrument_id),
    CONSTRAINT fk_positions_portfolio  FOREIGN KEY (portfolio_id)  REFERENCES portfolios(id) ON DELETE CASCADE,
    CONSTRAINT fk_positions_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id),
    CONSTRAINT chk_positions_qty CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id   INT UNSIGNED  NOT NULL,
    instrument_id  INT UNSIGNED  NOT NULL,
    side           ENUM('buy','sell') NOT NULL,
    order_type     ENUM('market') NOT NULL DEFAULT 'market',
    quantity       DECIMAL(24,8) NOT NULL,
    status         ENUM('filled','rejected') NOT NULL,
    reject_reason  VARCHAR(255)  NULL,
    origin         ENUM('manual','rebalance','advisor','import') NOT NULL DEFAULT 'manual',
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_orders_portfolio (portfolio_id, created_at),
    CONSTRAINT fk_orders_portfolio  FOREIGN KEY (portfolio_id)  REFERENCES portfolios(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Libro contable de la cartera: cada movimiento de efectivo o de títulos queda registrado
CREATE TABLE transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id    INT UNSIGNED  NOT NULL,
    order_id        INT UNSIGNED  NULL,
    instrument_id   INT UNSIGNED  NULL,
    type            ENUM('deposit','withdrawal','buy','sell','dividend') NOT NULL,
    quantity        DECIMAL(24,8) NULL,
    price           DECIMAL(20,8) NULL,
    -- Moneda del instrumento → moneda base de la cartera
    fx_rate         DECIMAL(20,8) NOT NULL DEFAULT 1,
    gross_amount    DECIMAL(20,2) NOT NULL,
    commission      DECIMAL(20,2) NOT NULL DEFAULT 0,
    -- Impacto en el efectivo (positivo entra, negativo sale), en moneda base
    cash_effect     DECIMAL(20,2) NOT NULL,
    realized_pnl    DECIMAL(20,2) NULL,
    cash_after      DECIMAL(20,2) NOT NULL,
    price_source    VARCHAR(24)   NULL,
    price_simulated TINYINT(1)    NOT NULL DEFAULT 0,
    notes           VARCHAR(255)  NULL,
    executed_at     DATETIME      NOT NULL,
    KEY idx_tx_portfolio (portfolio_id, executed_at),
    KEY idx_tx_instrument (instrument_id),
    CONSTRAINT fk_tx_portfolio  FOREIGN KEY (portfolio_id)  REFERENCES portfolios(id) ON DELETE CASCADE,
    CONSTRAINT fk_tx_order      FOREIGN KEY (order_id)      REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_tx_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portfolio_benchmarks (
    portfolio_id   INT UNSIGNED NOT NULL,
    instrument_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (portfolio_id, instrument_id),
    CONSTRAINT fk_pb_portfolio  FOREIGN KEY (portfolio_id)  REFERENCES portfolios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pb_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_profiles
    ADD CONSTRAINT fk_profiles_default_portfolio FOREIGN KEY (default_portfolio_id) REFERENCES portfolios(id) ON DELETE SET NULL;
