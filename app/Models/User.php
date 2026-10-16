<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    /** @return array{id: int, full_name: string, username: string, role: string, password_hash: string}|false */
    public static function findActiveByLogin(PDO $database, string $login): array|false
    {
        $statement = $database->prepare(
            'SELECT id, full_name, username, role, password_hash
             FROM users
             WHERE is_active = 1 AND (username = :username OR email = :email)
             LIMIT 1'
        );
        $statement->execute(['username' => $login, 'email' => $login]);

        return $statement->fetch();
    }
}
