<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AuditLogController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\ServiceController;
use App\Controllers\OrderController;
use App\Controllers\NotificationController;
use App\Controllers\PaymentController;
use App\Controllers\ReportController;
use App\Controllers\UserAdminController;
use App\Core\Auth;
use App\Middleware\RequireAuth;
use App\Middleware\RequirePermission;

$router->get('/', static function (): void {
    header('Location: ' . (Auth::check() ? '/dashboard' : '/login'), true, 302);
});

$router->get('/login', static function () use ($databaseConfig): void {
    AuthController::showLogin($databaseConfig);
});

$router->post('/login', static function () use ($databaseConfig): void {
    AuthController::login($databaseConfig);
});

$router->post('/logout', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig)) {
        AuthController::logout($databaseConfig);
    }
});

$router->get('/dashboard', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'dashboard.view')) {
        DashboardController::index($databaseConfig);
    }
});

$router->get('/audit-logs', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'audit_logs.view')) AuditLogController::index($databaseConfig);
});

$router->get('/notifications', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig)) NotificationController::index($databaseConfig);
});
$router->post('/notifications/read', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig)) NotificationController::markRead($databaseConfig);
});

$router->get('/payments', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'payments.view')) PaymentController::index($databaseConfig);
});
$router->get('/payments/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'payments.view')) PaymentController::index($databaseConfig);
});
$router->get('/payments/export', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'payments.view')) PaymentController::export($databaseConfig);
});

$router->get('/reports', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'reports.view')) ReportController::index($databaseConfig);
});
$router->get('/reports/export', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'reports.view')) ReportController::export($databaseConfig);
});

$router->get('/users', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::index($databaseConfig);
});
$router->get('/users/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::create();
});
$router->post('/users/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::store($databaseConfig);
});
$router->get('/roles', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'roles.manage')) UserAdminController::roles($databaseConfig);
});
$router->post('/roles/{id}/permissions', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'roles.manage')) UserAdminController::saveRolePermissions($id, $databaseConfig);
});
$router->get('/account/security', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig)) UserAdminController::accountSecurity();
});
$router->post('/account/security', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig)) UserAdminController::changePassword($databaseConfig);
});
$router->get('/users/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::edit($id, $databaseConfig);
});
$router->post('/users/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::update($id, $databaseConfig);
});
$router->get('/users/{id}/permissions', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.permissions')) UserAdminController::userPermissions($id, $databaseConfig);
});
$router->post('/users/{id}/permissions', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.permissions')) UserAdminController::saveUserPermissions($id, $databaseConfig);
});
$router->get('/users/{id}/reset-password', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::resetPasswordForm($id, $databaseConfig);
});
$router->post('/users/{id}/reset-password', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::resetPassword($id, $databaseConfig);
});
$router->post('/users/{id}/status', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'users.manage')) UserAdminController::toggle($id, $databaseConfig);
});

$router->get('/customers', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.view')) {
        CustomerController::index($databaseConfig);
    }
});

$router->get('/customers/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.view')) {
        CustomerController::searchJson($databaseConfig);
    }
});

$router->get('/customers/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.manage')) {
        CustomerController::create();
    }
});

$router->post('/customers/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.manage')) {
        CustomerController::store($databaseConfig);
    }
});

$router->get('/customers/{id}', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.view')) {
        CustomerController::show($id, $databaseConfig);
    }
});

$router->post('/customers/{id}/delete', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.manage')) {
        CustomerController::deactivate($id, $databaseConfig);
    }
});

$router->get('/customers/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.manage')) {
        CustomerController::edit($id, $databaseConfig);
    }
});

$router->post('/customers/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'customers.manage')) {
        CustomerController::update($id, $databaseConfig);
    }
});

$router->get('/services', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.view')) ServiceController::index($databaseConfig);
});
$router->get('/services/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.view')) ServiceController::searchJson($databaseConfig);
});
$router->get('/services/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.manage')) ServiceController::create();
});
$router->post('/services/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.manage')) ServiceController::store($databaseConfig);
});
$router->get('/services/{id}', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.view')) ServiceController::show($id, $databaseConfig);
});
$router->post('/services/{id}/delete', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.manage')) ServiceController::deactivate($id, $databaseConfig);
});
$router->post('/services/{id}/status', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.manage')) ServiceController::setStatus($id, $databaseConfig);
});
$router->get('/services/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.manage')) ServiceController::edit($id, $databaseConfig);
});
$router->post('/services/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'services.manage')) ServiceController::update($id, $databaseConfig);
});

$router->get('/orders', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.view')) OrderController::index($databaseConfig);
});
$router->get('/orders/search', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.view')) OrderController::index($databaseConfig);
});
$router->get('/orders/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.manage')) OrderController::create($databaseConfig);
});
$router->post('/orders/create', static function () use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.manage')) OrderController::store($databaseConfig);
});
$router->post('/orders/{id}/payments', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'payments.record')) OrderController::recordPayment($id, $databaseConfig);
});
$router->post('/orders/{id}/payments/{paymentId}/correct', static function (string $id, string $paymentId) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'payments.refund')) OrderController::correctPayment($id, $paymentId, $databaseConfig);
});
$router->post('/orders/{id}/payments/{paymentId}/refund', static function (string $id, string $paymentId) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'payments.refund')) OrderController::refundPayment($id, $paymentId, $databaseConfig);
});
$router->get('/orders/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.manage')) OrderController::edit($id, $databaseConfig);
});
$router->post('/orders/{id}/edit', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.manage')) OrderController::update($id, $databaseConfig);
});
$router->get('/orders/{id}', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.view')) OrderController::show($id, $databaseConfig);
});
$router->post('/orders/{id}/status', static function (string $id) use ($databaseConfig): void {
    if (RequireAuth::run($databaseConfig) && RequirePermission::run($databaseConfig, 'orders.manage')) OrderController::updateStatus($id, $databaseConfig);
});
