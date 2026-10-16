<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Customer
{
    /** @return list<array<string, mixed>> */
    public static function all(PDO $database): array
    {
        $statement = $database->query(
            'SELECT id, name, phone, address, notes, is_active, created_at
             FROM customers WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 100'
        );

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function search(PDO $database, string $query): array
    {
        $statement = $database->prepare(
            'SELECT id, name, phone, address, notes, is_active, created_at
             FROM customers
             WHERE is_active = 1 AND (name LIKE :name_query OR phone LIKE :phone_query)
             ORDER BY created_at DESC, id DESC LIMIT 100'
        );
        $term = '%' . $query . '%';
        $statement->execute(['name_query' => $term, 'phone_query' => $term]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|false */
    public static function find(PDO $database, int $id): array|false
    {
        $statement = $database->prepare(
            'SELECT id, name, phone, address, notes, is_active
             FROM customers WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch();
    }

    /** @param array{name: string, phone: string, address: ?string, notes: ?string} $data */
    public static function create(PDO $database, array $data): void
    {
        $statement = $database->prepare(
            'INSERT INTO customers (name, phone, address, notes)
             VALUES (:name, :phone, :address, :notes)'
        );
        $statement->execute($data);
    }

    /** @param array{name: string, phone: string, address: ?string, notes: ?string} $data */
    public static function update(PDO $database, int $id, array $data): bool
    {
        $statement = $database->prepare(
            'UPDATE customers
             SET name = :name, phone = :phone, address = :address, notes = :notes
             WHERE id = :id'
        );
        $statement->execute($data + ['id' => $id]);

        return $statement->rowCount() > 0 || self::find($database, $id) !== false;
    }

    public static function deactivate(PDO $database, int $id): bool
    {
        $statement = $database->prepare('UPDATE customers SET is_active = 0 WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
