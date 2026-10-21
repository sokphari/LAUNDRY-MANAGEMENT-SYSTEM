<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class LoginRateLimiter
{
    private const WINDOW_MINUTES = 15;
    private const ACCOUNT_IP_LIMIT = 5;
    private const IP_LIMIT = 25;

    public static function blockedFor(PDO $database, string $login, string $ip): int
    {
        [$ipHash, $loginHash] = self::keys($login, $ip);
        $statement = $database->prepare(
            'SELECT COUNT(*) attempts, MAX(attempted_at) latest
             FROM login_attempts WHERE ip_hash = :ip AND login_hash = :login
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $statement->execute(['ip' => $ipHash, 'login' => $loginHash]);
        $account = $statement->fetch(PDO::FETCH_ASSOC) ?: ['attempts' => 0, 'latest' => null];
        $statement = $database->prepare(
            'SELECT COUNT(*) attempts, MAX(attempted_at) latest
             FROM login_attempts WHERE ip_hash = :ip
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $statement->execute(['ip' => $ipHash]);
        $address = $statement->fetch(PDO::FETCH_ASSOC) ?: ['attempts' => 0, 'latest' => null];
        $blocked = (int)$account['attempts'] >= self::ACCOUNT_IP_LIMIT ? $account : ((int)$address['attempts'] >= self::IP_LIMIT ? $address : null);
        if ($blocked === null || !is_string($blocked['latest'])) return 0;
        return max(0, 900 - (time() - strtotime($blocked['latest'])));
    }

    public static function recordFailure(PDO $database, string $login, string $ip): int
    {
        [$ipHash, $loginHash] = self::keys($login, $ip);
        $database->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();
        $statement = $database->prepare('INSERT INTO login_attempts (ip_hash, login_hash) VALUES (:ip, :login)');
        $statement->execute(['ip' => $ipHash, 'login' => $loginHash]);

        $count = $database->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_hash = :ip AND login_hash = :login
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $count->execute(['ip' => $ipHash, 'login' => $loginHash]);
        return (int) $count->fetchColumn();
    }

    public static function clear(PDO $database, string $login, string $ip): void
    {
        [$ipHash, $loginHash] = self::keys($login, $ip);
        $statement = $database->prepare('DELETE FROM login_attempts WHERE ip_hash = :ip AND login_hash = :login');
        $statement->execute(['ip' => $ipHash, 'login' => $loginHash]);
    }

    /** @return array{string,string} */
    private static function keys(string $login, string $ip): array
    {
        return [hash('sha256', $ip), hash('sha256', mb_strtolower(trim($login)))];
    }
}
