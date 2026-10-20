<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Database;
use App\Services\PermissionService;
use PDOException;

final class RequirePermission
{
    /** @param array<string, mixed> $databaseConfig */
    public static function run(array $databaseConfig, string $permission): bool
    {
        $user = Auth::user();
        if ($user === null) { http_response_code(401); echo '401 Unauthorized'; return false; }
        try {
            if (PermissionService::allows(Database::connect($databaseConfig), (int)$user['id'], (string)$user['role'], $permission)) return true;
            http_response_code(403);
            echo '403 Forbidden';
        } catch (PDOException $exception) {
            error_log('[authorization] Permission check failed: ' . $exception->getMessage());
            http_response_code(503);
            echo 'Permission checks are temporarily unavailable.';
        }
        return false;
    }
}
