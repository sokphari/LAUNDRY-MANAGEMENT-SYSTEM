<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditLog;
use App\Services\BackupService;
use PDO;
use RuntimeException;
use Throwable;

final class BackupController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig, string $environment): void
    {
        try {
            $database = Database::connect($databaseConfig);
            $settings = self::settings($database);
            $backups = BackupService::list();
            $error = $_SESSION['_backup_error'] ?? null;
            $notice = $_SESSION['_backup_notice'] ?? null;
            unset($_SESSION['_backup_error'], $_SESSION['_backup_notice']);
            View::render('backups/index', [
                'title' => 'Backup & Restore', 'user' => Auth::user(), 'csrfToken' => Csrf::token(), 'currentPath' => '/backups',
                'settings' => $settings, 'backups' => $backups, 'notice' => is_string($notice) ? $notice : '',
                'error' => is_string($error) ? $error : '', 'environment' => strtolower($environment),
            ]);
        } catch (Throwable $exception) {
            error_log('[backup] Could not load backup settings: ' . $exception->getMessage());
            http_response_code(500);
            echo 'Backup settings are temporarily unavailable. Run the database migrations and try again.';
        }
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function create(array $databaseConfig): void
    {
        if (!self::verifyCsrf()) return;
        $backup = null;
        try {
            $database = Database::connect($databaseConfig);
            $settings = self::settings($database);
            if (!$settings['database_enabled']) throw new RuntimeException('Enable Database Backup before creating a backup.');
            $backup = BackupService::create($database, 'manual', $settings['uploaded_files_enabled']);
            AuditLog::record($database, self::actorId(), 'backup.created', 'backup', null, 'Created manual database backup', [
                'filename' => $backup['name'], 'bytes' => $backup['size'], 'type' => $backup['type'],
            ]);
            $_SESSION['_backup_notice'] = 'Backup created successfully.';
        } catch (Throwable $exception) {
            if (is_array($backup) && isset($backup['path']) && is_file($backup['path'])) @unlink($backup['path']);
            error_log('[backup] Manual backup failed: ' . $exception->getMessage());
            self::recordFailureNotification($database ?? null, 'Manual backup failed', $exception);
            $_SESSION['_backup_error'] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not create a backup. Check the server log and protected storage permissions.';
        }
        self::redirect();
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function updateSettings(array $databaseConfig): void
    {
        if (!self::verifyCsrf()) return;
        try {
            $database = Database::connect($databaseConfig);
            $before = self::settings($database);
            $settings = [
                'automatic_enabled' => ($_POST['automatic_enabled'] ?? '') === '1',
                'database_enabled' => ($_POST['database_enabled'] ?? '') === '1',
                'uploaded_files_enabled' => ($_POST['uploaded_files_enabled'] ?? '') === '1',
                'offsite_enabled' => false,
                'notifications_enabled' => ($_POST['notifications_enabled'] ?? '') === '1',
                'auto_cleanup_enabled' => ($_POST['auto_cleanup_enabled'] ?? '') === '1',
                'frequency' => in_array($_POST['frequency'] ?? '', ['daily', '6hours', 'weekly'], true) ? (string) $_POST['frequency'] : 'daily',
            ];
            $offsiteRequested = ($_POST['offsite_enabled'] ?? '') === '1';
            if ($offsiteRequested) throw new RuntimeException('Off-site storage is not configured on this server. Configure a protected remote storage target before enabling it.');
            $changes = [];
            foreach ($settings as $key => $value) if (($before[$key] ?? null) !== $value) $changes[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
            if ($changes !== []) {
                $database->beginTransaction();
                $statement = $database->prepare('UPDATE backup_settings SET automatic_enabled = :automatic_enabled, database_enabled = :database_enabled, uploaded_files_enabled = :uploaded_files_enabled, offsite_enabled = 0, notifications_enabled = :notifications_enabled, auto_cleanup_enabled = :auto_cleanup_enabled, frequency = :frequency, updated_by = :user_id WHERE id = 1');
                $statement->execute([
                    'automatic_enabled' => (int) $settings['automatic_enabled'], 'database_enabled' => (int) $settings['database_enabled'],
                    'uploaded_files_enabled' => (int) $settings['uploaded_files_enabled'], 'notifications_enabled' => (int) $settings['notifications_enabled'],
                    'auto_cleanup_enabled' => (int) $settings['auto_cleanup_enabled'], 'frequency' => $settings['frequency'], 'user_id' => self::actorId(),
                ]);
                AuditLog::record($database, self::actorId(), 'backup.settings_changed', 'backup', null, 'Changed backup settings', ['changes' => $changes]);
                $database->commit();
            }
            $_SESSION['_backup_notice'] = !$settings['automatic_enabled']
                ? 'Automatic backups are disabled. Existing backup files were kept.'
                : 'Backup settings saved. Automatic backups follow the selected frequency when the scheduler runs.';
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            error_log('[backup] Could not update backup setting: ' . $exception->getMessage());
            $_SESSION['_backup_error'] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update backup settings.';
        }
        self::redirect();
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function restore(string $filename, array $databaseConfig): void
    {
        if (!self::verifyCsrf()) return;
        if (!isset($_POST['confirm_restore']) || !is_string($_POST['confirm_restore']) || $_POST['confirm_restore'] !== 'RESTORE') {
            $_SESSION['_backup_error'] = 'Type RESTORE exactly to confirm replacing the current database data.';
            self::redirect();
            return;
        }

        $dataRestored = false;
        try {
            $database = Database::connect($databaseConfig);
            $source = BackupService::pathFor($filename);
            $restoreSettings = self::settings($database);
            if (!$restoreSettings['database_enabled']) throw new RuntimeException('Enable Database Backup before restoring a database backup.');
            $safetyBackup = BackupService::create($database, 'pre-restore', $restoreSettings['uploaded_files_enabled']);
            try {
                AuditLog::record($database, self::actorId(), 'backup.created', 'backup', null, 'Created safety backup before restore', [
                    'filename' => $safetyBackup['name'], 'bytes' => $safetyBackup['size'], 'type' => 'pre-restore',
                ]);
            } catch (Throwable $exception) {
                @unlink($safetyBackup['path']);
                throw $exception;
            }
            BackupService::restore($database, $filename);
            $dataRestored = true;
            $database->exec('UPDATE backup_settings SET updated_by = NULL WHERE id = 1');
            $actorId = self::actorId();
            $actorExists = false;
            $actorStillAdmin = false;
            if ($actorId !== null) {
                $actorCheck = $database->prepare('SELECT role, is_active, auth_version FROM users WHERE id = :id');
                $actorCheck->execute(['id' => $actorId]);
                $restoredActor = $actorCheck->fetch(PDO::FETCH_ASSOC);
                $actorExists = is_array($restoredActor);
                $actorStillAdmin = $actorExists
                    && $restoredActor['role'] === 'admin'
                    && (int) $restoredActor['is_active'] === 1
                    && (int) $restoredActor['auth_version'] === (int) ($_SESSION['user']['auth_version'] ?? -1);
            }
            $database->beginTransaction();
            AuditLog::record($database, $actorExists ? $actorId : null, 'backup.created', 'backup', null, 'Created safety backup before restore', [
                'filename' => $safetyBackup['name'], 'bytes' => $safetyBackup['size'], 'type' => 'pre-restore',
            ]);
            AuditLog::record($database, $actorExists ? $actorId : null, 'backup.restored', 'backup', null, 'Restored database backup', [
                'filename' => $filename, 'source_bytes' => (int) (filesize($source) ?: 0), 'safety_backup' => $safetyBackup['name'],
            ]);
            $database->commit();
            $_SESSION['_backup_notice'] = 'Backup restored. A safety backup of the previous data was saved first.';
            if (!$actorStillAdmin) {
                Auth::logout();
                header('Location: /login', true, 303);
                return;
            }
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            error_log('[backup] Restore failed: ' . $exception->getMessage());
            self::recordFailureNotification($database ?? null, 'Backup restore failed', $exception);
            $_SESSION['_backup_error'] = $dataRestored
                ? 'Data was restored, but the completion audit could not be saved. Check the server log before further restores.'
                : ($exception instanceof RuntimeException ? $exception->getMessage() : 'Restore failed. The database transaction was rolled back; check the server log.');
        }
        self::redirect();
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function download(string $filename, array $databaseConfig): void
    {
        try {
            $path = BackupService::pathFor($filename);
            $database = Database::connect($databaseConfig);
            AuditLog::record($database, self::actorId(), 'backup.downloaded', 'backup', null, 'Downloaded database backup', ['filename' => $filename]);
            header('Content-Type: application/gzip');
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
        } catch (Throwable $exception) {
            error_log('[backup] Backup download failed: ' . $exception->getMessage());
            http_response_code(404);
            echo 'Backup file not found.';
        }
    }

    /** Run from the daily CLI scheduler. @param array<string, mixed> $databaseConfig */
    public static function runScheduled(array $databaseConfig): int
    {
        $database = Database::connect($databaseConfig);
        if ((int) $database->query("SELECT GET_LOCK('laundry_manager_scheduled_backup', 0)")->fetchColumn() !== 1) {
            fwrite(STDOUT, "Skipped: another automatic backup is running.\n");
            return 0;
        }
        try {
            $settings = self::settings($database);
            if (!$settings['automatic_enabled']) {
                fwrite(STDOUT, "Skipped: automatic backups are disabled.\n");
                return 0;
            }
            if (!$settings['database_enabled']) {
                fwrite(STDOUT, "Skipped: database backups are disabled.\n");
                return 0;
            }
            $interval = ['daily' => 86400, '6hours' => 21600, 'weekly' => 604800][$settings['frequency']];
            if ($settings['last_automatic_at'] !== null && time() - strtotime($settings['last_automatic_at']) < $interval) {
                fwrite(STDOUT, "Skipped: the configured backup frequency has not elapsed.\n");
                return 0;
            }
            $backup = BackupService::create($database, 'automatic', $settings['uploaded_files_enabled']);
            try {
                $database->beginTransaction();
                $database->exec('UPDATE backup_settings SET last_automatic_at = CURRENT_TIMESTAMP WHERE id = 1');
                AuditLog::record($database, null, 'backup.created', 'backup', null, 'Created scheduled automatic backup', [
                    'filename' => $backup['name'], 'bytes' => $backup['size'], 'type' => $backup['type'],
                ]);
                $database->commit();
            } catch (Throwable $exception) {
                if ($database->inTransaction()) $database->rollBack();
                @unlink($backup['path']);
                throw $exception;
            }
            fwrite(STDOUT, 'Created automatic backup: ' . $backup['name'] . "\n");
            if ($settings['auto_cleanup_enabled']) {
                $deleted = BackupService::cleanupAutomatic(time() - ((int) $settings['retention_days'] * 86400));
                if ($deleted > 0) {
                    AuditLog::record($database, null, 'backup.cleanup', 'backup', null, 'Removed expired automatic backups', ['deleted' => $deleted, 'retention_days' => $settings['retention_days']]);
                }
            }
            return 0;
        } catch (Throwable $exception) {
            error_log('[backup] Scheduled backup failed: ' . $exception->getMessage());
            try {
                $settings = self::settings($database);
                if ($settings['notifications_enabled']) AuditLog::record($database, null, 'backup.failed', 'backup', null, 'Scheduled backup failed', ['message' => mb_substr($exception->getMessage(), 0, 300)]);
            } catch (Throwable $auditException) {
                error_log('[backup] Could not record scheduled failure notification: ' . $auditException->getMessage());
            }
            fwrite(STDERR, "Automatic backup failed. Check the application log.\n");
            return 1;
        } finally {
            $database->query("SELECT RELEASE_LOCK('laundry_manager_scheduled_backup')");
        }
    }

    /** @return array{automatic_enabled: bool, database_enabled: bool, uploaded_files_enabled: bool, offsite_enabled: bool, notifications_enabled: bool, auto_cleanup_enabled: bool, frequency: string, retention_days: int, last_automatic_at: ?string} */
    private static function settings(PDO $database): array
    {
        $row = $database->query('SELECT automatic_enabled, database_enabled, uploaded_files_enabled, offsite_enabled, notifications_enabled, auto_cleanup_enabled, frequency, retention_days, last_automatic_at FROM backup_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Backup settings are missing. Run the database migrations.');
        return [
            'automatic_enabled' => (bool) $row['automatic_enabled'], 'database_enabled' => (bool) $row['database_enabled'],
            'uploaded_files_enabled' => (bool) $row['uploaded_files_enabled'], 'offsite_enabled' => (bool) $row['offsite_enabled'],
            'notifications_enabled' => (bool) $row['notifications_enabled'], 'auto_cleanup_enabled' => (bool) $row['auto_cleanup_enabled'],
            'frequency' => (string) $row['frequency'], 'retention_days' => (int) $row['retention_days'],
            'last_automatic_at' => $row['last_automatic_at'] === null ? null : (string) $row['last_automatic_at'],
        ];
    }

    private static function verifyCsrf(): bool
    {
        if (Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) return true;
        http_response_code(419);
        echo 'Your session expired. Reload and try again.';
        return false;
    }

    private static function recordFailureNotification(?PDO $database, string $summary, Throwable $failure): void
    {
        if ($database === null || $database->inTransaction()) return;
        try {
            $enabled = $database->query('SELECT notifications_enabled FROM backup_settings WHERE id = 1')->fetchColumn();
            if ((int) $enabled === 1) {
                AuditLog::record($database, null, 'backup.failed', 'backup', null, $summary, ['message' => mb_substr($failure->getMessage(), 0, 300)]);
            }
        } catch (Throwable $exception) {
            error_log('[backup] Could not record failure notification: ' . $exception->getMessage());
        }
    }

    private static function actorId(): ?int
    {
        $id = Auth::user()['id'] ?? null;
        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private static function redirect(): void
    {
        header('Location: /backups', true, 303);
    }
}
