<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PermissionService
{
    public static function allows(PDO $database, int $userId, string $role, string $permission): bool
    {
        if ($role === 'admin') return true;
        $statement = $database->prepare(
            'SELECT up.effect FROM permissions p
             LEFT JOIN user_permissions up ON up.permission_id = p.id AND up.user_id = :user_id
             WHERE p.permission_key = :permission LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'permission' => $permission]);
        $override = $statement->fetchColumn();
        if ($override === 'allow') return true;
        if ($override === 'deny') return false;
        $statement = $database->prepare(
            'SELECT 1 FROM roles r JOIN role_permissions rp ON rp.role_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = :role AND p.permission_key = :permission LIMIT 1'
        );
        $statement->execute(['role' => $role, 'permission' => $permission]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array<string, string> Permission key => override effect */
    public static function overrides(PDO $database, int $userId): array
    {
        $statement = $database->prepare('SELECT p.permission_key, up.effect FROM user_permissions up JOIN permissions p ON p.id=up.permission_id WHERE up.user_id=:user_id');
        $statement->execute(['user_id' => $userId]);
        $overrides = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) $overrides[(string)$row['permission_key']] = (string)$row['effect'];
        return $overrides;
    }

    /** @return list<string> */
    public static function effective(PDO $database, int $userId, string $role): array
    {
        if ($role === 'admin') return array_map('strval', $database->query('SELECT permission_key FROM permissions')->fetchAll(PDO::FETCH_COLUMN));
        $statement = $database->prepare(
            'SELECT p.permission_key FROM roles r JOIN role_permissions rp ON rp.role_id=r.id
             JOIN permissions p ON p.id=rp.permission_id WHERE r.name=:role'
        );
        $statement->execute(['role' => $role]);
        $permissions = array_fill_keys(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN),), true);
        foreach (self::overrides($database, $userId) as $key => $effect) {
            if ($effect === 'allow') $permissions[$key] = true;
            else unset($permissions[$key]);
        }
        return array_keys($permissions);
    }
}
