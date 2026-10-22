<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class PaymentTransaction
{
    /** @param array<string, string|int> $filters
     * @return list<array<string, mixed>>
     */
    public static function all(PDO $database, array $filters, int $limit = 10, int $offset = 0): array
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare(
            'SELECT t.transaction_id, t.transaction_type, t.transaction_status, t.payment_id, t.order_id, t.amount, t.method, t.occurred_at, t.voided_at, t.void_reason, t.reason, t.refunded_amount,
                    o.order_code, o.status AS order_status, c.name AS customer_name, c.phone AS customer_phone, u.username
             FROM ' . self::transactions() . ' t
             INNER JOIN orders o ON o.id = t.order_id
             INNER JOIN customers c ON c.id = o.customer_id
             LEFT JOIN users u ON u.id = t.staff_id' . $where . '
             ORDER BY t.occurred_at DESC, t.transaction_id DESC LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array<string, string|int> $filters */
    public static function count(PDO $database, array $filters): int
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare(
            'SELECT COUNT(*) FROM ' . self::transactions() . ' t
             INNER JOIN orders o ON o.id = t.order_id
             INNER JOIN customers c ON c.id = o.customer_id' . $where
        );
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @param array<string, string|int> $filters
     * @return array{payments: float, refunds: float, pending: float, net: float}
     */
    public static function summary(PDO $database, array $filters): array
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare(
            'SELECT COALESCE(SUM(CASE WHEN t.transaction_type = \'payment\' AND t.voided_at IS NULL THEN t.amount ELSE 0 END), 0) AS payments,
                    COALESCE(SUM(CASE WHEN t.transaction_type = \'refund\' THEN t.amount ELSE 0 END), 0) AS refunds,
                    COALESCE(SUM(CASE WHEN t.transaction_type = \'pending\' THEN t.amount ELSE 0 END), 0) AS pending
             FROM ' . self::transactions() . ' t
             INNER JOIN orders o ON o.id = t.order_id
             INNER JOIN customers c ON c.id = o.customer_id' . $where
        );
        $statement->execute($parameters);
        $summary = $statement->fetch() ?: ['payments' => 0, 'refunds' => 0, 'pending' => 0];
        $payments = (float) $summary['payments'];
        $refunds = (float) $summary['refunds'];
        return ['payments' => $payments, 'refunds' => $refunds, 'pending' => (float) $summary['pending'], 'net' => $payments - $refunds];
    }

    /** @param array<string, string|int> $filters
     * @return array{cash: array{payments: float, refunds: float, net: float}, khqr: array{payments: float, refunds: float, net: float}, total: array{payments: float, refunds: float, net: float}}
     */
    public static function reconciliation(PDO $database, array $filters): array
    {
        $result = [
            'cash' => ['payments' => 0.0, 'refunds' => 0.0, 'net' => 0.0],
            'khqr' => ['payments' => 0.0, 'refunds' => 0.0, 'net' => 0.0],
        ];
        foreach ([
            ['table' => 'order_payments', 'method' => 'method', 'date' => 'paid_at', 'amount' => 'payments', 'extra' => 'voided_at IS NULL'],
            ['table' => 'order_payment_refunds', 'method' => 'refund_method', 'date' => 'refunded_at', 'amount' => 'refunds', 'extra' => '1 = 1'],
        ] as $source) {
            $conditions = [$source['extra']];
            $parameters = [];
            if (($filters['from'] ?? '') !== '') {
                $conditions[] = $source['date'] . ' >= :date_from';
                $parameters['date_from'] = (string) $filters['from'] . ' 00:00:00';
            }
            if (($filters['to'] ?? '') !== '') {
                $conditions[] = $source['date'] . ' < :date_to';
                $parameters['date_to'] = (string) $filters['to'] . ' 00:00:00';
            }
            $statement = $database->prepare(
                'SELECT ' . $source['method'] . ' AS method, COALESCE(SUM(amount), 0) AS amount FROM ' . $source['table'] .
                ' WHERE ' . implode(' AND ', $conditions) . ' GROUP BY ' . $source['method']
            );
            $statement->execute($parameters);
            foreach ($statement->fetchAll() as $row) {
                $method = (string) $row['method'];
                if (isset($result[$method])) $result[$method][$source['amount']] = (float) $row['amount'];
            }
        }
        $total = ['payments' => 0.0, 'refunds' => 0.0, 'net' => 0.0];
        foreach ($result as &$methodSummary) {
            $methodSummary['net'] = $methodSummary['payments'] - $methodSummary['refunds'];
            foreach (['payments', 'refunds', 'net'] as $key) $total[$key] += $methodSummary[$key];
        }
        unset($methodSummary);
        $result['total'] = $total;
        return $result;
    }

    /** @return array<string, mixed>|false */
    public static function findPayment(PDO $database, int $paymentId): array|false
    {
        $statement = $database->prepare(
            'SELECT p.id AS payment_id, p.order_id, p.amount, p.method, p.paid_at, p.voided_at, p.void_reason, p.replacement_payment_id,
                    p.received_by, receiver.username AS received_by_username,
                    o.order_code, o.received_at, o.expected_pickup_at, o.status AS order_status, o.total_amount,
                    c.name AS customer_name, c.phone AS customer_phone,
                    COALESCE((SELECT SUM(r.amount) FROM order_payment_refunds r WHERE r.payment_id = p.id), 0) AS payment_refunded_amount,
                    COALESCE((SELECT SUM(pay.amount - COALESCE((SELECT SUM(ref.amount) FROM order_payment_refunds ref WHERE ref.payment_id = pay.id), 0)) FROM order_payments pay WHERE pay.order_id = o.id AND pay.voided_at IS NULL), 0) AS net_paid,
                    COALESCE((SELECT SUM(ref.amount) FROM order_payment_refunds ref INNER JOIN order_payments pay ON pay.id = ref.payment_id WHERE pay.order_id = o.id AND pay.voided_at IS NULL), 0) AS total_refunded
             FROM order_payments p
             INNER JOIN orders o ON o.id = p.order_id
             INNER JOIN customers c ON c.id = o.customer_id
             INNER JOIN users receiver ON receiver.id = p.received_by
             WHERE p.id = :payment_id'
        );
        $statement->execute(['payment_id' => $paymentId]);
        $payment = $statement->fetch();
        if ($payment === false) return false;

        $items = $database->prepare(
            'SELECT oi.service_name, oi.quantity, oi.billing_unit, oi.line_total
             FROM order_items oi
             WHERE oi.order_id = :order_id ORDER BY oi.id'
        );
        $items->execute(['order_id' => $payment['order_id']]);
        $payment['items'] = $items->fetchAll();
        return $payment;
    }

    /** @return list<array{id: int, username: string, role: string}> */
    public static function staffOptions(PDO $database): array
    {
        return $database->query(
            'SELECT DISTINCT u.id, u.username, u.role
             FROM users u
             WHERE EXISTS (SELECT 1 FROM order_payments p WHERE p.received_by = u.id)
                OR EXISTS (SELECT 1 FROM order_payment_refunds r WHERE r.refunded_by = u.id)
             ORDER BY u.username'
        )->fetchAll();
    }

    private static function transactions(): string
    {
        return '(SELECT p.id AS transaction_id, p.id AS payment_id, p.order_id, p.received_by AS staff_id, p.amount, p.method,
                       \'payment\' AS transaction_type, p.paid_at AS occurred_at, p.voided_at, p.void_reason, NULL AS reason,
                       COALESCE((SELECT SUM(r.amount) FROM order_payment_refunds r WHERE r.payment_id = p.id), 0) AS refunded_amount,
                       CASE WHEN p.voided_at IS NULL THEN \'recorded\' ELSE \'voided\' END AS transaction_status
                FROM order_payments p
                UNION ALL
                SELECT r.id AS transaction_id, r.payment_id, p.order_id, r.refunded_by AS staff_id, r.amount, r.refund_method AS method,
                       \'refund\' AS transaction_type, r.refunded_at AS occurred_at, NULL AS voided_at, NULL AS void_reason, r.reason,
                       0 AS refunded_amount, \'recorded\' AS transaction_status
                FROM order_payment_refunds r INNER JOIN order_payments p ON p.id = r.payment_id
                UNION ALL
                SELECT o.id AS transaction_id, NULL AS payment_id, o.id AS order_id, NULL AS staff_id,
                       GREATEST(0, ROUND(o.total_amount - COALESCE((SELECT SUM(p.amount - COALESCE((SELECT SUM(r.amount) FROM order_payment_refunds r WHERE r.payment_id = p.id), 0)) FROM order_payments p WHERE p.order_id = o.id AND p.voided_at IS NULL), 0), 2)) AS amount,
                       NULL AS method, \'pending\' AS transaction_type, o.received_at AS occurred_at, NULL AS voided_at, NULL AS void_reason, NULL AS reason, 0 AS refunded_amount,
                       \'pending\' AS transaction_status
                FROM orders o
                WHERE o.status <> \'cancelled\' AND o.total_amount > COALESCE((SELECT SUM(p.amount - COALESCE((SELECT SUM(r.amount) FROM order_payment_refunds r WHERE r.payment_id = p.id), 0)) FROM order_payments p WHERE p.order_id = o.id AND p.voided_at IS NULL), 0))';
    }

    /** @param array<string, string|int> $filters
     * @return array{0: string, 1: array<string, string|int>}
     */
    private static function where(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        $query = (string) ($filters['q'] ?? '');
        if ($query !== '') {
            $conditions[] = '(o.order_code LIKE :order_code OR c.name LIKE :customer_name OR c.phone LIKE :customer_phone OR CAST(t.transaction_id AS CHAR) LIKE :transaction_id OR COALESCE(t.reason, \'\') LIKE :refund_reason)';
            $term = '%' . $query . '%';
            $parameters += ['order_code' => $term, 'customer_name' => $term, 'customer_phone' => $term, 'transaction_id' => $term, 'refund_reason' => $term];
        }
        foreach (['type' => 't.transaction_type', 'status' => 't.transaction_status', 'method' => 't.method', 'staff' => 't.staff_id'] as $key => $column) {
            $value = $filters[$key] ?? '';
            if ($value !== '' && $value !== null) {
                $conditions[] = $column . ' = :' . $key;
                $parameters[$key] = $key === 'staff' ? (int) $value : (string) $value;
            }
        }
        if (($filters['from'] ?? '') !== '') {
            $conditions[] = 't.occurred_at >= :date_from';
            $parameters['date_from'] = (string) $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $conditions[] = 't.occurred_at < :date_to';
            $parameters['date_to'] = (string) $filters['to'] . ' 00:00:00';
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }
}
