ALTER TABLE order_payments
    ADD COLUMN voided_at DATETIME NULL AFTER paid_at,
    ADD COLUMN voided_by BIGINT UNSIGNED NULL AFTER voided_at,
    ADD COLUMN void_reason VARCHAR(500) NULL AFTER voided_by,
    ADD COLUMN replacement_payment_id BIGINT UNSIGNED NULL AFTER void_reason,
    ADD KEY order_payments_replacement_index (replacement_payment_id),
    ADD CONSTRAINT order_payments_voided_by_fk FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE RESTRICT,
    ADD CONSTRAINT order_payments_replacement_fk FOREIGN KEY (replacement_payment_id) REFERENCES order_payments (id) ON DELETE RESTRICT;
