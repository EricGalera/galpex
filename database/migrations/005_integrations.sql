-- ============================================================
-- 005 · Proveedores de datos, caché y exchanges conectados
-- ============================================================

CREATE TABLE api_providers (
    id                  SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(24)  NOT NULL,
    name                VARCHAR(60)  NOT NULL,
    requests_per_minute INT UNSIGNED NOT NULL,
    requests_per_day    INT UNSIGNED NOT NULL,
    calls_minute        INT UNSIGNED NOT NULL DEFAULT 0,
    minute_window       INT UNSIGNED NOT NULL DEFAULT 0,
    calls_today         INT UNSIGNED NOT NULL DEFAULT 0,
    calls_date          DATE         NULL,
    last_success_at     DATETIME     NULL,
    last_error_at       DATETIME     NULL,
    last_error          VARCHAR(255) NULL,
    UNIQUE KEY uq_providers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_cache (
    cache_key   CHAR(64)   PRIMARY KEY,
    payload     MEDIUMTEXT NOT NULL,
    expires_at  INT UNSIGNED NOT NULL,
    KEY idx_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuentas de exchanges en modo SOLO LECTURA. Las claves se guardan cifradas (libsodium + APP_KEY).
CREATE TABLE connected_exchanges (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    exchange           ENUM('binance','okx','bybit') NOT NULL,
    label              VARCHAR(60)  NOT NULL,
    api_key_enc        TEXT         NOT NULL,
    api_secret_enc     TEXT         NOT NULL,
    read_only_verified TINYINT(1)   NOT NULL DEFAULT 0,
    status             ENUM('pending','active','error') NOT NULL DEFAULT 'pending',
    status_message     VARCHAR(255) NULL,
    last_sync_at       DATETIME     NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_exchanges_user (user_id),
    CONSTRAINT fk_exchanges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exchange_balances (
    exchange_id  INT UNSIGNED  NOT NULL,
    asset        VARCHAR(16)   NOT NULL,
    free_amount  DECIMAL(30,10) NOT NULL,
    locked_amount DECIMAL(30,10) NOT NULL DEFAULT 0,
    synced_at    DATETIME      NOT NULL,
    PRIMARY KEY (exchange_id, asset),
    CONSTRAINT fk_balances_exchange FOREIGN KEY (exchange_id) REFERENCES connected_exchanges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exchange_trades (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exchange_id   INT UNSIGNED   NOT NULL,
    external_id   VARCHAR(64)    NOT NULL,
    symbol        VARCHAR(24)    NOT NULL,
    side          ENUM('buy','sell') NOT NULL,
    quantity      DECIMAL(30,10) NOT NULL,
    price         DECIMAL(30,10) NOT NULL,
    fee           DECIMAL(30,10) NULL,
    fee_asset     VARCHAR(16)    NULL,
    executed_at   DATETIME       NOT NULL,
    UNIQUE KEY uq_exchange_trade (exchange_id, external_id),
    CONSTRAINT fk_trades_exchange FOREIGN KEY (exchange_id) REFERENCES connected_exchanges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
