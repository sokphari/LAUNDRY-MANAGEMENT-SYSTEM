<?php

declare(strict_types=1);

$database = require __DIR__ . '/cli-bootstrap.php';
$database->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $database->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$migrationFiles = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);

foreach ($migrationFiles as $migrationFile) {
    $name = basename($migrationFile);
    if (in_array($name, $applied, true)) {
        echo "Already applied: {$name}\n";
        continue;
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Migration is empty or unreadable: {$name}\n");
        exit(1);
    }

    try {
        $database->exec($sql);
        $statement = $database->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $statement->execute(['migration' => $name]);
        echo "Applied: {$name}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "Failed: {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Migrations complete.\n";
