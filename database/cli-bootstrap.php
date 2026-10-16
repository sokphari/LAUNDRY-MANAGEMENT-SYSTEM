<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$databaseConfig = require BASE_PATH . '/config/database.php';
$localConfigPath = BASE_PATH . '/config/local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    $databaseConfig = array_replace($databaseConfig, $localConfig['database'] ?? []);
}

try {
    return App\Core\Database::connect($databaseConfig);
} catch (PDOException $exception) {
    fwrite(STDERR, "Database connection failed: {$exception->getMessage()}\n");
    fwrite(STDERR, "Check config/database.php or config/local.php and confirm the MySQL user can connect with a password.\n");
    exit(1);
}
