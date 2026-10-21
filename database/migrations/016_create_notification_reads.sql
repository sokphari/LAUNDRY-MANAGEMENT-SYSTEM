CREATE TABLE user_notification_reads (
    user_id BIGINT UNSIGNED NOT NULL,
    last_read_audit_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT user_notification_reads_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing audit history is already available in Audit Logs; do not surface it as new bell alerts.
INSERT INTO user_notification_reads (user_id, last_read_audit_id)
SELECT id, (SELECT COALESCE(MAX(id), 0) FROM audit_logs) FROM users;
