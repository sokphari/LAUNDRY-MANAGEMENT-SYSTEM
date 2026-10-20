<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\Customer;
use App\Services\AuditLog as AuditLogService;
use PDOException;

final class CustomerController
{
    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        try {
            $customers = $query === ''
                ? Customer::all(Database::connect($databaseConfig))
                : Customer::search(Database::connect($databaseConfig), $query);
        } catch (PDOException $exception) {
            self::logDatabaseError($exception);
            self::renderError(self::databaseErrorMessage($exception, 'load'));
            return;
        }

        View::render('customers/index', self::sharedData() + [
            'title' => 'Customers',
            'customers' => $customers,
            'query' => $query,
            'flash' => self::takeFlash(),
        ]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function searchJson(array $databaseConfig): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

        try {
            $customers = $query === ''
                ? Customer::all(Database::connect($databaseConfig))
                : Customer::search(Database::connect($databaseConfig), $query);
            echo json_encode(['customers' => $customers], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (PDOException $exception) {
            self::logDatabaseError($exception);
            http_response_code(500);
            echo json_encode(['error' => 'Customer search is temporarily unavailable.'], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    public static function create(): void
    {
        View::render('customers/form', self::sharedData() + [
            'title' => 'Add Customer',
            'heading' => 'Add Customer',
            'customer' => ['name' => '', 'phone' => '', 'address' => '', 'notes' => ''],
            'errors' => [],
            'formAction' => '/customers/create',
        ]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function store(array $databaseConfig): void
    {
        if (!self::verifyCsrf()) {
            return;
        }

        $customer = self::formInput();
        $errors = self::validate($customer);
        if ($errors !== []) {
            self::renderForm('Add Customer', $customer, $errors, '/customers/create');
            return;
        }

        try {
            $database = Database::connect($databaseConfig);
            $database->beginTransaction();
            Customer::create($database, $customer);
            $customerId = (int) $database->lastInsertId();
            AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), 'customer.created', 'customer', $customerId, 'Created customer ' . $customer['name'], [
                'after' => ['name' => $customer['name'], 'phone' => $customer['phone'], 'address' => $customer['address'], 'notes' => $customer['notes']],
            ]);
            $database->commit();
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::logDatabaseError($exception);
            self::renderForm('Add Customer', $customer, ['form' => self::databaseErrorMessage($exception, 'save')], '/customers/create');
            return;
        }

        self::setFlash('success', 'Customer added successfully.');
        header('Location: /customers', true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function edit(string $id, array $databaseConfig): void
    {
        $customer = self::findCustomer($id, $databaseConfig);
        if ($customer === null) {
            return;
        }

        self::renderForm('Edit Customer', $customer, [], '/customers/' . (int) $id . '/edit');
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function show(string $id, array $databaseConfig): void
    {
        $customer = self::findCustomer($id, $databaseConfig);
        if ($customer === null) {
            return;
        }

        View::render('customers/show', self::sharedData() + [
            'title' => 'Customer details',
            'customer' => $customer,
        ]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function deactivate(string $id, array $databaseConfig): void
    {
        if (!self::verifyCsrf()) {
            return;
        }

        if (!ctype_digit($id) || (int) $id < 1) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        try {
            $database = Database::connect($databaseConfig);
            $database->beginTransaction();
            $before = Customer::findForUpdate($database, (int) $id);
            if ($before === false || !Customer::deactivate($database, (int) $id)) {
                $database->rollBack();
                http_response_code(404);
                echo '404 Not Found';
                return;
            }
            AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), 'customer.deactivated', 'customer', (int) $id, 'Deactivated customer ' . $before['name'], [
                'before' => ['is_active' => true], 'after' => ['is_active' => false],
            ]);
            $database->commit();
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::logDatabaseError($exception);
            self::setFlash('error', 'Could not deactivate this customer. Please try again.');
            header('Location: /customers', true, 303);
            return;
        }

        self::setFlash('success', 'Customer deactivated. Existing records were preserved.');
        header('Location: /customers', true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function update(string $id, array $databaseConfig): void
    {
        if (!self::verifyCsrf()) {
            return;
        }

        $customer = self::findCustomer($id, $databaseConfig);
        if ($customer === null) {
            return;
        }

        $input = self::formInput();
        $errors = self::validate($input);
        if ($errors !== []) {
            self::renderForm('Edit Customer', $input, $errors, '/customers/' . (int) $id . '/edit');
            return;
        }

        try {
            $database = Database::connect($databaseConfig);
            $database->beginTransaction();
            $before = Customer::findForUpdate($database, (int) $id);
            if ($before === false) {
                $database->rollBack();
                http_response_code(404);
                echo '404 Not Found';
                return;
            }
            Customer::update($database, (int) $id, $input);
            $oldValues = ['name' => (string) $before['name'], 'phone' => (string) $before['phone'], 'address' => (string) ($before['address'] ?? ''), 'notes' => (string) ($before['notes'] ?? '')];
            $newValues = $input;
            $changedBefore = $changedAfter = [];
            foreach ($newValues as $field => $value) {
                if ($oldValues[$field] !== $value) {
                    $changedBefore[$field] = $oldValues[$field];
                    $changedAfter[$field] = $value;
                }
            }
            if ($changedAfter !== []) {
                AuditLogService::record($database, (int) (Auth::user()['id'] ?? 0), 'customer.updated', 'customer', (int) $id, 'Updated customer ' . $input['name'], [
                    'before' => $changedBefore,
                    'after' => $changedAfter,
                ]);
            }
            $database->commit();
        } catch (PDOException $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            self::logDatabaseError($exception);
            self::renderForm('Edit Customer', $input, ['form' => self::databaseErrorMessage($exception, 'update')], '/customers/' . (int) $id . '/edit');
            return;
        }

        self::setFlash('success', 'Customer updated successfully.');
        header('Location: /customers', true, 303);
    }

    /** @return array{name: string, phone: string, address: string, notes: string} */
    private static function formInput(): array
    {
        return [
            'name' => self::postString('name'),
            'phone' => self::postString('phone'),
            'address' => self::postString('address'),
            'notes' => self::postString('notes'),
        ];
    }

    private static function postString(string $key): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    }

    /** @param array{name: string, phone: string, address: string, notes: string} $customer
     *  @return array<string, string>
     */
    private static function validate(array $customer): array
    {
        $errors = [];
        if ($customer['name'] === '') {
            $errors['name'] = 'Enter the customer name.';
        } elseif (mb_strlen($customer['name']) > 120) {
            $errors['name'] = 'Name must be 120 characters or fewer.';
        }

        if ($customer['phone'] === '') {
            $errors['phone'] = 'Enter the customer phone number.';
        } elseif (mb_strlen($customer['phone']) > 20) {
            $errors['phone'] = 'Phone must be 20 characters or fewer.';
        }

        if (mb_strlen($customer['address']) > 5000) {
            $errors['address'] = 'Address is too long.';
        }
        if (mb_strlen($customer['notes']) > 5000) {
            $errors['notes'] = 'Notes are too long.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $customer */
    private static function renderForm(string $heading, array $customer, array $errors, string $formAction): void
    {
        View::render('customers/form', self::sharedData() + [
            'title' => $heading,
            'heading' => $heading,
            'customer' => $customer,
            'errors' => $errors,
            'formAction' => $formAction,
        ]);
    }

    /** @param array<string, mixed> $databaseConfig
     *  @return array<string, mixed>|null
     */
    private static function findCustomer(string $id, array $databaseConfig): ?array
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            http_response_code(404);
            echo '404 Not Found';
            return null;
        }

        try {
            $customer = Customer::find(Database::connect($databaseConfig), (int) $id);
        } catch (PDOException $exception) {
            self::logDatabaseError($exception);
            self::renderError(self::databaseErrorMessage($exception, 'load'));
            return null;
        }

        if ($customer === false) {
            http_response_code(404);
            echo '404 Not Found';
            return null;
        }

        return $customer;
    }

    private static function verifyCsrf(): bool
    {
        if (Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            return true;
        }

        http_response_code(419);
        echo 'Your session expired. Please go back and try again.';
        return false;
    }

    /** @return array{csrfToken: string, user: array<string, mixed>|null} */
    private static function sharedData(): array
    {
        return ['csrfToken' => Csrf::token(), 'user' => Auth::user()];
    }

    private static function setFlash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    /** @return array{type: string, message: string}|null */
    private static function takeFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : null;
    }

    private static function renderError(string $message): void
    {
        http_response_code(500);
        View::render('customers/error', self::sharedData() + ['title' => 'Customers unavailable', 'message' => $message]);
    }

    private static function logDatabaseError(PDOException $exception): void
    {
        $logMessage = sprintf("[%s] [customers] %s\n", date(DATE_ATOM), $exception->getMessage());
        file_put_contents(BASE_PATH . '/storage/logs/app.log', $logMessage, FILE_APPEND | LOCK_EX);
    }

    private static function databaseErrorMessage(PDOException $exception, string $action): string
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if ($sqlState === '42S02') {
            return 'The customers table is missing. Run `php database/migrate.php`, then try again.';
        }

        return match ($action) {
            'save' => 'Could not save the customer. Check the form and database connection, then try again.',
            'update' => 'Could not update the customer. Check the database connection, then try again.',
            default => 'Customer data is temporarily unavailable. Check the database connection and migrations.',
        };
    }
}
