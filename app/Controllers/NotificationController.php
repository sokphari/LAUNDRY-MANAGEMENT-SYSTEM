<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\SecurityNotification;
use Throwable;

final class NotificationController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        $user = Auth::user();
        if ($user === null) {
            self::respondUnauthorized();
            return;
        }

        try {
            self::respond(SecurityNotification::forUser(Database::connect($databaseConfig), (int) $user['id'], (string) $user['role']));
        } catch (Throwable $exception) {
            self::logFailure($exception);
            http_response_code(503);
            self::respond(['error' => 'Notifications are temporarily unavailable.']);
        }
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function markRead(array $databaseConfig): void
    {
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419);
            self::respond(['error' => 'Your session expired. Reload and try again.']);
            return;
        }

        $user = Auth::user();
        if ($user === null) {
            self::respondUnauthorized();
            return;
        }

        try {
            $database = Database::connect($databaseConfig);
            SecurityNotification::markRead($database, (int) $user['id'], (string) $user['role']);
            self::respond(SecurityNotification::forUser($database, (int) $user['id'], (string) $user['role']));
        } catch (Throwable $exception) {
            self::logFailure($exception);
            http_response_code(503);
            self::respond(['error' => 'Notifications are temporarily unavailable.']);
        }
    }

    /** @param array<string, mixed> $data */
    private static function respond(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function respondUnauthorized(): void
    {
        http_response_code(401);
        self::respond(['error' => 'Your session has expired. Sign in again.']);
    }

    private static function logFailure(Throwable $exception): void
    {
        $message = '[notifications] ' . get_class($exception) . ': ' . $exception->getMessage();
        error_log($message);
        $directory = BASE_PATH . '/storage/logs';
        if (is_dir($directory) && is_writable($directory)) {
            file_put_contents($directory . '/app.log', sprintf("[%s] %s\n", date(DATE_ATOM), $message), FILE_APPEND | LOCK_EX);
        }
    }
}
