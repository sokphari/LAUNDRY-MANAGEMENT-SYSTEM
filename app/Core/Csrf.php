<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function verify(?string $submittedToken): bool
    {
        $storedToken = $_SESSION['_csrf_token'] ?? null;

        return is_string($storedToken)
            && is_string($submittedToken)
            && hash_equals($storedToken, $submittedToken);
    }
}
