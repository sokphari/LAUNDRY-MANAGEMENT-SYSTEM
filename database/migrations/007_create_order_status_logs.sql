CREATE TABLE order_status_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    old_status ENUM('received', 'processing', 'ready', 'collected', 'cancelled') NULL,
    new_status ENUM('received', 'processing', 'ready', 'collected', 'cancelled') NOT NULL,
    notes VARCHAR(500) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY order_status_logs_history_index (order_id, changed_at),
    CONSTRAINT order_status_logs_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
    CONSTRAINT order_status_logs_user_fk FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
