<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Service
{
    /** @return list<array<string, mixed>> */
    public static function all(PDO $database, string $query = '', string $status = 'all'): array
    {
        $conditions = [];
        $parameters = [];
        if ($status === 'active') $conditions[] = 'is_active = 1';
        elseif ($status === 'inactive') $conditions[] = 'is_active = 0';
        if ($query !== '') {
            $conditions[] = 'name LIKE :name_query';
            $parameters['name_query'] = '%' . $query . '%';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $statement = $database->prepare('SELECT id, name, description, price, billing_unit, is_active, created_at FROM services' . $where . ' ORDER BY is_active DESC, name, id DESC LIMIT 200');
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|false */
    public static function find(PDO $database, int $id): array|false
    {
        $statement = $database->prepare('SELECT id, name, description, price, billing_unit, is_active, created_at FROM services WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    /** @return array<string, mixed>|false */
    public static function findForUpdate(PDO $database, int $id): array|false
    {
        $statement = $database->prepare('SELECT id, name, description, price, billing_unit, is_active, created_at FROM services WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    /** @param array{name: string, description: string, price: string, billing_unit: string} $data */
    public static function create(PDO $database, array $data): void
    {
        $statement = $database->prepare('INSERT INTO services (name, description, price, billing_unit) VALUES (:name, :description, :price, :billing_unit)');
        $statement->execute($data);
    }

    /** @param array{name: string, description: string, price: string, billing_unit: string} $data */
    public static function update(PDO $database, int $id, array $data): bool
    {
        $statement = $database->prepare('UPDATE services SET name = :name, description = :description, price = :price, billing_unit = :billing_unit WHERE id = :id');
        $statement->execute($data + ['id' => $id]);
        return $statement->rowCount() > 0 || self::find($database, $id) !== false;
    }

    public static function deactivate(PDO $database, int $id): bool
    {
        $statement = $database->prepare('UPDATE services SET is_active = 0 WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }

    public static function setActive(PDO $database, int $id, bool $active): bool
    {
        $statement = $database->prepare('UPDATE services SET is_active = :new_active WHERE id = :id AND is_active <> :old_active');
        $value = $active ? 1 : 0;
        $statement->execute(['new_active' => $value, 'id' => $id, 'old_active' => $value]);
        return $statement->rowCount() > 0;
    }

    public static function nameAndUnitExists(PDO $database, string $name, string $unit, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM services WHERE name = :name AND billing_unit = :billing_unit';
        $parameters = ['name' => $name, 'billing_unit' => $unit];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }
        $statement = $database->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }
}
