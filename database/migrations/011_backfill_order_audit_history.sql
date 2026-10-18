INSERT INTO audit_logs (user_id, action, entity_type, entity_id, summary, details, created_at)
SELECT o.created_by, 'order.created', 'order', o.id,
       CONCAT('Created order ', o.order_code),
       JSON_OBJECT('order_code', o.order_code, 'customer_id', o.customer_id, 'total', o.total_amount),
       o.created_at
FROM orders o;

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, summary, details, created_at)
SELECT l.changed_by,
       CASE WHEN l.new_status = 'cancelled' THEN 'order.cancelled' ELSE 'order.status_changed' END,
       'order', l.order_id,
       CASE WHEN l.new_status = 'cancelled' THEN CONCAT('Cancelled order ', o.order_code) ELSE CONCAT('Changed order ', o.order_code, ' status to ', l.new_status) END,
       JSON_OBJECT('old_status', l.old_status, 'new_status', l.new_status, 'reason', l.notes),
       l.changed_at
FROM order_status_logs l
INNER JOIN orders o ON o.id = l.order_id;

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, summary, details, created_at)
SELECT p.received_by, 'payment.recorded', 'payment', p.id,
       CONCAT('Recorded a payment for order ', o.order_code),
       JSON_OBJECT('order_id', o.id, 'amount', p.amount, 'method', p.method),
       p.paid_at
FROM order_payments p
INNER JOIN orders o ON o.id = p.order_id;

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, summary, details, created_at)
SELECT p.voided_by, 'payment.corrected', 'payment', p.id,
       CONCAT('Corrected a payment for order ', o.order_code),
       JSON_OBJECT('order_id', o.id, 'original_amount', p.amount, 'original_method', p.method, 'reason', p.void_reason, 'replacement_payment_id', p.replacement_payment_id),
       p.voided_at
FROM order_payments p
INNER JOIN orders o ON o.id = p.order_id
WHERE p.voided_at IS NOT NULL;
