CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    summary VARCHAR(500) NOT NULL,
    details JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY audit_logs_created_index (created_at, id),
    KEY audit_logs_entity_index (entity_type, entity_id),
    KEY audit_logs_action_index (action, created_at),
    CONSTRAINT audit_logs_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
