-- database/create_audit_logs.sql
-- Lightweight audit log table for authentication and critical actions
-- Run this once on both local and live database

CREATE TABLE IF NOT EXISTS audit_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NULL,
    action        VARCHAR(50)  NOT NULL COMMENT 'e.g. login, logout, failed_login, open_card',
    details       VARCHAR(255) NULL     COMMENT 'Human-readable context',
    ip_address    VARCHAR(45)  NULL     COMMENT 'Supports both IPv4 and IPv6',
    user_agent    VARCHAR(255) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user    (user_id),
    INDEX idx_audit_action  (action),
    INDEX idx_audit_created (created_at),

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lightweight audit trail for login, logout, and critical system events';
