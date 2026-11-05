-- ============================================================
-- 004 · Asesor, alertas, notificaciones y diario de inversiones
-- ============================================================

CREATE TABLE investment_profiles (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED  NOT NULL,
    answers            TEXT          NOT NULL,          -- respuestas del cuestionario (JSON)
    capital            DECIMAL(20,2) NOT NULL,
    currency           CHAR(3)       NOT NULL,
    horizon_years      TINYINT UNSIGNED NOT NULL,
    risk_tolerance     TINYINT UNSIGNED NOT NULL,       -- 0..100
    risk_capacity      TINYINT UNSIGNED NOT NULL,       -- 0..100
    risk_score         TINYINT UNSIGNED NOT NULL,       -- 0..100 (combinado)
    liquidity_need     ENUM('low','medium','high') NOT NULL,
    income_preference  ENUM('income','balanced','growth') NOT NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_profiles_user (user_id, created_at),
    CONSTRAINT fk_invprofiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portfolio_recommendations (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                INT UNSIGNED  NOT NULL,
    profile_id             INT UNSIGNED  NOT NULL,
    strategy_code          VARCHAR(32)   NOT NULL,
    name                   VARCHAR(80)   NOT NULL,
    allocation             TEXT          NOT NULL,      -- [{instrument_id, weight, reason}] (JSON)
    analysis               TEXT          NOT NULL,      -- explicación estructurada (JSON)
    fit_score              TINYINT UNSIGNED NOT NULL,
    expected_return        DECIMAL(8,4)  NULL,
    expected_volatility    DECIMAL(8,4)  NULL,
    accepted_portfolio_id  INT UNSIGNED  NULL,
    created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reco_profile (profile_id),
    CONSTRAINT fk_reco_user      FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reco_profile   FOREIGN KEY (profile_id) REFERENCES investment_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_reco_portfolio FOREIGN KEY (accepted_portfolio_id) REFERENCES portfolios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alerts (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NOT NULL,
    type             ENUM('price_above','price_below','change_pct','portfolio_drawdown','position_return','allocation_drift') NOT NULL,
    instrument_id    INT UNSIGNED  NULL,
    portfolio_id     INT UNSIGNED  NULL,
    threshold        DECIMAL(20,8) NOT NULL,
    note             VARCHAR(160)  NULL,
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    repeat_alert     TINYINT(1)    NOT NULL DEFAULT 0,
    observed_value       DECIMAL(20,8) NULL,
    triggered_at     DATETIME      NULL,
    last_checked_at  DATETIME      NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_alerts_user (user_id, is_active),
    CONSTRAINT fk_alerts_user       FOREIGN KEY (user_id)       REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_alerts_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE,
    CONSTRAINT fk_alerts_portfolio  FOREIGN KEY (portfolio_id)  REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    alert_id    INT UNSIGNED NULL,
    kind        ENUM('alert','trade','system') NOT NULL DEFAULT 'alert',
    title       VARCHAR(120) NOT NULL,
    body        VARCHAR(255) NULL,
    link        VARCHAR(255) NULL,
    read_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE investment_journal (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    transaction_id  INT UNSIGNED  NULL,
    instrument_id   INT UNSIGNED  NULL,
    entry_type      ENUM('buy','sell','idea','review') NOT NULL DEFAULT 'idea',
    reason          TEXT          NULL,
    objective       VARCHAR(255)  NULL,
    expectation     VARCHAR(255)  NULL,
    risk_taken      VARCHAR(255)  NULL,
    outcome         TEXT          NULL,
    observations    TEXT          NULL,
    rating          TINYINT UNSIGNED NULL,       -- autoevaluación 1..5
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_journal_user (user_id, created_at),
    CONSTRAINT fk_journal_user        FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_journal_instrument  FOREIGN KEY (instrument_id)  REFERENCES instruments(id) ON DELETE SET NULL,
    CONSTRAINT chk_journal_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
