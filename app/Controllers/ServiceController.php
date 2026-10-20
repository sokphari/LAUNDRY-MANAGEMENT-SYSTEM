<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\Service;
use App\Middleware\RequireRole;
use App\Services\AuditLog as AuditLogService;
use PDOException;

final class ServiceController
{
    private const SERVICE_NAMES = ['Washing Clothes', 'Express Washing', 'Ironing', 'Dry Cleaning'];

    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $status = isset($_GET['status']) && is_string($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'], true) ? $_GET['status'] : 'all';
        try {
            $services = Service::all(Database::connect($databaseConfig), $query, $status);
        } catch (PDOException $exception) {
            self::log($exception);
            http_response_code(500);
            View::render('services/index', self::shared() + ['title' => 'Services', 'services' => [], 'query' => $query, 'status' => $status, 'flash' => self::takeFlash(), 'loadError' => true]);
            return;
        }
        View::render('services/index', self::shared() + ['title' => 'Services', 'services' => $services, 'query' => $query, 'status' => $status, 'flash' => self::takeFlash(), 'loadError' => false]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function searchJson(array $databaseConfig): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $status = isset($_GET['status']) && is_string($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'], true) ? $_GET['status'] : 'all';
        try {
            $services = Service::all(Database::connect($databaseConfig), $query, $status);
            foreach ($services as &$service) {
                $service['created_at_display'] = date('d M Y, H:i', strtotime((string) $service['created_at']));
            }
            unset($service);
            echo json_encode(['services' => $services], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (PDOException $exception) {
            self::log($exception);
            http_response_code(500);
            echo json_encode(['error' => 'Service data is temporarily unavailable.'], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function setStatus(string $id, array $databaseConfig): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ((Auth::user()['role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Only administrators can change service status.']);
            return;
        }
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Your session expired. Reload the page and try again.']);
            return;
        }
        $active = $_POST['is_active'] ?? null;
        if (!ctype_digit($id) || (int) $id < 1 || !in_array($active, ['0', '1', 0, 1], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid service status request.']);
            return;
        }
        try {
            $database = Database::connect($databaseConfig);
            $database->beginTransaction();
            $before = Service::findForUpdate($database, (int) $id);
            if ($before === false) {
                $database->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Service not found.']);
                return;
            }
            $nextActive = (string) $active === '1';
            if (!Service::setActive($database, (int) $id, $nextActive)) {
                $database->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Service status is already up to date.']);
                return;
            }
            $action = $nextActive ? 'service.activated' : 'service.deactivated';
            AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), $action, 'service', (int) $id, ($nextActive ? 'Activated service ' : 'Deactivated service ') . $before['name'], [
                'before' => ['is_active' => (bool) $before['is_active']],
                'after' => ['is_active' => $nextActive],
            ]);
            $database->commit();
            echo json_encode(['success' => true, 'is_active' => (string) $active === '1']);
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::log($exception);
            http_response_code(500);
            echo json_encode(['error' => 'Could not update this service status.']);
        }
    }

    public static function create(): void
    {
        if (!RequireRole::run(['admin'])) return;
        self::renderForm('Add Service', ['name' => '', 'service_type' => '', 'custom_name' => '', 'description' => '', 'price' => '', 'billing_unit' => ''], [], '/services/create');
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function store(array $databaseConfig): void
    {
        if (!RequireRole::run(['admin'])) return;
        if (!self::verifyCsrf()) return;
        $data = self::input();
        $errors = self::validate($data);
        if ($errors !== []) { self::renderForm('Add Service', $data, $errors, '/services/create'); return; }
        try {
            $database = Database::connect($databaseConfig);
            if (Service::nameAndUnitExists($database, $data['name'], $data['billing_unit'])) {
                self::renderForm('Add Service', $data, ['name' => 'A service with this name and billing unit already exists. Edit its price instead.'], '/services/create');
                return;
            }
            $database->beginTransaction();
            Service::create($database, self::databaseInput($data));
            $serviceId = (int) $database->lastInsertId();
            AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), 'service.created', 'service', $serviceId, 'Created service ' . $data['name'], [
                'after' => ['name' => $data['name'], 'description' => $data['description'], 'price' => (float) $data['price'], 'billing_unit' => $data['billing_unit'], 'is_active' => true],
            ]);
            $database->commit();
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::log($exception);
            $errors = self::isDuplicateError($exception)
                ? ['name' => 'A service with this name and billing unit already exists. Edit its price instead.']
                : ['form' => 'Could not save the service. Check the database connection and try again.'];
            self::renderForm('Add Service', $data, $errors, '/services/create');
            return;
        }
        self::flash('success', 'Service added successfully.');
        header('Location: /services', true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function show(string $id, array $databaseConfig): void
    {
        $service = self::find($id, $databaseConfig);
        if ($service === null) return;
        View::render('services/show', self::shared() + ['title' => 'Service details', 'service' => $service]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function edit(string $id, array $databaseConfig): void
    {
        if (!RequireRole::run(['admin'])) return;
        $service = self::find($id, $databaseConfig);
        if ($service !== null) self::renderForm('Edit Service', $service, [], '/services/' . (int) $id . '/edit');
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function update(string $id, array $databaseConfig): void
    {
        if (!RequireRole::run(['admin'])) return;
        if (!self::verifyCsrf()) return;
        $service = self::find($id, $databaseConfig);
        if ($service === null) return;
        $data = self::input();
        $errors = self::validate($data);
        if ($errors !== []) { self::renderForm('Edit Service', $data, $errors, '/services/' . (int) $id . '/edit'); return; }
        try {
            $database = Database::connect($databaseConfig);
            if (Service::nameAndUnitExists($database, $data['name'], $data['billing_unit'], (int) $id)) {
                self::renderForm('Edit Service', $data, ['name' => 'Another service already uses this name and billing unit.'], '/services/' . (int) $id . '/edit');
                return;
            }
            $database->beginTransaction();
            $before = Service::findForUpdate($database, (int) $id);
            if ($before === false) {
                $database->rollBack();
                http_response_code(404);
                echo '404 Not Found';
                return;
            }
            Service::update($database, (int) $id, self::databaseInput($data));
            $changedPrice = (float) $before['price'] !== (float) $data['price'];
            $action = $changedPrice ? 'service.price_updated' : 'service.updated';
            $oldValues = ['name' => (string) $before['name'], 'description' => (string) ($before['description'] ?? ''), 'price' => (float) $before['price'], 'billing_unit' => (string) $before['billing_unit']];
            $newValues = ['name' => $data['name'], 'description' => $data['description'], 'price' => (float) $data['price'], 'billing_unit' => $data['billing_unit']];
            $changedBefore = $changedAfter = [];
            foreach ($newValues as $field => $value) {
                if ($oldValues[$field] !== $value) {
                    $changedBefore[$field] = $oldValues[$field];
                    $changedAfter[$field] = $value;
                }
            }
            if ($changedAfter !== []) {
                AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), $action, 'service', (int) $id, ($changedPrice ? 'Updated price for service ' : 'Updated service ') . $data['name'], [
                    'before' => $changedBefore,
                    'after' => $changedAfter,
                ]);
            }
            $database->commit();
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::log($exception);
            $errors = self::isDuplicateError($exception)
                ? ['name' => 'Another service already uses this name and billing unit.']
                : ['form' => 'Could not update the service. Check the database connection and try again.'];
            self::renderForm('Edit Service', $data, $errors, '/services/' . (int) $id . '/edit');
            return;
        }
        self::flash('success', 'Service updated successfully.');
        header('Location: /services', true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function deactivate(string $id, array $databaseConfig): void
    {
        if (!RequireRole::run(['admin'])) return;
        if (!self::verifyCsrf()) return;
        $service = self::find($id, $databaseConfig);
        if ($service === null) return;
        try {
            $database = Database::connect($databaseConfig);
            $database->beginTransaction();
            $before = Service::findForUpdate($database, (int) $id);
            if ($before === false) {
                $database->rollBack();
                http_response_code(404);
                echo '404 Not Found';
                return;
            }
            if (Service::deactivate($database, (int) $id)) {
                AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), 'service.deactivated', 'service', (int) $id, 'Deactivated service ' . $before['name'], [
                    'before' => ['is_active' => true], 'after' => ['is_active' => false],
                ]);
            }
            $database->commit();
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::log($exception);
            self::flash('error', 'Could not deactivate the service. Please try again.');
            header('Location: /services', true, 303);
            return;
        }
        self::flash('success', 'Service deactivated. Existing records were preserved.');
        header('Location: /services', true, 303);
    }

    /** @return array{name: string, service_type: string, custom_name: string, description: string, price: string, billing_unit: string} */
    private static function input(): array
    {
        $read = static fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        $type = $read('service_type');
        $customName = $read('custom_name');
        return [
            'name' => in_array($type, self::SERVICE_NAMES, true) ? $type : ($type === 'Other' ? $customName : ''),
            'service_type' => $type,
            'custom_name' => $customName,
            'description' => $read('description'),
            'price' => $read('price'),
            'billing_unit' => $read('billing_unit'),
        ];
    }

    /** @param array{name: string, service_type: string, custom_name: string, description: string, price: string, billing_unit: string} $data
     * @return array<string, string>
     */
    private static function validate(array $data): array
    {
        $errors = [];
        if (!in_array($data['service_type'], [...self::SERVICE_NAMES, 'Other'], true)) $errors['name'] = 'Choose a service name.';
        elseif ($data['name'] === '' || mb_strlen($data['name']) > 120) $errors['name'] = 'Enter a custom service name up to 120 characters.';
        if ($data['description'] !== '' && mb_strlen($data['description']) > 5000) $errors['description'] = 'Description must be 5000 characters or fewer.';
        if ($data['price'] === '' || !is_numeric($data['price']) || (float) $data['price'] < 0 || (float) $data['price'] > 9999999999.99) $errors['price'] = 'Enter a valid price greater than or equal to zero.';
        if (!in_array($data['billing_unit'], ['kg', 'piece'], true)) $errors['billing_unit'] = 'Choose kg or piece.';
        return $errors;
    }

    /** @param array{name: string, service_type: string, custom_name: string, description: string, price: string, billing_unit: string} $data
     * @return array{name: string, description: string, price: string, billing_unit: string}
     */
    private static function databaseInput(array $data): array
    {
        return ['name' => $data['name'], 'description' => $data['description'], 'price' => $data['price'], 'billing_unit' => $data['billing_unit']];
    }

    /** @param array<string, mixed> $service @param array<string, string> $errors */
    private static function renderForm(string $heading, array $service, array $errors, string $formAction): void
    {
        View::render('services/form', self::shared() + ['title' => $heading, 'heading' => $heading, 'service' => $service, 'errors' => $errors, 'formAction' => $formAction]);
    }

    /** @param array<string, mixed> $databaseConfig @return array<string, mixed>|null */
    private static function find(string $id, array $databaseConfig): ?array
    {
        if (!ctype_digit($id) || (int) $id < 1) { http_response_code(404); echo '404 Not Found'; return null; }
        try { $service = Service::find(Database::connect($databaseConfig), (int) $id); }
        catch (PDOException $exception) { self::log($exception); http_response_code(500); echo 'Service data is temporarily unavailable.'; return null; }
        if ($service === false) { http_response_code(404); echo '404 Not Found'; return null; }
        return $service;
    }

    private static function verifyCsrf(): bool
    {
        if (Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) return true;
        http_response_code(419); echo 'Your session expired. Please go back and try again.'; return false;
    }

    private static function isDuplicateError(PDOException $exception): bool
    {
        return (string) ($exception->errorInfo[0] ?? $exception->getCode()) === '23000';
    }

    /** @return array{csrfToken: string, user: array<string, mixed>|null} */
    private static function shared(): array { return ['csrfToken' => Csrf::token(), 'user' => Auth::user()]; }
    private static function flash(string $type, string $message): void { $_SESSION['_flash'] = ['type' => $type, 'message' => $message]; }
    /** @return array<string, string>|null */
    private static function takeFlash(): ?array { $flash = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return is_array($flash) ? $flash : null; }
    private static function log(PDOException $exception): void { file_put_contents(BASE_PATH . '/storage/logs/app.log', sprintf("[%s] [services] %s\n", date(DATE_ATOM), $exception->getMessage()), FILE_APPEND | LOCK_EX); }
}
