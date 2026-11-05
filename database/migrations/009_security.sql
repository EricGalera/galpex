-- ============================================================
-- 009 · Seguridad de cuentas
--   · session_version: sube al cambiar o restablecer la contraseña y cierra
--     todas las demás sesiones abiertas de ese usuario
--   · must_change_password: obliga a elegir una contraseña propia al ingresar
--     (se usa con la contraseña inicial del administrador)
--   · índices para revisar rápido los eventos de seguridad del registro de auditoría
-- (IF NOT EXISTS: la migración se puede volver a ejecutar sin errores)
-- ============================================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status;

ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER session_version;

ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER must_change_password;

ALTER TABLE audit_log ADD INDEX IF NOT EXISTS idx_audit_event_date (event, created_at);

-- Los enlaces de recuperación vencidos o usados no sirven para nada: se borran.
DELETE FROM password_resets WHERE used_at IS NOT NULL OR expires_at < UTC_TIMESTAMP();
