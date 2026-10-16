<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Core\Auth;
use App\Middleware\RequireAuth;

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

$router->get('/dashboard', static function (): void {
    if (RequireAuth::run()) {
        DashboardController::index();
    }
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
