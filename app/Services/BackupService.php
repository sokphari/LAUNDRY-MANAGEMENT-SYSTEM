<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class BackupService
{
    private const FORMAT_VERSION = 1;
    private const EXCLUDED_TABLES = ['schema_migrations', 'backup_settings'];
    private const MAX_COMPRESSED_FILE_SIZE = 536870912;

    /** @return list<array{name: string, path: string, size: int, created_at: int, type: string}> */
    public static function list(): array
    {
        $directory = self::directory();
        $files = glob($directory . '/laundry-*.json.gz') ?: [];
        $backups = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (!self::validFilename($name) || !is_file($path) || is_link($path)) continue;
            $backups[] = [
                'name' => $name,
                'path' => $path,
                'size' => (int) (filesize($path) ?: 0),
                'created_at' => (int) (filemtime($path) ?: 0),
                'type' => self::typeFromFilename($name),
            ];
        }
        usort($backups, static fn (array $left, array $right): int => $right['created_at'] <=> $left['created_at']);
        return $backups;
    }

    /** @return array{name: string, path: string, size: int, created_at: int, type: string} */
    public static function create(PDO $database, string $type = 'manual', bool $includeUploadedFiles = true): array
    {
        if (!in_array($type, ['manual', 'automatic', 'pre-restore'], true)) {
            throw new RuntimeException('Unsupported backup type.');
        }
        $directory = self::directory();
        $name = sprintf('laundry-%s-%s-%s.json.gz', $type, date('Ymd-His'), bin2hex(random_bytes(6)));
        $path = $directory . '/' . $name;
        $temporaryPath = $path . '.tmp';
        $stream = gzopen($temporaryPath, 'wb9');
        if ($stream === false) throw new RuntimeException('Could not create a compressed backup file.');

        try {
            $database->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $database->beginTransaction();
            $tables = self::dataTables($database);
            self::write($stream, '{"format":' . self::FORMAT_VERSION . ',"created_at":' . json_encode(date(DATE_ATOM), JSON_THROW_ON_ERROR) . ',"database":' . json_encode((string) $database->query('SELECT DATABASE()')->fetchColumn(), JSON_THROW_ON_ERROR) . ',"tables":[');
            foreach ($tables as $tableIndex => $table) {
                if ($tableIndex > 0) self::write($stream, ',');
                $columns = array_map(
                    static fn (array $column): string => (string) $column['Field'],
                    $database->query('SHOW COLUMNS FROM ' . self::quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC)
                );
                self::write($stream, '{"name":' . json_encode($table, JSON_THROW_ON_ERROR) . ',"columns":' . json_encode($columns, JSON_THROW_ON_ERROR) . ',"rows":[');
                $statement = $database->query('SELECT * FROM ' . self::quoteIdentifier($table));
                $rowIndex = 0;
                while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    if ($rowIndex++ > 0) self::write($stream, ',');
                    self::write($stream, json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
                }
                self::write($stream, ']}');
            }
            self::write($stream, '],"assets":');
            self::write($stream, json_encode($includeUploadedFiles ? self::profileAssets() : [], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            self::write($stream, '}');
            $database->commit();
            if (!gzclose($stream)) throw new RuntimeException('Could not finish writing the backup file.');
            if (!chmod($temporaryPath, 0600) || !rename($temporaryPath, $path)) {
                throw new RuntimeException('Could not safely store the backup file.');
            }
            chmod($path, 0600);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            @gzclose($stream);
            @unlink($temporaryPath);
            throw $exception;
        }

        return ['name' => $name, 'path' => $path, 'size' => (int) (filesize($path) ?: 0), 'created_at' => (int) (filemtime($path) ?: time()), 'type' => $type];
    }

    public static function restore(PDO $database, string $filename): void
    {
        $path = self::pathFor($filename);
        $compressedSize = filesize($path);
        if ($compressedSize === false || $compressedSize < 1 || $compressedSize > self::MAX_COMPRESSED_FILE_SIZE) {
            throw new RuntimeException('The selected backup file is empty or too large to restore.');
        }
        $compressed = file_get_contents($path);
        $contents = $compressed === false ? false : gzdecode($compressed);
        if ($contents === false) throw new RuntimeException('The selected backup file is damaged or unreadable.');
        $backup = json_decode($contents, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        unset($contents, $compressed);
        if (!is_array($backup) || ($backup['format'] ?? null) !== self::FORMAT_VERSION || !is_array($backup['tables'] ?? null)) {
            throw new RuntimeException('The selected file is not a supported Laundry Manager backup.');
        }
        if (!is_string($backup['database'] ?? null) || $backup['database'] !== (string) $database->query('SELECT DATABASE()')->fetchColumn()) {
            throw new RuntimeException('This backup belongs to a different database. Restore it from the matching application configuration.');
        }

        $liveTables = self::dataTables($database);
        $liveColumns = [];
        foreach ($liveTables as $tableName) {
            $liveColumns[$tableName] = array_map(
                static fn (array $column): string => (string) $column['Field'],
                $database->query('SHOW COLUMNS FROM ' . self::quoteIdentifier($tableName))->fetchAll(PDO::FETCH_ASSOC)
            );
        }
        $backupTables = [];
        foreach ($backup['tables'] as $table) {
            if (!is_array($table) || !is_string($table['name'] ?? null) || !is_array($table['columns'] ?? null) || !is_array($table['rows'] ?? null)) {
                throw new RuntimeException('The backup file contains invalid table data.');
            }
            if (!isset($liveColumns[$table['name']]) || $table['columns'] !== $liveColumns[$table['name']]) {
                throw new RuntimeException('This backup was made with a different database schema. Restore it using a compatible application version.');
            }
            $backupTables[] = $table['name'];
        }
        sort($backupTables, SORT_STRING);
        if ($backupTables !== $liveTables) {
            throw new RuntimeException('This backup was made with a different database schema. Restore it using a compatible application version.');
        }

        $assets = $backup['assets'] ?? [];
        if (!is_array($assets)) throw new RuntimeException('The backup contains invalid uploaded file data.');
        $restoredAssets = [];
        foreach ($assets as $asset) {
            if (!is_array($asset) || !is_string($asset['path'] ?? null) || !is_string($asset['data'] ?? null)
                || preg_match('~\A/uploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $asset['path']) !== 1) {
                throw new RuntimeException('The backup contains an unsafe uploaded file path.');
            }
            $bytes = base64_decode($asset['data'], true);
            if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024) throw new RuntimeException('The backup contains an invalid or oversized profile image.');
            $restoredAssets[] = ['path' => $asset['path'], 'data' => $bytes];
        }

        $database->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $database->beginTransaction();
            foreach (array_reverse($liveTables) as $tableName) {
                $database->exec('DELETE FROM ' . self::quoteIdentifier($tableName));
            }
            foreach ($backup['tables'] as $table) {
                $tableName = $table['name'];
                $columns = $liveColumns[$tableName];
                $knownColumns = array_fill_keys($liveColumns[$tableName], true);
                foreach ($table['rows'] as $row) {
                    if (!is_array($row) || $row === [] || array_diff(array_keys($row), array_keys($knownColumns)) !== []) {
                        throw new RuntimeException('The backup contains invalid columns for table ' . $tableName . '.');
                    }
                    $sql = 'INSERT INTO ' . self::quoteIdentifier($tableName)
                        . ' (' . implode(', ', array_map(self::quoteIdentifier(...), $columns)) . ') VALUES ('
                        . implode(', ', array_fill(0, count($columns), '?')) . ')';
                    $statement = $database->prepare($sql);
                    $statement->execute(array_values($row));
                }
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        } finally {
            $database->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        foreach ($restoredAssets as $asset) {
            $relative = $asset['path'];
            $target = BASE_PATH . '/public' . $relative;
            $directory = dirname($target);
            if (is_link($directory) || is_link($target)) {
                throw new RuntimeException('Database was restored, but a profile image path is not safe.');
            }
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Database was restored, but profile image storage could not be prepared.');
            }
            $temporary = $target . '.restore-' . bin2hex(random_bytes(4));
            if (file_put_contents($temporary, $asset['data'], LOCK_EX) === false || !chmod($temporary, 0640) || !rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('Database was restored, but a profile image could not be restored.');
            }
        }
    }

    public static function pathFor(string $filename): string
    {
        if (!self::validFilename($filename)) throw new RuntimeException('Invalid backup filename.');
        $path = self::directory() . '/' . $filename;
        if (!is_file($path) || is_link($path)) throw new RuntimeException('The selected backup file does not exist.');
        return $path;
    }

    /** Delete only expired scheduled backups; manual and pre-restore files are preserved. */
    public static function cleanupAutomatic(int $olderThan): int
    {
        $deleted = 0;
        foreach (glob(self::directory() . '/laundry-automatic-*.json.gz') ?: [] as $path) {
            if (is_link($path) || !is_file($path) || (int) (filemtime($path) ?: time()) >= $olderThan) continue;
            if (unlink($path)) $deleted++;
        }
        return $deleted;
    }

    /** @return list<string> */
    public static function dataTables(PDO $database): array
    {
        $tables = $database->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $tables = array_values(array_filter(array_map('strval', $tables), static fn (string $table): bool => !in_array($table, self::EXCLUDED_TABLES, true)));
        sort($tables, SORT_STRING);
        if ($tables === []) throw new RuntimeException('No application tables were found to back up.');
        return $tables;
    }

    /** @return list<array{path: string, data: string}> */
    private static function profileAssets(): array
    {
        $directory = BASE_PATH . '/public/uploads/profiles';
        if (!is_dir($directory)) return [];
        $assets = [];
        foreach (glob($directory . '/*') ?: [] as $path) {
            $name = basename($path);
            if (!is_file($path) || is_link($path) || preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z/', $name) !== 1) continue;
            $size = filesize($path);
            if ($size === false || $size > 5 * 1024 * 1024) continue;
            $contents = file_get_contents($path);
            if ($contents !== false) $assets[] = ['path' => '/uploads/profiles/' . $name, 'data' => base64_encode($contents)];
        }
        return $assets;
    }

    private static function directory(): string
    {
        $directory = BASE_PATH . '/storage/backups';
        if (is_link($directory)) throw new RuntimeException('The backup storage path cannot be a symbolic link.');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the protected backup storage directory.');
        }
        if (!chmod($directory, 0700) || !is_writable($directory)) {
            throw new RuntimeException('The protected backup storage directory is not writable.');
        }
        $resolvedDirectory = realpath($directory);
        $resolvedPublic = realpath(BASE_PATH . '/public');
        if ($resolvedDirectory === false || $resolvedPublic === false
            || $resolvedDirectory === $resolvedPublic
            || str_starts_with($resolvedDirectory, $resolvedPublic . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Backup files must be stored outside the public website folder.');
        }
        return $directory;
    }

    private static function validFilename(string $filename): bool
    {
        return preg_match('/^laundry-(?:manual|automatic|pre-restore)-\d{8}-\d{6}-[a-f0-9]{12}\.json\.gz$/D', $filename) === 1;
    }

    private static function typeFromFilename(string $filename): string
    {
        preg_match('/^laundry-(manual|automatic|pre-restore)-/', $filename, $matches);
        return $matches[1] ?? 'backup';
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /** @param resource $stream */
    private static function write($stream, string $contents): void
    {
        $written = gzwrite($stream, $contents);
        if ($written === false || $written !== strlen($contents)) throw new RuntimeException('Could not write all backup data.');
    }
}
