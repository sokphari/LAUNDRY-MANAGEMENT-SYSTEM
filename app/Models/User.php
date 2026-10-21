<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    /** @return array<string, mixed>|false */
    public static function findById(PDO $database, int $id): array|false
    {
        $statement = $database->prepare('SELECT id, full_name, gender, profile, username, email, role, is_active FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    /** @return array{id: int, full_name: string, username: string, email: ?string, role: string, password_hash: string, auth_version: int}|false */
    public static function findActiveByLogin(PDO $database, string $login): array|false
    {
        $statement = $database->prepare(
            'SELECT id, full_name, username, email, role, password_hash, auth_version
             FROM users
             WHERE is_active = 1 AND (username = :username OR email = :email)
             LIMIT 1'
        );
        $statement->execute(['username' => $login, 'email' => $login]);

        return $statement->fetch();
    }
}
