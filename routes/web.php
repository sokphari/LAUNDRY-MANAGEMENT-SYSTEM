<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AuditLogController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\ServiceController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\ReportController;
use App\Core\Auth;
use App\Middleware\RequireAuth;
use App\Middleware\RequireRole;

$router->get('/', static function (): void {
    header('Location: ' . (Auth::check() ? '/dashboard' : '/login'), true, 302);
});

$router->get('/login', static function () use ($databaseConfig): void {
    AuthController::showLogin($databaseConfig);
});

$router->post('/login', static function () use ($databaseConfig): void {
    AuthController::login($databaseConfig);
});

$router->post('/logout', static function (): void {
    if (RequireAuth::run()) {
        AuthController::logout();
    }
});

$router->get('/dashboard', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) {
        DashboardController::index($databaseConfig);
    }
});

$router->get('/audit-logs', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) AuditLogController::index($databaseConfig);
});

$router->get('/payments', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) PaymentController::index($databaseConfig);
});
$router->get('/payments/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) PaymentController::index($databaseConfig);
});
$router->get('/payments/export', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) PaymentController::export($databaseConfig);
});

$router->get('/reports', static function () use ($databaseConfig): void {
    if (RequireAuth::run() && RequireRole::run(['admin'])) ReportController::index($databaseConfig);
});
$router->get('/reports/export', static function () use ($databaseConfig): void {
    if (RequireAuth::run() && RequireRole::run(['admin'])) ReportController::export($databaseConfig);
});

$router->get('/customers', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::index($databaseConfig);
    }
});

$router->get('/customers/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::searchJson($databaseConfig);
    }
});

$router->get('/customers/create', static function (): void {
    if (RequireAuth::run()) {
        CustomerController::create();
    }
});

$router->post('/customers/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::store($databaseConfig);
    }
});

$router->get('/customers/{id}', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::show($id, $databaseConfig);
    }
});

$router->post('/customers/{id}/delete', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::deactivate($id, $databaseConfig);
    }
});

$router->get('/customers/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::edit($id, $databaseConfig);
    }
});

$router->post('/customers/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) {
        CustomerController::update($id, $databaseConfig);
    }
});

$router->get('/services', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::index($databaseConfig);
});
$router->get('/services/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::searchJson($databaseConfig);
});
$router->get('/services/create', static function (): void {
    if (RequireAuth::run()) ServiceController::create();
});
$router->post('/services/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::store($databaseConfig);
});
$router->get('/services/{id}', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::show($id, $databaseConfig);
});
$router->post('/services/{id}/delete', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::deactivate($id, $databaseConfig);
});
$router->post('/services/{id}/status', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::setStatus($id, $databaseConfig);
});
$router->get('/services/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::edit($id, $databaseConfig);
});
$router->post('/services/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) ServiceController::update($id, $databaseConfig);
});

$router->get('/orders', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::index($databaseConfig);
});
$router->get('/orders/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::index($databaseConfig);
});
$router->get('/orders/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::create($databaseConfig);
});
$router->post('/orders/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::store($databaseConfig);
});
$router->post('/orders/{id}/payments', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::recordPayment($id, $databaseConfig);
});
$router->post('/orders/{id}/payments/{paymentId}/correct', static function (string $id, string $paymentId) use ($databaseConfig): void {
    if (RequireAuth::run() && RequireRole::run(['admin'])) OrderController::correctPayment($id, $paymentId, $databaseConfig);
});
$router->post('/orders/{id}/payments/{paymentId}/refund', static function (string $id, string $paymentId) use ($databaseConfig): void {
    if (RequireAuth::run() && RequireRole::run(['admin'])) OrderController::refundPayment($id, $paymentId, $databaseConfig);
});
$router->get('/orders/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::edit($id, $databaseConfig);
});
$router->post('/orders/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::update($id, $databaseConfig);
});
$router->get('/orders/{id}', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::show($id, $databaseConfig);
});
$router->post('/orders/{id}/status', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run()) OrderController::updateStatus($id, $databaseConfig);
});
