<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DashboardService
{
    /** @return array<string, mixed> */
    public static function data(PDO $database, int $requestedYear, string $range, string $fromDate = '', string $toDate = ''): array
    {
        $currentYear = (int) date('Y');
        $years = range($currentYear, $currentYear - 4);
        $selectedYear = in_array($requestedYear, $years, true) ? $requestedYear : $currentYear;

        [$rangeStart, $rangeEnd, $rangeLabel, $fromDate, $toDate] = self::resolveRange($range, $fromDate, $toDate);
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

        return [
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
