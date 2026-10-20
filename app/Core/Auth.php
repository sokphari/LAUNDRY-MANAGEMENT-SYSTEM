<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const IDLE_TIMEOUT_SECONDS = 1800;
    private const ABSOLUTE_TIMEOUT_SECONDS = 43200;

    /** @param array{id: int|string, full_name: string, username: string, role: string, email?: ?string, auth_version?: int} $user */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['_auth_started_at'] = time();
        $_SESSION['_last_activity_at'] = time();
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
        if (!isset($_SESSION['user']['id'], $_SESSION['user']['role'])) return false;
        $now = time();
        $startedAt = (int)($_SESSION['_auth_started_at'] ?? 0);
        $lastActivity = (int)($_SESSION['_last_activity_at'] ?? 0);
        if ($startedAt < 1 || $lastActivity < 1 || $now - $lastActivity >= self::IDLE_TIMEOUT_SECONDS || $now - $startedAt >= self::ABSOLUTE_TIMEOUT_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
            return false;
        }
        $_SESSION['_last_activity_at'] = $now;
        return true;
    }

    /** @return array{id: int|string, full_name: string, username: string, role: string, email?: ?string}|null */
    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
    }
}
