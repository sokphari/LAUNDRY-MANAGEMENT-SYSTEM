<?php

declare(strict_types=1);

$publicRoot = __DIR__;
$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestedFile = realpath($publicRoot . $requestedPath);
$root = realpath($publicRoot);

if ($requestedFile !== false && $root !== false && str_starts_with($requestedFile, $root . DIRECTORY_SEPARATOR) && is_file($requestedFile)) {
    return false;
}

require $publicRoot . '/index.php';
