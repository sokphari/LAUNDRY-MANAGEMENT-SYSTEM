<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DashboardService
{
    /** @return array<string, mixed> */
    public static function data(PDO $database, int $requestedYear, string $range, string $fromDate = '', string $toDate = '', int $userId = 0, string $role = 'admin'): array
    {
        $currentYear = (int) date('Y');
        $years = range($currentYear, $currentYear - 4);
        $selectedYear = in_array($requestedYear, $years, true) ? $requestedYear : $currentYear;

        [$rangeStart, $rangeEnd, $rangeLabel, $fromDate, $toDate] = self::resolveRange($range, $fromDate, $toDate);
        if ($role !== 'admin') {
            return self::staffData($database, $selectedYear, $range, $rangeStart, $rangeEnd, $rangeLabel, $fromDate, $toDate, $userId);
        }
        $customerDateFilter = '1 = 1';
        $orderDateFilter = '1 = 1';
        $paymentDateFilter = '1 = 1';
        $refundDateFilter = '1 = 1';
        $customerParameters = [];
        $orderParameters = [];
        $paymentRangeParameters = [];
        $refundRangeParameters = [];
        if ($rangeStart !== null || $rangeEnd !== null) {
            $customerDateFilter = self::dateCondition('c.created_at', 'customer', $rangeStart, $rangeEnd, $customerParameters);
            $orderDateFilter = self::dateCondition('o.received_at', 'order', $rangeStart, $rangeEnd, $orderParameters);
            $paymentDateFilter = self::dateCondition('p.paid_at', 'payment_range', $rangeStart, $rangeEnd, $paymentRangeParameters);
            $refundDateFilter = self::dateCondition('r.refunded_at', 'refund_range', $rangeStart, $rangeEnd, $refundRangeParameters);
        }

        $customerStatement = $database->prepare('SELECT COUNT(*) FROM customers c WHERE ' . $customerDateFilter);
        $customerStatement->execute($customerParameters);
        $customerCount = (int) $customerStatement->fetchColumn();
        $orderCountStatement = $database->prepare('SELECT COUNT(*) FROM orders o WHERE ' . $orderDateFilter);
        $orderCountStatement->execute($orderParameters);
        $ordersReceived = (int) $orderCountStatement->fetchColumn();
        $readyStatement = $database->prepare("SELECT COUNT(*) FROM orders o WHERE o.status = 'ready' AND " . $orderDateFilter);
        $readyStatement->execute($orderParameters);
        $readyOrders = (int) $readyStatement->fetchColumn();

        $statusCounts = array_fill_keys(['received', 'processing', 'ready', 'collected', 'cancelled'], 0);
        $statusStatement = $database->prepare('SELECT o.status, COUNT(*) AS total FROM orders o WHERE ' . $orderDateFilter . ' GROUP BY o.status');
        $statusStatement->execute($orderParameters);
        foreach ($statusStatement->fetchAll() as $row) {
            if (array_key_exists($row['status'], $statusCounts)) {
                $statusCounts[$row['status']] = (int) $row['total'];
            }
        }

        $periodPaymentStatement = $database->prepare(
            'SELECT COALESCE(SUM(activity.amount), 0) AS amount
             FROM (
                 SELECT p.amount AS amount FROM order_payments p
                 WHERE p.voided_at IS NULL AND ' . $paymentDateFilter . '
                 UNION ALL
                 SELECT -r.amount AS amount FROM order_payment_refunds r
                 INNER JOIN order_payments p ON p.id = r.payment_id AND p.voided_at IS NULL
                 WHERE ' . $refundDateFilter . '
             ) activity'
        );
        $periodPaymentStatement->execute($paymentRangeParameters + $refundRangeParameters);
        $periodNetPayments = (float) $periodPaymentStatement->fetchColumn();

        $statement = $database->prepare(
            'SELECT activity.month_number, SUM(activity.amount) AS amount
             FROM (
                 SELECT MONTH(p.paid_at) AS month_number, p.amount AS amount
                 FROM order_payments p
                 WHERE p.voided_at IS NULL AND p.paid_at >= :payment_start AND p.paid_at < :payment_end
                 UNION ALL
                 SELECT MONTH(r.refunded_at) AS month_number, -r.amount AS amount
                 FROM order_payment_refunds r
                 INNER JOIN order_payments p ON p.id = r.payment_id AND p.voided_at IS NULL
                 WHERE r.refunded_at >= :refund_start AND r.refunded_at < :refund_end
             ) activity
             GROUP BY activity.month_number'
        );
        $yearStart = sprintf('%04d-01-01 00:00:00', $selectedYear);
        $yearEnd = sprintf('%04d-01-01 00:00:00', $selectedYear + 1);
        $statement->execute([
            'payment_start' => $yearStart,
            'payment_end' => $yearEnd,
            'refund_start' => $yearStart,
            'refund_end' => $yearEnd,
        ]);
        $monthlyAmounts = array_fill(1, 12, 0.0);
        foreach ($statement->fetchAll() as $row) {
            $month = (int) $row['month_number'];
            if ($month >= 1 && $month <= 12) {
                $monthlyAmounts[$month] = (float) $row['amount'];
            }
        }
        $yearlyRevenue = [];
        foreach (range(1, 12) as $month) {
            $yearlyRevenue[] = [
                'month' => date('M', mktime(0, 0, 0, $month, 1, $selectedYear)),
                'amount' => $monthlyAmounts[$month],
            ];
        }
        $annualNetPayments = array_sum($monthlyAmounts);

        $labels = [
            'received' => 'Received',
            'processing' => 'Processing',
            'ready' => 'Ready',
            'collected' => 'Collected',
            'cancelled' => 'Cancelled',
        ];
        $colors = [
            'received' => '#7C3AED',
            'processing' => '#A78BFA',
            'ready' => '#F59E0B',
            'collected' => '#10B981',
            'cancelled' => '#A1A1AA',
        ];
        $orderStatuses = [];
        foreach ($labels as $status => $label) {
            $orderStatuses[] = ['label' => $label, 'value' => $statusCounts[$status], 'color' => $colors[$status]];
        }

        $readyNote = $range === 'all' ? 'Currently ready' : 'Received during ' . strtolower($rangeLabel);

        $data = [
            'isStaffDashboard' => false,
            'selectedYear' => $selectedYear,
            'years' => $years,
            'selectedRange' => $range,
            'rangeLabel' => $rangeLabel,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'stats' => [
                ['label' => 'Customers added', 'value' => number_format($customerCount), 'note' => $rangeLabel, 'tone' => 'violet'],
                ['label' => 'Net payments', 'value' => number_format($periodNetPayments) . '៛', 'note' => $rangeLabel . ' · payments minus refunds', 'tone' => 'emerald'],
                ['label' => 'Orders received', 'value' => number_format($ordersReceived), 'note' => $rangeLabel, 'tone' => 'blue'],
                ['label' => 'Ready for pickup', 'value' => number_format($readyOrders), 'note' => $readyNote, 'tone' => 'amber'],
            ],
            'orderStatuses' => $orderStatuses,
            'yearlyRevenue' => $yearlyRevenue,
        ];
        $data['staffActivities'] = self::staffActivities($database, $rangeStart, $rangeEnd);
        return $data;
    }

    /** @return array<string, mixed> */
    private static function staffData(PDO $database, int $selectedYear, string $range, ?string $rangeStart, ?string $rangeEnd, string $rangeLabel, string $fromDate, string $toDate, int $userId): array
    {
        $orderWhere = 'o.created_by = :order_staff_id';
        $orderParams = ['order_staff_id' => $userId];
        if ($rangeStart !== null) { $orderWhere .= ' AND o.received_at >= :order_from'; $orderParams['order_from'] = $rangeStart; }
        if ($rangeEnd !== null) { $orderWhere .= ' AND o.received_at < :order_to'; $orderParams['order_to'] = $rangeEnd; }
        $ordersStatement = $database->prepare("SELECT COUNT(*) FROM orders o WHERE $orderWhere");
        $ordersStatement->execute($orderParams);
        $ordersCount = (int) $ordersStatement->fetchColumn();
        $statusLogWhere = 'changed_by = :status_staff_id';
        $statusLogParams = ['status_staff_id' => $userId];
        if ($rangeStart !== null) { $statusLogWhere .= ' AND changed_at >= :status_from'; $statusLogParams['status_from'] = $rangeStart; }
        if ($rangeEnd !== null) { $statusLogWhere .= ' AND changed_at < :status_to'; $statusLogParams['status_to'] = $rangeEnd; }
        $statusUpdatesStatement = $database->prepare("SELECT COUNT(*) FROM order_status_logs WHERE $statusLogWhere");
        $statusUpdatesStatement->execute($statusLogParams);
        $statusUpdates = (int) $statusUpdatesStatement->fetchColumn();
        $collectedWhere = $statusLogWhere . " AND new_status = 'collected'";
        $collectedStatement = $database->prepare("SELECT COUNT(DISTINCT order_id) FROM order_status_logs WHERE $collectedWhere");
        $collectedStatement->execute($statusLogParams);
        $collectedOrders = (int) $collectedStatement->fetchColumn();
        $readyStatement = $database->prepare("SELECT COUNT(*) FROM orders o WHERE $orderWhere AND o.status = 'ready'");
        $readyStatement->execute($orderParams);
        $readyCount = (int) $readyStatement->fetchColumn();
        $statusCounts = array_fill_keys(['received', 'processing', 'ready', 'collected', 'cancelled'], 0);
        $statusStatement = $database->prepare("SELECT o.status, COUNT(*) AS total FROM orders o WHERE $orderWhere GROUP BY o.status");
        $statusStatement->execute($orderParams);
        foreach ($statusStatement->fetchAll() as $row) if (array_key_exists($row['status'], $statusCounts)) $statusCounts[$row['status']] = (int) $row['total'];

        $paymentWhere = 'p.received_by = :payment_staff_id AND p.voided_at IS NULL';
        $paymentParams = ['payment_staff_id' => $userId];
        if ($rangeStart !== null) { $paymentWhere .= ' AND p.paid_at >= :payment_from'; $paymentParams['payment_from'] = $rangeStart; }
        if ($rangeEnd !== null) { $paymentWhere .= ' AND p.paid_at < :payment_to'; $paymentParams['payment_to'] = $rangeEnd; }
        $refundWhere = 'r.refunded_by = :refund_staff_id AND p.voided_at IS NULL';
        $refundParams = ['refund_staff_id' => $userId];
        if ($rangeStart !== null) { $refundWhere .= ' AND r.refunded_at >= :refund_from'; $refundParams['refund_from'] = $rangeStart; }
        if ($rangeEnd !== null) { $refundWhere .= ' AND r.refunded_at < :refund_to'; $refundParams['refund_to'] = $rangeEnd; }
        $netStatement = $database->prepare(
            "SELECT COALESCE((SELECT SUM(p.amount) FROM order_payments p WHERE $paymentWhere),0)
             - COALESCE((SELECT SUM(r.amount) FROM order_payment_refunds r INNER JOIN order_payments p ON p.id = r.payment_id WHERE $refundWhere),0)"
        );
        $netStatement->execute($paymentParams + $refundParams);
        $netPayments = (float) $netStatement->fetchColumn();
        $paymentsStatement = $database->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM order_payments p WHERE $paymentWhere");
        $paymentsStatement->execute($paymentParams);
        $paymentsReceived = (float) $paymentsStatement->fetchColumn();

        $yearStatement = $database->prepare(
            'SELECT activity.month_number, SUM(activity.amount) AS amount FROM (
                SELECT MONTH(p.paid_at) month_number, p.amount amount FROM order_payments p WHERE p.received_by = :payment_user AND p.voided_at IS NULL AND p.paid_at >= :payment_start AND p.paid_at < :payment_end
                UNION ALL
                SELECT MONTH(r.refunded_at) month_number, -r.amount amount FROM order_payment_refunds r INNER JOIN order_payments p ON p.id = r.payment_id WHERE r.refunded_by = :refund_user AND p.voided_at IS NULL AND r.refunded_at >= :refund_start AND r.refunded_at < :refund_end
            ) activity GROUP BY activity.month_number'
        );
        $yearStart = sprintf('%04d-01-01 00:00:00', $selectedYear);
        $yearEnd = sprintf('%04d-01-01 00:00:00', $selectedYear + 1);
        $yearStatement->execute(['payment_user' => $userId, 'payment_start' => $yearStart, 'payment_end' => $yearEnd, 'refund_user' => $userId, 'refund_start' => $yearStart, 'refund_end' => $yearEnd]);
        $monthlyAmounts = array_fill(1, 12, 0.0);
        foreach ($yearStatement->fetchAll() as $row) $monthlyAmounts[(int) $row['month_number']] = (float) $row['amount'];
        $yearlyRevenue = [];
        foreach (range(1, 12) as $month) $yearlyRevenue[] = ['month' => date('M', mktime(0, 0, 0, $month, 1, $selectedYear)), 'amount' => $monthlyAmounts[$month]];

        $activityWhere = 'a.user_id = :activity_user';
        $activityParams = ['activity_user' => $userId];
        if ($rangeStart !== null) { $activityWhere .= ' AND a.created_at >= :activity_from'; $activityParams['activity_from'] = $rangeStart; }
        if ($rangeEnd !== null) { $activityWhere .= ' AND a.created_at < :activity_to'; $activityParams['activity_to'] = $rangeEnd; }
        $activitiesStatement = $database->prepare("SELECT a.action, a.summary, a.entity_type, a.entity_id, a.details, a.created_at FROM audit_logs a WHERE $activityWhere ORDER BY a.created_at DESC, a.id DESC LIMIT 8");
        $activitiesStatement->execute($activityParams);
        $activities = array_map(static function (array $row): array {
            $details = is_string($row['details']) ? json_decode($row['details'], true) : null;
            $row['linked_order_id'] = $row['entity_type'] === 'order' ? (int) $row['entity_id'] : (int) (is_array($details) ? ($details['order_id'] ?? 0) : 0);
            $row['created_display'] = date('d M Y, H:i', strtotime((string) $row['created_at']));
            return $row;
        }, $activitiesStatement->fetchAll());

        $paymentsStatement = $database->prepare(
            'SELECT p.id, p.amount, p.method, p.paid_at, o.id order_id, o.order_code, c.name customer_name
             FROM order_payments p INNER JOIN orders o ON o.id = p.order_id INNER JOIN customers c ON c.id = o.customer_id
             WHERE p.received_by = :staff_id AND p.voided_at IS NULL' . ($rangeStart !== null ? ' AND p.paid_at >= :from_date' : '') . ($rangeEnd !== null ? ' AND p.paid_at < :to_date' : '') . ' ORDER BY p.paid_at DESC, p.id DESC LIMIT 8'
        );
        $paymentsParams = ['staff_id' => $userId];
        if ($rangeStart !== null) $paymentsParams['from_date'] = $rangeStart;
        if ($rangeEnd !== null) $paymentsParams['to_date'] = $rangeEnd;
        $paymentsStatement->execute($paymentsParams);
        $payments = $paymentsStatement->fetchAll();

        $workStatement = $database->prepare("SELECT o.id, o.order_code, o.status, o.expected_pickup_at, c.name customer_name FROM orders o INNER JOIN customers c ON c.id = o.customer_id WHERE o.created_by = :staff_id AND o.status IN ('received','processing','ready') ORDER BY FIELD(o.status,'ready','processing','received'), o.expected_pickup_at ASC LIMIT 8");
        $workStatement->execute(['staff_id' => $userId]);
        $work = $workStatement->fetchAll();

        $labels = ['received' => 'Received', 'processing' => 'Processing', 'ready' => 'Ready', 'collected' => 'Collected', 'cancelled' => 'Cancelled'];
        $colors = ['received' => '#7C3AED', 'processing' => '#A78BFA', 'ready' => '#F59E0B', 'collected' => '#10B981', 'cancelled' => '#A1A1AA'];
        $orderStatuses = [];
        foreach ($labels as $key => $label) $orderStatuses[] = ['label' => $label, 'value' => $statusCounts[$key], 'color' => $colors[$key]];
        return [
            'selectedYear' => $selectedYear, 'years' => range((int) date('Y'), (int) date('Y') - 4), 'selectedRange' => $range, 'rangeLabel' => $rangeLabel, 'fromDate' => $fromDate, 'toDate' => $toDate,
            'isStaffDashboard' => true,
            'stats' => [
                ['label' => 'My Orders Created', 'value' => number_format($ordersCount), 'note' => $rangeLabel, 'tone' => 'violet'],
                ['label' => 'Payments I Received', 'value' => number_format($paymentsReceived) . '៛', 'note' => $rangeLabel, 'tone' => 'emerald'],
                ['label' => 'Status Updates', 'value' => number_format($statusUpdates), 'note' => $rangeLabel, 'tone' => 'blue'],
                ['label' => 'Orders I Collected', 'value' => number_format($collectedOrders), 'note' => $rangeLabel, 'tone' => 'amber'],
            ],
            'orderStatuses' => $orderStatuses, 'yearlyRevenue' => $yearlyRevenue, 'staffActivities' => [], 'recentActivities' => $activities, 'personalPayments' => $payments, 'personalWork' => $work,
        ];
    }

    private static function activityCount(PDO $database, int $userId, ?string $from, ?string $to): int
    {
        $where = 'user_id = :user_id'; $parameters = ['user_id' => $userId];
        if ($from !== null) { $where .= ' AND created_at >= :from_date'; $parameters['from_date'] = $from; }
        if ($to !== null) { $where .= ' AND created_at < :to_date'; $parameters['to_date'] = $to; }
        $statement = $database->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where"); $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    private static function staffActivities(PDO $database, ?string $from, ?string $to): array
    {
        $staff = $database->query("SELECT id, full_name, username FROM users WHERE role = 'staff' ORDER BY is_active DESC, full_name")->fetchAll();
        $rows = [];
        foreach ($staff as $person) {
            $id = (int) $person['id'];
            $orderWhere = 'created_by = :staff_id'; $orderParams = ['staff_id' => $id];
            if ($from !== null) { $orderWhere .= ' AND received_at >= :from_date'; $orderParams['from_date'] = $from; }
            if ($to !== null) { $orderWhere .= ' AND received_at < :to_date'; $orderParams['to_date'] = $to; }
            $orderStatement = $database->prepare("SELECT COUNT(*) FROM orders WHERE $orderWhere"); $orderStatement->execute($orderParams);
            $paymentWhere = 'received_by = :staff_id AND voided_at IS NULL'; $paymentParams = ['staff_id' => $id];
            if ($from !== null) { $paymentWhere .= ' AND paid_at >= :from_date'; $paymentParams['from_date'] = $from; }
            if ($to !== null) { $paymentWhere .= ' AND paid_at < :to_date'; $paymentParams['to_date'] = $to; }
            $paymentStatement = $database->prepare("SELECT COALESCE(SUM(amount),0) FROM order_payments WHERE $paymentWhere"); $paymentStatement->execute($paymentParams);
            $refundWhere = 'r.refunded_by = :staff_id AND p.voided_at IS NULL'; $refundParams = ['staff_id' => $id];
            if ($from !== null) { $refundWhere .= ' AND refunded_at >= :from_date'; $refundParams['from_date'] = $from; }
            if ($to !== null) { $refundWhere .= ' AND refunded_at < :to_date'; $refundParams['to_date'] = $to; }
            $refundStatement = $database->prepare("SELECT COALESCE(SUM(r.amount),0) FROM order_payment_refunds r INNER JOIN order_payments p ON p.id = r.payment_id WHERE $refundWhere"); $refundStatement->execute($refundParams);
            $activityWhere = 'user_id = :staff_id'; $activityParams = ['staff_id' => $id];
            if ($from !== null) { $activityWhere .= ' AND created_at >= :from_date'; $activityParams['from_date'] = $from; }
            if ($to !== null) { $activityWhere .= ' AND created_at < :to_date'; $activityParams['to_date'] = $to; }
            $latest = $database->prepare("SELECT summary, created_at FROM audit_logs WHERE $activityWhere ORDER BY created_at DESC, id DESC LIMIT 1"); $latest->execute($activityParams);
            $lastActivity = $latest->fetch() ?: null;
            $rows[] = ['user_id' => $id, 'full_name' => $person['full_name'], 'username' => $person['username'], 'orders' => (int) $orderStatement->fetchColumn(), 'payments' => (float) $paymentStatement->fetchColumn() - (float) $refundStatement->fetchColumn(), 'last_activity' => $lastActivity['summary'] ?? 'No activity in this date range', 'last_activity_at' => isset($lastActivity['created_at']) ? date('d M Y, H:i', strtotime((string) $lastActivity['created_at'])) : '—'];
        }
        return $rows;
    }

    /** @return array{0: ?string, 1: ?string, 2: string, 3: string, 4: string} */
    private static function resolveRange(string $range, string $fromDate, string $toDate): array
    {
        $today = new \DateTimeImmutable('today');
        if ($range === 'today') {
            return [$today->format('Y-m-d H:i:s'), $today->modify('+1 day')->format('Y-m-d H:i:s'), 'Today', '', ''];
        }
        if ($range === 'yesterday') {
            $yesterday = $today->modify('-1 day');
            return [$yesterday->format('Y-m-d H:i:s'), $today->format('Y-m-d H:i:s'), 'Yesterday', '', ''];
        }
        if ($range === 'week') {
            $start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
            return [$start->format('Y-m-d H:i:s'), $start->modify('+7 days')->format('Y-m-d H:i:s'), 'This week', '', ''];
        }
        if ($range === 'month') {
            $start = $today->modify('first day of this month');
            return [$start->format('Y-m-d H:i:s'), $start->modify('first day of next month')->format('Y-m-d H:i:s'), 'This month', '', ''];
        }
        if ($range === 'custom') {
            $from = self::parseDate($fromDate);
            $to = self::parseDate($toDate);
            if ($from === null && $to === null) {
                $from = $today->modify('first day of this month');
                $to = $today;
                $fromDate = $from->format('Y-m-d');
                $toDate = $to->format('Y-m-d');
            }
            if ($from !== null && $to !== null && $from > $to) {
                [$from, $to] = [$to, $from];
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
            $label = ($fromDate !== '' ? $fromDate : 'Start') . ' – ' . ($toDate !== '' ? $toDate : 'Present');
            return [
                $from?->format('Y-m-d H:i:s'),
                $to?->modify('+1 day')->format('Y-m-d H:i:s'),
                'Custom · ' . $label,
                $fromDate,
                $toDate,
            ];
        }

        return [null, null, 'All time', '', ''];
    }

    private static function parseDate(string $date): ?\DateTimeImmutable
    {
        if ($date === '') return null;
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }

    /** @param array<string, string> $parameters */
    private static function dateCondition(string $column, string $prefix, ?string $start, ?string $end, array &$parameters): string
    {
        $conditions = [];
        if ($start !== null) {
            $key = $prefix . '_from';
            $conditions[] = $column . ' >= :' . $key;
            $parameters[$key] = $start;
        }
        if ($end !== null) {
            $key = $prefix . '_to';
            $conditions[] = $column . ' < :' . $key;
            $parameters[$key] = $end;
        }
        return $conditions === [] ? '1 = 1' : implode(' AND ', $conditions);
    }
}
