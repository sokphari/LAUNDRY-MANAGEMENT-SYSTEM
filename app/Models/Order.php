<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Order
{
    /** @param array{query: string, status: string, from: string, to: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function all(PDO $database, array $filters, int $limit = 10, int $offset = 0): array
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare(
            'SELECT o.id, o.order_code, o.received_at, o.expected_pickup_at, o.status, o.total_amount,
                    c.name AS customer_name, c.phone AS customer_phone,
                    COALESCE((SELECT SUM(p.amount) FROM order_payments p WHERE p.order_id = o.id AND p.voided_at IS NULL), 0) AS paid_amount
             FROM orders o INNER JOIN customers c ON c.id = o.customer_id' . $where . '
             ORDER BY o.received_at DESC, o.id DESC LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array{query: string, status: string, from: string, to: string} $filters */
    public static function count(PDO $database, array $filters): int
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare('SELECT COUNT(*) FROM orders o INNER JOIN customers c ON c.id = o.customer_id' . $where);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @param array{query: string, status: string, from: string, to: string} $filters
     * @return array{0: string, 1: array<string, string>}
     */
    private static function where(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['query'] !== '') {
            $conditions[] = '(o.order_code LIKE :code_query OR c.name LIKE :name_query OR c.phone LIKE :phone_query)';
            $term = '%' . $filters['query'] . '%';
            $parameters += ['code_query' => $term, 'name_query' => $term, 'phone_query' => $term];
        }
        if ($filters['status'] !== 'all') {
            $conditions[] = 'o.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if ($filters['from'] !== '') {
            $conditions[] = 'o.received_at >= :date_from';
            $parameters['date_from'] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            $conditions[] = 'o.received_at < :date_to_exclusive';
            $parameters['date_to_exclusive'] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @return array{order: array<string, mixed>, items: list<array<string, mixed>>, statusLogs: list<array<string, mixed>>, payments: list<array<string, mixed>>}|false */
    public static function find(PDO $database, int $id): array|false
    {
        $statement = $database->prepare(
            'SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
                    u.username AS created_by_username,
                    COALESCE((SELECT SUM(p.amount) FROM order_payments p WHERE p.order_id = o.id AND p.voided_at IS NULL), 0) AS paid_amount
             FROM orders o
             INNER JOIN customers c ON c.id = o.customer_id
             INNER JOIN users u ON u.id = o.created_by
             WHERE o.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $order = $statement->fetch();
        if ($order === false) return false;

        $itemStatement = $database->prepare('SELECT service_id, service_name, billing_unit, quantity, unit_price, line_total FROM order_items WHERE order_id = :id ORDER BY id');
        $itemStatement->execute(['id' => $id]);
        $logStatement = $database->prepare(
            'SELECT l.old_status, l.new_status, l.notes, l.changed_at, u.username
             FROM order_status_logs l INNER JOIN users u ON u.id = l.changed_by
             WHERE l.order_id = :id ORDER BY l.id DESC'
        );
        $logStatement->execute(['id' => $id]);
        $paymentStatement = $database->prepare('SELECT p.id, p.amount, p.method, p.paid_at, p.voided_at, p.void_reason, p.replacement_payment_id, u.username, vu.username AS voided_by_username FROM order_payments p INNER JOIN users u ON u.id = p.received_by LEFT JOIN users vu ON vu.id = p.voided_by WHERE p.order_id = :id ORDER BY p.paid_at DESC, p.id DESC');
        $paymentStatement->execute(['id' => $id]);

        return ['order' => $order, 'items' => $itemStatement->fetchAll(), 'statusLogs' => $logStatement->fetchAll(), 'payments' => $paymentStatement->fetchAll()];
    }
}
