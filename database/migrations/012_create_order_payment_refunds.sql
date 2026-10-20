CREATE TABLE order_payment_refunds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    refunded_by BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    refund_method ENUM('cash', 'khqr') NOT NULL,
    reason VARCHAR(500) NOT NULL,
    refunded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY payment_refunds_payment_date_index (payment_id, refunded_at, id),
    KEY payment_refunds_refunded_by_index (refunded_by, refunded_at),
    CONSTRAINT payment_refunds_payment_fk FOREIGN KEY (payment_id) REFERENCES order_payments (id) ON DELETE RESTRICT,
    CONSTRAINT payment_refunds_user_fk FOREIGN KEY (refunded_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
