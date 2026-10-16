<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\User;
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
            $user = User::findActiveByLogin(Database::connect($databaseConfig), $login);
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
            self::redirectWithError('The username/email or password is incorrect.', $login);
            return;
        }

        Auth::login([
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
        ]);
        unset($_SESSION['_csrf_token']);
        header('Location: /dashboard', true, 302);
    }

    public static function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Your session expired. Please go back and try again.';
            return;
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
}
