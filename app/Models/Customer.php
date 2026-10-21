<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Customer
{
    /** @return list<array{id: int|string, name: string, phone: string}> */
    public static function activeOptions(PDO $database): array
    {
        return $database->query('SELECT id, name, phone FROM customers WHERE is_active = 1 ORDER BY name LIMIT 500')->fetchAll();
    }

    /** @return array{customers: list<array<string, mixed>>, total: int} */
    public static function paginate(PDO $database, string $query, int $limit, int $offset): array
    {
        if ($query === '') {
            $total = (int) $database->query('SELECT COUNT(*) FROM customers WHERE is_active = 1')->fetchColumn();
            $statement = $database->prepare(
                'SELECT id, name, phone, address, notes, is_active, created_at
                 FROM customers WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
            );
        } else {
            $term = '%' . $query . '%';
            $count = $database->prepare(
                'SELECT COUNT(*) FROM customers
                 WHERE is_active = 1 AND (name LIKE :name_count OR phone LIKE :phone_count)'
            );
            $count->execute(['name_count' => $term, 'phone_count' => $term]);
            $total = (int) $count->fetchColumn();
            $statement = $database->prepare(
                'SELECT id, name, phone, address, notes, is_active, created_at
                 FROM customers
                 WHERE is_active = 1 AND (name LIKE :name_query OR phone LIKE :phone_query)
                 ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue(':name_query', $term, PDO::PARAM_STR);
            $statement->bindValue(':phone_query', $term, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['customers' => $statement->fetchAll(), 'total' => $total];
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

    /** @return array<string, mixed>|false */
    public static function findForUpdate(PDO $database, int $id): array|false
    {
        $statement = $database->prepare(
            'SELECT id, name, phone, address, notes, is_active
             FROM customers WHERE id = :id LIMIT 1 FOR UPDATE'
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
