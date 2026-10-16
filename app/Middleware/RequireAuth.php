<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class RequireAuth
{
    public static function run(): bool
    {
        if (Auth::check()) {
            return true;
        }

        header('Location: /login', true, 302);
        return false;
    }
}
