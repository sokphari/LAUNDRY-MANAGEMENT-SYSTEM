<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class SecurityNotification
{
    private const ADMIN_ACTIONS = [
        'auth.login', 'auth.login_failed', 'auth.logout',
        'user.created', 'user.updated', 'user.role_changed', 'user.enabled', 'user.disabled',
        'user.password_changed', 'user.password_reset', 'user.permissions_updated', 'role.permissions_updated',
    ];

    /** @return array{notifications: list<array<string, mixed>>, unread_count: int, failed_attempt_count: int} */
    public static function forUser(PDO $database, int $userId, string $role, int $limit = 8): array
    {
        [$scope, $parameters] = self::scope($userId, $role);
        $read = $database->prepare('SELECT last_read_audit_id FROM user_notification_reads WHERE user_id = :user_id');
        $read->execute(['user_id' => $userId]);
        $lastRead = (int) ($read->fetchColumn() ?: 0);

        $count = $database->prepare('SELECT COUNT(*) FROM audit_logs a WHERE ' . $scope . ' AND a.id > :last_read');
        $count->execute($parameters + ['last_read' => $lastRead]);
        $unread = (int) $count->fetchColumn();

        if ($role === 'admin') {
            $failedCount = (int) $database->query(
                "SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login_failed'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            )->fetchColumn();
        } else {
            $failedStatement = $database->prepare(
                "SELECT COUNT(*) FROM audit_logs
                 WHERE action = 'auth.login_failed'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                   AND CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.target_user_id')) AS UNSIGNED) = :user_id"
            );
            $failedStatement->execute(['user_id' => $userId]);
            $failedCount = (int) $failedStatement->fetchColumn();
        }

        $statement = $database->prepare(
            "SELECT a.id, a.action, a.summary, a.entity_type, a.entity_id, a.created_at, u.username AS actor,
                    CASE WHEN a.action = 'auth.login_failed' THEN
                        (SELECT COUNT(*) FROM audit_logs f
                         WHERE f.action = 'auth.login_failed'
                           AND f.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                           AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(f.details, '$.login_identifier'))) =
                               LOWER(JSON_UNQUOTE(JSON_EXTRACT(a.details, '$.login_identifier'))))
                    ELSE 0 END AS failed_attempts
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             WHERE ' . $scope . '
             ORDER BY a.id DESC LIMIT " . max(1, min(20, $limit))
        );
        $statement->execute($parameters);
        $notifications = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($notifications as &$notification) {
            $notification['is_unread'] = (int) $notification['id'] > $lastRead;
            $notification['created_display'] = date('d M Y, H:i', strtotime((string) $notification['created_at']));
            $notification['action_label'] = self::actionLabel((string) $notification['action']);
        }
        unset($notification);

        return ['notifications' => $notifications, 'unread_count' => $unread, 'failed_attempt_count' => $failedCount];
    }

    /** Mark current eligible events as read while keeping events arriving afterward unread. */
    public static function markRead(PDO $database, int $userId, string $role): void
    {
        [$scope, $parameters] = self::scope($userId, $role);
        $latest = $database->prepare('SELECT COALESCE(MAX(a.id), 0) FROM audit_logs a WHERE ' . $scope);
        $latest->execute($parameters);
        $latestId = (int) $latest->fetchColumn();

        $database->beginTransaction();
        try {
            $database->prepare('INSERT IGNORE INTO user_notification_reads (user_id, last_read_audit_id) VALUES (:user_id, 0)')
                ->execute(['user_id' => $userId]);
            $update = $database->prepare(
                'UPDATE user_notification_reads
                 SET last_read_audit_id = GREATEST(last_read_audit_id, :latest_id)
                 WHERE user_id = :user_id'
            );
            $update->execute(['latest_id' => $latestId, 'user_id' => $userId]);
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    /** @return array{string, array<string, int|string>} */
    private static function scope(int $userId, string $role): array
    {
        $actions = self::ADMIN_ACTIONS;
        $quotedActions = implode(',', array_map(static fn (string $action): string => "'" . $action . "'", $actions));
        if ($role === 'admin') {
            return [
                '(a.action IN (' . $quotedActions . ") AND NOT (a.user_id = :viewer_id AND a.action IN ('auth.login','auth.logout')))",
                ['viewer_id' => $userId],
            ];
        }

        return [
            "((a.user_id = :actor_id AND a.action = 'user.password_changed')
              OR (a.entity_type = 'user' AND a.entity_id = :target_id AND a.action IN ('user.updated','user.role_changed','user.enabled','user.disabled','user.password_reset'))
              OR (a.action = 'auth.login_failed' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(a.details, '$.target_user_id')) AS UNSIGNED) = :failed_target))",
            ['actor_id' => $userId, 'target_id' => $userId, 'failed_target' => $userId],
        ];
    }

    private static function actionLabel(string $action): string
    {
        return match ($action) {
            'auth.login' => 'Signed in',
            'auth.login_failed' => 'Failed sign-in attempt',
            'auth.logout' => 'Signed out',
            'user.created' => 'User account created',
            'user.updated' => 'User account updated',
            'user.role_changed' => 'User role changed',
            'user.enabled' => 'User account enabled',
            'user.disabled' => 'User account disabled',
            'user.password_changed' => 'Password changed',
            'user.password_reset' => 'Password reset',
            'user.permissions_updated' => 'User permissions changed',
            'role.permissions_updated' => 'Role permissions changed',
            default => ucwords(str_replace(['.', '_'], ' ', $action)),
        };
    }
}
