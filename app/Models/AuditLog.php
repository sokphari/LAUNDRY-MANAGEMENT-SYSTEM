<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class AuditLog
{
    /** @return list<array<string, mixed>> */
    public static function all(PDO $database, array $filters, int $limit, int $offset): array
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare(
            'SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id, a.summary, a.details, a.created_at, u.username
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id' . $where . '
             ORDER BY a.created_at DESC, a.id DESC LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public static function count(PDO $database, array $filters): int
    {
        [$where, $parameters] = self::where($filters);
        $statement = $database->prepare('SELECT COUNT(*) FROM audit_logs a' . $where);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public static function staffOptions(PDO $database): array
    {
        return $database->query('SELECT id, username, role FROM users ORDER BY role, username')->fetchAll();
    }

    /** @return array{0: string, 1: array<string, string|int>} */
    private static function where(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        $query = (string) ($filters['q'] ?? '');
        if ($query !== '') {
            $conditions[] = '(a.summary LIKE :summary_query OR a.action LIKE :action_query OR a.entity_type LIKE :entity_query OR CAST(a.entity_id AS CHAR) LIKE :id_query)';
            $term = '%' . $query . '%';
            $parameters += ['summary_query' => $term, 'action_query' => $term, 'entity_query' => $term, 'id_query' => $term];
        }
        foreach (['action' => 'a.action', 'module' => 'a.entity_type', 'user' => 'a.user_id'] as $key => $column) {
            $value = $filters[$key] ?? '';
            if ($value !== '' && $value !== null) {
                $conditions[] = $column . ' = :' . $key;
                $parameters[$key] = $key === 'user' ? (int) $value : (string) $value;
            }
        }
        if (($filters['from'] ?? '') !== '') {
            $conditions[] = 'a.created_at >= :date_from';
            $parameters['date_from'] = (string) $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $conditions[] = 'a.created_at < :date_to';
            $parameters['date_to'] = (string) $filters['to'] . ' 00:00:00';
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }
}
