<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\LoginRateLimiter;
use PDOException;

final class AuthController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function showLogin(array $databaseConfig): void
    {
        if (Auth::check()) {
            header('Location: /dashboard', true, 302);
            return;
        }

        View::render('auth/login', [
            'title' => 'Sign in',
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['login_error'] ?? null,
            'oldLogin' => $_SESSION['old_login'] ?? '',
        ], 'guest');
        unset($_SESSION['login_error'], $_SESSION['old_login']);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function login(array $databaseConfig): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Your session expired. Please go back and try again.';
            return;
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($login === '' || $password === '') {
            self::redirectWithError('Enter your username or email and password.', $login);
            return;
        }

        try {
            $database = Database::connect($databaseConfig);
            $ip = self::clientIp();
            $blockedFor = LoginRateLimiter::blockedFor($database, $login, $ip);
            if ($blockedFor > 0) {
                self::redirectWithError('Too many sign-in attempts. Try again in ' . max(1, (int)ceil($blockedFor / 60)) . ' minute(s).', $login);
                return;
            }
            $user = User::findActiveByLogin($database, $login);
        } catch (PDOException $exception) {
            $logMessage = sprintf(
                "[%s] [authentication] Database connection/query failed: %s\n",
                date(DATE_ATOM),
                $exception->getMessage()
            );
            file_put_contents(BASE_PATH . '/storage/logs/app.log', $logMessage, FILE_APPEND | LOCK_EX);
            self::redirectWithError('Sign in is temporarily unavailable. Please try again.', $login);
            return;
        }

        if ($user === false || !password_verify($password, $user['password_hash'])) {
            if ($user === false) password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            try {
                LoginRateLimiter::recordFailure($database, $login, $ip);
                AuditLog::record($database, null, 'auth.login_failed', 'auth', null, 'Failed sign-in attempt', [
                    'login_identifier' => mb_substr($login, 0, 150),
                    'target_user_id' => $user === false ? null : (int)$user['id'],
                    'ip' => $ip,
                ]);
            } catch (PDOException $exception) {
                error_log('[authentication] Could not record failed sign-in: ' . $exception->getMessage());
            }
            self::redirectWithError('The username/email or password is incorrect.', $login);
            return;
        }

        try {
            LoginRateLimiter::clear($database, $login, $ip);
            AuditLog::record($database, (int)$user['id'], 'auth.login', 'auth', (int)$user['id'], 'User signed in', ['ip' => $ip]);
        } catch (PDOException $exception) {
            error_log('[authentication] Could not record successful sign-in: ' . $exception->getMessage());
        }

        Auth::login([
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'auth_version' => (int)$user['auth_version'],
        ]);
        unset($_SESSION['_csrf_token']);
        header('Location: /dashboard', true, 302);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function logout(array $databaseConfig): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Your session expired. Please go back and try again.';
            return;
        }

        $user = Auth::user();
        if ($user !== null) {
            try {
                AuditLog::record(Database::connect($databaseConfig), (int)$user['id'], 'auth.logout', 'auth', (int)$user['id'], 'User signed out', ['ip' => self::clientIp()]);
            } catch (PDOException $exception) {
                error_log('[authentication] Could not record sign-out: ' . $exception->getMessage());
            }
        }
        Auth::logout();
        header('Location: /login', true, 302);
    }

    private static function redirectWithError(string $message, string $login): void
    {
        $_SESSION['login_error'] = $message;
        $_SESSION['old_login'] = $login;
        header('Location: /login', true, 302);
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }
}
