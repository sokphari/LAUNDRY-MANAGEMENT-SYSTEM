<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuditLog
{
    /** @param array<string, mixed> $details */
    public static function record(PDO $database, ?int $userId, string $action, string $entityType, ?int $entityId, string $summary, array $details = []): void
    {
        $statement = $database->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, summary, details) VALUES (:user_id, :action, :entity_type, :entity_id, :summary, :details)');
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => mb_substr($summary, 0, 500),
            'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
    }
}
