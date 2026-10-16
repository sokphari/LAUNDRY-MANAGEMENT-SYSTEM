<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    /** @param array{id: int|string, full_name: string, username: string, role: string} $user */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id'], $_SESSION['user']['role']);
    }

    /** @return array{id: int|string, full_name: string, username: string, role: string}|null */
    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
    }
}
