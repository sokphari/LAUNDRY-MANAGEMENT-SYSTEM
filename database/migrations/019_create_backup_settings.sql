CREATE TABLE backup_settings (
    id TINYINT UNSIGNED NOT NULL,
    automatic_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    last_automatic_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT backup_settings_singleton CHECK (id = 1),
    CONSTRAINT backup_settings_user_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO backup_settings (id, automatic_enabled) VALUES (1, TRUE);
