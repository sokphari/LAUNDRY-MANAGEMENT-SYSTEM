<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use PDO;
use PDOException;

final class ReportController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        [$from, $to, $range, $fromDate, $toDate] = self::dateRange();
        try {
            $data = self::reportData(Database::connect($databaseConfig), $from, $to);
        } catch (PDOException $exception) {
            error_log('[reports] ' . $exception->getMessage());
            http_response_code(500);
            echo 'Report data is temporarily unavailable. Check the database connection and migrations.';
            return;
        }

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        View::render('reports/index', $data + [
            'title' => 'Reports', 'user' => Auth::user(), 'currentPath' => '/reports',
            'range' => $range, 'fromDate' => $fromDate, 'toDate' => $toDate,
            'pdfUrl' => self::exportUrl('pdf'), 'excelUrl' => self::exportUrl('csv'),
        ]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function export(array $databaseConfig): void
    {
        $format = isset($_GET['format']) && is_string($_GET['format']) ? $_GET['format'] : '';
        if (!in_array($format, ['pdf', 'csv'], true)) { http_response_code(400); echo 'Unsupported export format.'; return; }
        [, , $range, $fromDate, $toDate] = self::dateRange();
        $from = $fromDate !== '' ? $fromDate . ' 00:00:00' : null;
        $to = $toDate !== '' ? (new DateTimeImmutable($toDate))->modify('+1 day')->format('Y-m-d 00:00:00') : null;
        try { $data = self::reportData(Database::connect($databaseConfig), $from, $to); }
        catch (PDOException $exception) { error_log('[reports-export] ' . $exception->getMessage()); http_response_code(500); echo 'Report data is temporarily unavailable.'; return; }

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="laundry-report-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'wb');
            if ($out === false) { http_response_code(500); return; }
            fwrite($out, "\xEF\xBB\xBF");
            $csv = static function (array $row) use ($out): void {
                $safe = array_map(static function (mixed $value): mixed {
                    if (is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)) return "'" . $value;
                    return $value;
                }, $row);
                fputcsv($out, $safe);
            };
            $csv(['Laundry report', 'Date range', $range]);
            $csv([]);
            $csv(['Metric', 'Value']);
            foreach ($data['summary'] as $label => $value) $csv([$label, $value]);
            $csv([]);
            $csv(['Order status', 'Orders']);
            foreach ($data['statuses'] as $row) $csv([$row['label'], $row['count']]);
            $csv([]);
            $csv(['Payment method', 'Received (KHR)', 'Refunds (KHR)', 'Net (KHR)']);
            foreach ($data['methods'] as $row) $csv([$row['label'], $row['received'], $row['refunds'], $row['net']]);
            $csv([]);
            $csv(['Service', 'Quantity', 'Revenue (KHR)']);
            foreach ($data['services'] as $row) $csv([$row['service_name'], $row['quantity'], $row['revenue']]);
            fclose($out);
            return;
        }

        $periodLabel = $range === 'all' ? 'All time' : trim(($fromDate !== '' ? $fromDate : 'Start') . ' to ' . ($toDate !== '' ? $toDate : 'Present')) . ' (Asia/Phnom_Penh)';
        $actor = Auth::user();
        $preparedBy = $actor !== null ? (string)($actor['username'] ?? 'Staff') . ' (' . ucfirst((string)($actor['role'] ?? 'staff')) . ')' : 'Staff';
        $exportedAt = (new DateTimeImmutable('now', new \DateTimeZone('Asia/Phnom_Penh')))->format('d M Y, H:i') . ' ICT';
        $periodBadge = match ($range) { 'today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', 'month' => 'This month', 'custom' => 'Custom range', default => 'All time' };
        View::render('reports/export-pdf', ['report' => $data, 'periodLabel' => $periodLabel, 'periodBadge' => $periodBadge, 'preparedBy' => $preparedBy, 'exportedAt' => $exportedAt], 'payments-export');
    }

    /** @return array<string, mixed> */
    private static function reportData(PDO $db, ?string $from, ?string $to): array
    {
        $orderWhere = '1=1'; $orderParams = [];
        if ($from !== null) { $orderWhere .= ' AND o.received_at >= :order_from'; $orderParams['order_from'] = $from; }
        if ($to !== null) { $orderWhere .= ' AND o.received_at < :order_to'; $orderParams['order_to'] = $to; }
        $stmt = $db->prepare("SELECT COUNT(*) orders, COALESCE(SUM(o.total_amount),0) total, COALESCE(SUM(o.discount_amount),0) discounts FROM orders o WHERE $orderWhere");
        $stmt->execute($orderParams); $orders = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $paymentWhere = 'p.voided_at IS NULL'; $paymentParams = [];
        $refundWhere = 'p.voided_at IS NULL'; $refundParams = [];
        if ($from !== null) { $paymentWhere .= ' AND p.paid_at >= :payment_from'; $paymentParams['payment_from'] = $from; $refundWhere .= ' AND r.refunded_at >= :refund_from'; $refundParams['refund_from'] = $from; }
        if ($to !== null) { $paymentWhere .= ' AND p.paid_at < :payment_to'; $paymentParams['payment_to'] = $to; $refundWhere .= ' AND r.refunded_at < :refund_to'; $refundParams['refund_to'] = $to; }
        $stmt = $db->prepare("SELECT COALESCE(SUM(p.amount),0) amount FROM order_payments p WHERE $paymentWhere"); $stmt->execute($paymentParams); $received = (float) $stmt->fetchColumn();
        $stmt = $db->prepare("SELECT COALESCE(SUM(r.amount),0) amount FROM order_payment_refunds r INNER JOIN order_payments p ON p.id=r.payment_id WHERE $refundWhere"); $stmt->execute($refundParams); $refunds = (float) $stmt->fetchColumn();

        $statusStmt = $db->prepare("SELECT o.status, COUNT(*) count FROM orders o WHERE $orderWhere GROUP BY o.status"); $statusStmt->execute($orderParams);
        $statusCounts = array_fill_keys(['received','processing','ready','collected','cancelled'], 0);
        foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) if (array_key_exists($row['status'], $statusCounts)) $statusCounts[$row['status']] = (int)$row['count'];
        $statuses = [];
        foreach (['received'=>'Received','processing'=>'Processing','ready'=>'Ready','collected'=>'Collected','cancelled'=>'Cancelled'] as $key=>$label) $statuses[]=['label'=>$label,'count'=>$statusCounts[$key]];

        $methodStmt = $db->prepare("SELECT UPPER(p.method) method, COALESCE(SUM(p.amount),0) amount FROM order_payments p WHERE $paymentWhere GROUP BY p.method"); $methodStmt->execute($paymentParams);
        $methodSums = ['CASH'=>0.0,'KHQR'=>0.0]; foreach ($methodStmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $key = strtoupper((string)$row['method']); if (isset($methodSums[$key])) $methodSums[$key] = (float)$row['amount']; }
        $refundMethodStmt = $db->prepare("SELECT UPPER(r.refund_method) method, COALESCE(SUM(r.amount),0) amount FROM order_payment_refunds r INNER JOIN order_payments p ON p.id=r.payment_id WHERE $refundWhere GROUP BY r.refund_method"); $refundMethodStmt->execute($refundParams);
        $refundMethodSums = ['CASH'=>0.0,'KHQR'=>0.0]; foreach ($refundMethodStmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $key = strtoupper((string)$row['method']); if (isset($refundMethodSums[$key])) $refundMethodSums[$key] = (float)$row['amount']; }
        $methods=[]; foreach (['CASH'=>'Cash','KHQR'=>'KHQR'] as $key=>$label) $methods[]=['label'=>$label,'received'=>$methodSums[$key],'refunds'=>$refundMethodSums[$key],'net'=>$methodSums[$key]-$refundMethodSums[$key]];

        $serviceStmt = $db->prepare("SELECT oi.service_name, oi.billing_unit, SUM(oi.quantity) quantity, SUM(oi.line_total) revenue FROM order_items oi INNER JOIN orders o ON o.id=oi.order_id WHERE $orderWhere AND o.status <> 'cancelled' GROUP BY oi.service_name, oi.billing_unit ORDER BY revenue DESC LIMIT 4");
        $serviceStmt->execute($orderParams); $services=$serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($services as &$service) { $service['quantity']=(float)$service['quantity']; $service['revenue']=(float)$service['revenue']; } unset($service);
        $balanceStmt = $db->prepare(
            "SELECT COALESCE(SUM(GREATEST(o.total_amount - COALESCE(paid.amount,0) + COALESCE(refunded.amount,0),0)),0)
             FROM orders o
             LEFT JOIN (SELECT p.order_id, SUM(p.amount) amount FROM order_payments p WHERE p.voided_at IS NULL GROUP BY p.order_id) paid ON paid.order_id=o.id
             LEFT JOIN (SELECT p.order_id, SUM(r.amount) amount FROM order_payment_refunds r INNER JOIN order_payments p ON p.id=r.payment_id AND p.voided_at IS NULL GROUP BY p.order_id) refunded ON refunded.order_id=o.id
             WHERE $orderWhere"
        );
        $balanceStmt->execute($orderParams); $outstanding = (float)$balanceStmt->fetchColumn();
        $snapshotStmt = $db->query(
            "SELECT COALESCE(SUM(GREATEST(o.total_amount - COALESCE(paid.amount,0) + COALESCE(refunded.amount,0),0)),0)
             FROM orders o
             LEFT JOIN (SELECT p.order_id, SUM(p.amount) amount FROM order_payments p WHERE p.voided_at IS NULL GROUP BY p.order_id) paid ON paid.order_id=o.id
             LEFT JOIN (SELECT p.order_id, SUM(r.amount) amount FROM order_payment_refunds r INNER JOIN order_payments p ON p.id=r.payment_id AND p.voided_at IS NULL GROUP BY p.order_id) refunded ON refunded.order_id=o.id
             WHERE o.status <> 'cancelled'"
        );
        $snapshotOutstanding = (float)$snapshotStmt->fetchColumn();
        $summary = ['Orders'=>(int)$orders['orders'],'Order value (KHR)'=>(float)$orders['total'],'Discounts (KHR)'=>(float)$orders['discounts'],'Payments received (KHR)'=>$received,'Refunds recorded (KHR)'=>$refunds,'Net received (KHR)'=>$received-$refunds,'Outstanding balance (KHR)'=>$outstanding];
        return ['summary'=>$summary,'statuses'=>$statuses,'methods'=>$methods,'services'=>$services,'snapshotOutstanding'=>$snapshotOutstanding];
    }

    /** @return array{0:?string,1:?string,2:string,3:string,4:string} */
    private static function dateRange(): array
    {
        $range = isset($_GET['range']) && is_string($_GET['range']) ? $_GET['range'] : 'month';
        if (!in_array($range, ['all','today','yesterday','week','month','custom'], true)) $range='month';
        $today = new DateTimeImmutable('today'); $fromDate=''; $toDate='';
        if ($range==='today') $fromDate=$toDate=$today->format('Y-m-d');
        elseif ($range==='yesterday') $fromDate=$toDate=$today->modify('-1 day')->format('Y-m-d');
        elseif ($range==='week') { $fromDate=$today->modify('-'.((int)$today->format('N')-1).' days')->format('Y-m-d'); $toDate=$today->format('Y-m-d'); }
        elseif ($range==='month') { $fromDate=$today->modify('first day of this month')->format('Y-m-d'); $toDate=$today->format('Y-m-d'); }
        elseif ($range==='custom') { $fromDate=self::validDate($_GET['from']??''); $toDate=self::validDate($_GET['to']??''); if ($fromDate!=='' && $toDate!=='' && $fromDate>$toDate) [$fromDate,$toDate]=[$toDate,$fromDate]; }
        $from = $fromDate!=='' ? $fromDate.' 00:00:00' : null;
        $to = $toDate!=='' ? (new DateTimeImmutable($toDate))->modify('+1 day')->format('Y-m-d 00:00:00') : null;
        return [$from,$to,$range,$fromDate,$toDate];
    }

    private static function validDate(mixed $date): string { if (!is_string($date)||$date==='') return ''; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d&&$d->format('Y-m-d')===$date?$date:''; }
    private static function exportUrl(string $format): string { $query=$_GET; $query['format']=$format; return '/reports/export?'.http_build_query($query); }
}
