CREATE TABLE order_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    received_by BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('cash', 'khqr') NOT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY order_payments_order_paid_index (order_id, paid_at),
    CONSTRAINT order_payments_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
    CONSTRAINT order_payments_received_by_fk FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
