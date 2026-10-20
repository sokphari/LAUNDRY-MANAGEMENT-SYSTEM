<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\PaymentTransaction;
use DateTimeImmutable;
use PDOException;

final class PaymentController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        [$filters, $displayFilters] = self::filtersFromRequest();
        $pageNumber = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = max(1, is_int($pageNumber) ? $pageNumber : 1);
        $perPage = 10;
        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        try {
            $database = Database::connect($databaseConfig);
            $staff = PaymentTransaction::staffOptions($database);
            if ($filters['staff'] !== '' && !in_array((int) $filters['staff'], array_map(static fn (array $person): int => (int) $person['id'], $staff), true)) {
                $filters['staff'] = '';
                $displayFilters['staff'] = '';
            }
            $total = PaymentTransaction::count($database, $filters);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $transactions = PaymentTransaction::all($database, $filters, $perPage, ($page - 1) * $perPage);
            $summary = PaymentTransaction::summary($database, $filters);
            $reconciliation = PaymentTransaction::reconciliation($database, $filters);
        } catch (PDOException $exception) {
            file_put_contents(BASE_PATH . '/storage/logs/app.log', sprintf("[%s] [payments] %s\n", date(DATE_ATOM), $exception->getMessage()), FILE_APPEND | LOCK_EX);
            http_response_code(500);
            if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => 'Payment data is temporarily unavailable.']); return; }
            echo 'Payment data is temporarily unavailable. Check the database and migrations.';
            return;
        }

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            foreach ($transactions as &$transaction) {
                $transaction['occurred_display'] = date('d M Y, H:i', strtotime((string) $transaction['occurred_at']));
            }
            unset($transaction);
            echo json_encode(['transactions' => $transactions, 'summary' => $summary, 'reconciliation' => $reconciliation, 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        View::render('payments/index', [
            'title' => 'Payments', 'user' => Auth::user(), 'csrfToken' => Csrf::token(), 'currentPath' => '/payments',
            'transactions' => $transactions, 'summary' => $summary, 'filters' => $displayFilters,
            'reconciliation' => $reconciliation,
            'staff' => $staff, 'page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages,
            'pdfExportUrl' => self::exportUrl('pdf'), 'excelExportUrl' => self::exportUrl('xlsx'),
        ]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function export(array $databaseConfig): void
    {
        $format = self::queryString('format');
        if (!in_array($format, ['pdf', 'xlsx'], true)) {
            http_response_code(400);
            echo 'Unsupported export format.';
            return;
        }

        [$filters, $displayFilters] = self::filtersFromRequest();
        try {
            $database = Database::connect($databaseConfig);
            $staff = PaymentTransaction::staffOptions($database);
            if ($filters['staff'] !== '' && !in_array((int) $filters['staff'], array_map(static fn (array $person): int => (int) $person['id'], $staff), true)) {
                $filters['staff'] = '';
            }
            $total = PaymentTransaction::count($database, $filters);
            $transactions = [];
            for ($offset = 0; $offset < $total; $offset += 100) {
                array_push($transactions, ...PaymentTransaction::all($database, $filters, 100, $offset));
            }
            $summary = PaymentTransaction::summary($database, $filters);
            $reconciliation = PaymentTransaction::reconciliation($database, $filters);
        } catch (PDOException $exception) {
            file_put_contents(BASE_PATH . '/storage/logs/app.log', sprintf("[%s] [payments-export] %s\n", date(DATE_ATOM), $exception->getMessage()), FILE_APPEND | LOCK_EX);
            http_response_code(500);
            echo 'Payment data is temporarily unavailable.';
            return;
        }

        if ($format === 'xlsx') {
            self::sendExcel($transactions, $reconciliation);
            return;
        }

        View::render('payments/export-pdf', [
            'transactions' => $transactions,
            'summary' => $summary,
            'reconciliation' => $reconciliation,
            'filters' => $displayFilters,
            'exportedAt' => date('d M Y, H:i'),
        ], 'payments-export');
    }

    /** @return array{0: array<string, string|int>, 1: array<string, string|int>} */
    private static function filtersFromRequest(): array
    {
        $range = self::queryString('range');
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
        $staff = filter_var($_GET['staff'] ?? '', FILTER_VALIDATE_INT);
        $filters = [
            'q' => self::queryString('q'),
            'type' => in_array(self::queryString('type'), ['payment', 'refund', 'pending'], true) ? self::queryString('type') : '',
            'status' => in_array(self::queryString('status'), ['pending', 'recorded', 'voided'], true) ? self::queryString('status') : '',
            'method' => in_array(self::queryString('method'), ['cash', 'khqr'], true) ? self::queryString('method') : '',
            'staff' => is_int($staff) && $staff > 0 ? $staff : '',
            'from' => $from,
            'to' => $to,
        ];
        $display = $filters;
        $display['range'] = $range;
        $display['fromDate'] = $range === 'custom' ? self::validDate($_GET['from'] ?? '') : '';
        $display['toDate'] = $range === 'custom' ? self::validDate($_GET['to'] ?? '') : '';
        return [$filters, $display];
    }

    private static function exportUrl(string $format): string
    {
        $parameters = $_GET;
        unset($parameters['page']);
        $parameters['format'] = $format;
        return '/payments/export?' . http_build_query($parameters);
    }

    /** @param list<array<string, mixed>> $transactions */
    private static function sendExcel(array $transactions, array $reconciliation): void
    {
        if (!class_exists(\ZipArchive::class)) {
            http_response_code(500);
            echo 'Excel export requires the PHP ZIP extension.';
            return;
        }
        $tempPath = tempnam(sys_get_temp_dir(), 'payments-');
        if ($tempPath === false) {
            http_response_code(500);
            echo 'Could not prepare the Excel file.';
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            unlink($tempPath);
            http_response_code(500);
            echo 'Could not prepare the Excel file.';
            return;
        }
        $rows = [['Date & time', 'Status', 'Type', 'Transaction ID', 'Order', 'Customer', 'Phone', 'Method', 'Amount (KHR)', 'Staff', 'Reason']];
        foreach ($transactions as $transaction) {
            $isRefund = $transaction['transaction_type'] === 'refund';
            $rows[] = [
                date('Y-m-d H:i:s', strtotime((string) $transaction['occurred_at'])),
                ucfirst((string) $transaction['transaction_status']),
                $transaction['transaction_type'] === 'pending' ? 'Pending balance' : ($isRefund ? 'Refund' : 'Payment'),
                (string) $transaction['transaction_id'],
                (string) $transaction['order_code'],
                (string) $transaction['customer_name'],
                (string) $transaction['customer_phone'],
                (string) ($transaction['method'] ?? ''),
                $isRefund ? -(float) $transaction['amount'] : (float) $transaction['amount'],
                (string) ($transaction['username'] ?? ''),
                (string) ($transaction['reason'] ?? $transaction['void_reason'] ?? ''),
            ];
        }
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = self::excelColumn($columnIndex + 1) . ($rowIndex + 1);
                if (is_float($value) || is_int($value)) {
                    $cells .= '<c r="' . $reference . '"><v>' . number_format((float) $value, 2, '.', '') . '</v></c>';
                } else {
                    $cells .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlEscape((string) $value) . '</t></is></c>';
                }
            }
            $sheetRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>';
        $reconciliationRows = [['Payment Method Summary · selected date range only; other list filters do not affect these totals.'], ['Payment method', 'Payments received (KHR)', 'Refunds (KHR)', 'Net received (KHR)']];
        foreach (['cash' => 'Cash', 'khqr' => 'KHQR', 'total' => 'Total'] as $key => $label) {
            $reconciliationRows[] = [$label, $reconciliation[$key]['payments'], $reconciliation[$key]['refunds'], $reconciliation[$key]['net']];
        }
        $reconciliationSheetRows = '';
        foreach ($reconciliationRows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = self::excelColumn($columnIndex + 1) . ($rowIndex + 1);
                if (is_float($value) || is_int($value)) {
                    $cells .= '<c r="' . $reference . '"><v>' . number_format((float) $value, 2, '.', '') . '</v></c>';
                } else {
                    $cells .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlEscape((string) $value) . '</t></is></c>';
                }
            }
            $reconciliationSheetRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }
        $reconciliationSheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $reconciliationSheetRows . '</sheetData></worksheet>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Payments" sheetId="1" r:id="rId1"/><sheet name="Payment Method Summary" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/worksheets/sheet2.xml', $reconciliationSheet);
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="payments-' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . filesize($tempPath));
        readfile($tempPath);
        unlink($tempPath);
    }

    private static function excelColumn(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder) . $column;
            $number = intdiv($number - 1, 26);
        }
        return $column;
    }

    private static function xmlEscape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function queryString(string $key): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
    }

    private static function validDate(mixed $value): string
    {
        if (!is_string($value) || $value === '') return '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
}
