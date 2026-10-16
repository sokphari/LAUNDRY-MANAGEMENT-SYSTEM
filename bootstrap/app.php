<?php

declare(strict_types=1);

use App\Core\Router;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Helpers/functions.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:");

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

$appConfig = require BASE_PATH . '/config/app.php';
$databaseConfig = require BASE_PATH . '/config/database.php';
$localConfigPath = BASE_PATH . '/config/local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    $databaseConfig = array_replace($databaseConfig, $localConfig['database'] ?? []);
}

date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$router = new Router();
require BASE_PATH . '/routes/web.php';

return ['app' => $appConfig, 'database' => $databaseConfig, 'router' => $router];
