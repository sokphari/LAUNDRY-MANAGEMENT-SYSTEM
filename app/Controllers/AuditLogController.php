<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\AuditLog;
use DateTimeImmutable;
use PDOException;

final class AuditLogController
{
    private const ACTIONS = [
        'order.created' => 'Order Created',
        'order.updated' => 'Order Updated',
        'order.status_changed' => 'Status Changed',
        'order.cancelled' => 'Order Cancelled',
        'payment.recorded' => 'Payment Recorded',
        'payment.corrected' => 'Payment Corrected',
        'service.price_updated' => 'Service Price Updated',
        'service.updated' => 'Service Updated',
        'service.created' => 'Service Created',
        'service.activated' => 'Service Activated',
        'service.deactivated' => 'Service Deactivated',
        'customer.updated' => 'Customer Updated',
        'customer.created' => 'Customer Created',
        'customer.deactivated' => 'Customer Deactivated',
        'refund.completed' => 'Refund Completed',
    ];

    private const MODULES = ['order' => 'Orders', 'payment' => 'Payments', 'service' => 'Services', 'customer' => 'Customers', 'refund' => 'Refunds'];

    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        if ((Auth::user()['role'] ?? '') !== 'admin') { http_response_code(403); echo 'You do not have permission to view audit logs.'; return; }

        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $requestedAction = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
        $action = array_key_exists($requestedAction, self::ACTIONS) ? $requestedAction : '';
        $requestedModule = isset($_GET['module']) && is_string($_GET['module']) ? $_GET['module'] : '';
        $module = array_key_exists($requestedModule, self::MODULES) ? $requestedModule : '';
        $requestedUser = filter_var($_GET['user'] ?? '', FILTER_VALIDATE_INT);
        $userId = is_int($requestedUser) && $requestedUser > 0 ? $requestedUser : '';
        $range = isset($_GET['range']) && is_string($_GET['range']) ? $_GET['range'] : '';
        if (!in_array($range, ['', 'today', 'yesterday', 'last7', 'custom'], true)) $range = '';

        $today = new DateTimeImmutable('today');
        $from = $to = '';
        if ($range === 'today') { $from = $today->format('Y-m-d'); $to = $today->modify('+1 day')->format('Y-m-d'); }
        elseif ($range === 'yesterday') { $from = $today->modify('-1 day')->format('Y-m-d'); $to = $today->format('Y-m-d'); }
        elseif ($range === 'last7') { $from = $today->modify('-6 days')->format('Y-m-d'); $to = $today->modify('+1 day')->format('Y-m-d'); }
        elseif ($range === 'custom') {
            $from = self::validDate($_GET['from'] ?? '');
            $to = self::validDate($_GET['to'] ?? '');
            if ($to !== '') $to = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
        }

        $filters = ['q' => $query, 'action' => $action, 'module' => $module, 'user' => $userId, 'from' => $from, 'to' => $to];
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = max(1, is_int($requestedPage) ? $requestedPage : 1);
        $perPage = 10;
        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        try {
            $database = Database::connect($databaseConfig);
            $staff = AuditLog::staffOptions($database);
            if ($userId !== '' && !in_array($userId, array_map(static fn (array $person): int => (int) $person['id'], $staff), true)) {
                $userId = '';
                $filters['user'] = '';
            }
            $total = AuditLog::count($database, $filters);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $logs = AuditLog::all($database, $filters, $perPage, ($page - 1) * $perPage);
        } catch (PDOException $exception) {
            file_put_contents(BASE_PATH . '/storage/logs/app.log', sprintf("[%s] [audit-logs] %s\n", date(DATE_ATOM), $exception->getMessage()), FILE_APPEND | LOCK_EX);
            http_response_code(500);
            if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => 'Audit log data is temporarily unavailable.']); return; }
            echo 'Audit log data is temporarily unavailable. Check the database and migrations.';
            return;
        }

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            foreach ($logs as &$log) {
                $details = is_string($log['details']) ? json_decode($log['details'], true) : null;
                $log['details_data'] = is_array($details) ? $details : null;
                $log['created_display'] = date('d M Y, H:i:s', strtotime((string) $log['created_at']));
                $log['action_label'] = self::ACTIONS[$log['action']] ?? ucwords(str_replace(['.', '_'], ' ', (string) $log['action']));
                $log['action_class'] = self::actionClass((string) $log['action']);
                $log['linked_order_id'] = $log['entity_type'] === 'order' ? (int) $log['entity_id'] : (int) ($details['order_id'] ?? 0);
            }
            unset($log);
            echo json_encode(['logs' => $logs, 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }
        View::render('audit-logs/index', [
            'title' => 'Audit Logs', 'user' => Auth::user(), 'csrfToken' => Csrf::token(), 'currentPath' => '/audit-logs',
            'logs' => $logs, 'query' => $query, 'action' => $action, 'actions' => self::ACTIONS,
            'modules' => self::MODULES, 'module' => $module, 'staff' => $staff, 'userId' => $userId,
            'range' => $range, 'fromDate' => $range === 'custom' ? self::validDate($_GET['from'] ?? '') : '',
            'toDate' => $range === 'custom' ? self::validDate($_GET['to'] ?? '') : '',
            'page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages,
        ]);
    }

    private static function validDate(mixed $value): string
    {
        if (!is_string($value) || $value === '') return '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public static function actionClass(string $action): string
    {
        if (in_array($action, ['order.created', 'payment.recorded', 'service.created', 'customer.created', 'service.activated'], true)) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
        if ($action === 'order.status_changed') return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300';
        if (in_array($action, ['service.price_updated', 'payment.corrected', 'order.updated', 'service.updated', 'customer.updated'], true)) return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
        if (in_array($action, ['order.cancelled', 'refund.completed', 'service.deactivated', 'customer.deactivated'], true)) return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
        return 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300';
    }
}
