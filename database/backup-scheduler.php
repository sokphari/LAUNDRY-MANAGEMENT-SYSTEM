<?php

declare(strict_types=1);

require __DIR__ . '/cli-bootstrap.php';
$appConfig = require dirname(__DIR__) . '/config/app.php';
$databaseConfig = require dirname(__DIR__) . '/config/database.php';
$localConfigPath = dirname(__DIR__) . '/config/local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    $databaseConfig = array_replace($databaseConfig, $localConfig['database'] ?? []);
}
date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

exit(App\Controllers\BackupController::runScheduled($databaseConfig));
