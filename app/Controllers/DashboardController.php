<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\DashboardService;
use PDOException;

final class DashboardController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        $currentYear = (int) date('Y');
        $requestedYear = isset($_GET['year']) && is_string($_GET['year']) && ctype_digit($_GET['year'])
            ? (int) $_GET['year']
            : $currentYear;
        $requestedRange = isset($_GET['range']) && is_string($_GET['range']) ? $_GET['range'] : 'today';
        $range = in_array($requestedRange, ['all', 'today', 'yesterday', 'week', 'month', 'custom'], true) ? $requestedRange : 'today';
        $fromDate = self::validDate($_GET['from'] ?? null);
        $toDate = self::validDate($_GET['to'] ?? null);

        try {
            $actor = Auth::user();
            $dashboardData = DashboardService::data(Database::connect($databaseConfig), $requestedYear, $range, $fromDate, $toDate, (int) ($actor['id'] ?? 0), (string) ($actor['role'] ?? 'staff'));
        } catch (PDOException $exception) {
            error_log('[dashboard] ' . $exception->getMessage());
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                http_response_code(500);
                echo json_encode(['error' => 'Dashboard data is temporarily unavailable.']);
                return;
            }
            http_response_code(500);
            echo 'Dashboard data is temporarily unavailable. Check the database connection and migrations.';
            return;
        }

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($dashboardData, JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        View::render('dashboard/index', $dashboardData + [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'csrfToken' => Csrf::token(),
            'currentPath' => '/dashboard',
        ]);
    }

    private static function validDate(mixed $value): string
    {
        if (!is_string($value) || $value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }
}
