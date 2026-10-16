<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class RequireRole
{
    /** @param list<string> $roles */
    public static function run(array $roles): bool
    {
        $user = Auth::user();
        if ($user !== null && in_array($user['role'], $roles, true)) {
            return true;
        }

        http_response_code($user === null ? 401 : 403);
        echo $user === null ? '401 Unauthorized' : '403 Forbidden';
        return false;
    }
}
