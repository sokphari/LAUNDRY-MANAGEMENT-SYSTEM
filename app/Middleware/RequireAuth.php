<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Database;
use App\Services\PermissionService;
use PDOException;

final class RequireAuth
{
    /** @param array<string, mixed> $databaseConfig */
    public static function run(array $databaseConfig): bool
    {
        if (!Auth::check()) { header('Location: /login', true, 302); return false; }

        try {
            $user = Auth::user();
            $database = Database::connect($databaseConfig);
            $statement = $database->prepare('SELECT role, is_active, auth_version FROM users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int)$user['id']]);
            $current = $statement->fetch();
            if ($current !== false && (int)$current['is_active'] === 1 && (int)$current['auth_version'] === (int)($_SESSION['user']['auth_version'] ?? -1)) {
                $_SESSION['user']['role'] = $current['role'];
                $_SESSION['user']['permissions'] = PermissionService::effective($database, (int)$user['id'], (string)$current['role']);
                return true;
            }
        } catch (PDOException $exception) {
            error_log('[authorization] Could not verify the current account: ' . $exception->getMessage());
            http_response_code(503);
            echo 'Account access is temporarily unavailable. Please try again.';
            return false;
        }

        Auth::logout();
        header('Location: /login', true, 302);
        return false;
    }
}
