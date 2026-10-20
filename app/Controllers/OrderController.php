<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Services\InvalidOrderException;
use App\Services\OrderService;
use PDOException;

final class OrderController
{
    private const STATUSES = ['received', 'processing', 'ready', 'collected', 'cancelled'];

    /** @param array<string, mixed> $databaseConfig */
    public static function index(array $databaseConfig): void
    {
        $perPage = 10;
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = max(1, is_int($requestedPage) ? $requestedPage : 1);
        $totalOrders = 0;
        $totalPages = 1;
        $filters = [
            'query' => self::getString('q'),
            'status' => in_array(self::getString('status'), self::STATUSES, true) ? self::getString('status') : 'all',
            'from' => self::validDate(self::getString('from')) ? self::getString('from') : '',
            'to' => self::validDate(self::getString('to')) ? self::getString('to') : '',
        ];
        try {
            $database = Database::connect($databaseConfig);
            $totalOrders = Order::count($database, $filters);
            $totalPages = max(1, (int) ceil($totalOrders / $perPage));
            $page = min($page, $totalPages);
            $orders = Order::all($database, $filters, $perPage, ($page - 1) * $perPage);
        } catch (PDOException $exception) {
            self::log($exception);
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['error' => 'Order data is temporarily unavailable.']);
                return;
            }
            http_response_code(500);
            $orders = [];
        }
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            foreach ($orders as &$order) {
                $order['received_display'] = date('d M Y', strtotime((string) $order['received_at']));
                $order['pickup_display'] = date('d M Y', strtotime((string) $order['expected_pickup_at']));
            }
            unset($order);
            echo json_encode(['orders' => $orders, 'page' => $page, 'per_page' => $perPage, 'total' => $totalOrders, 'total_pages' => $totalPages], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }
        View::render('orders/index', self::shared() + ['title' => 'Orders', 'orders' => $orders, 'filters' => $filters, 'page' => $page, 'perPage' => $perPage, 'totalOrders' => $totalOrders, 'totalPages' => $totalPages, 'flash' => self::takeFlash()]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function create(array $databaseConfig): void
    {
        try {
            $database = Database::connect($databaseConfig);
            $customers = Customer::activeOptions($database);
            $services = Service::all($database, '', 'active');
        } catch (PDOException $exception) {
            self::log($exception);
            http_response_code(500);
            echo 'Order form data is temporarily unavailable. Check the database and migrations.';
            return;
        }
        self::renderForm($customers, $services, [
            'customer_id' => '', 'received_at' => date('Y-m-d'), 'expected_pickup_at' => date('Y-m-d'),
            'notes' => '', 'payment_method' => 'cash', 'deposit' => '0', 'items' => [['service_id' => '', 'quantity' => '']],
        ], []);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function store(array $databaseConfig): void
    {
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419); echo 'Your session expired. Please go back and try again.'; return;
        }
        $input = self::input();
        $errors = self::validate($input);
        if ($errors !== []) {
            self::renderCreateWithInput($databaseConfig, $input, $errors);
            return;
        }
        try {
            $orderId = OrderService::create(Database::connect($databaseConfig), (int) (Auth::user()['id'] ?? 0), $input);
        } catch (InvalidOrderException $exception) {
            self::renderCreateWithInput($databaseConfig, $input, $exception->errors);
            return;
        } catch (PDOException $exception) {
            self::log($exception);
            self::renderCreateWithInput($databaseConfig, $input, ['form' => 'Could not save the order. Check the database connection and try again.']);
            return;
        }
        self::flash('success', 'Order created successfully.');
        header('Location: /orders/' . $orderId, true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function show(string $id, array $databaseConfig): void
    {
        if (!ctype_digit($id) || (int) $id < 1) { http_response_code(404); echo '404 Not Found'; return; }
        try {
            $data = Order::find(Database::connect($databaseConfig), (int) $id);
        } catch (PDOException $exception) {
            self::log($exception); http_response_code(500); echo 'Order details are temporarily unavailable.'; return;
        }
        if ($data === false) { http_response_code(404); echo '404 Not Found'; return; }
        View::render('orders/show', self::shared() + $data + ['title' => 'Order ' . $data['order']['order_code'], 'flash' => self::takeFlash(), 'allowedStatuses' => self::nextStatuses((string) $data['order']['status'])]);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function edit(string $id, array $databaseConfig): void
    {
        if (!ctype_digit($id) || (int) $id < 1) { http_response_code(404); echo '404 Not Found'; return; }
        try {
            $database = Database::connect($databaseConfig);
            $data = Order::find($database, (int) $id);
            if ($data === false) { http_response_code(404); echo '404 Not Found'; return; }
            if (in_array($data['order']['status'], ['collected', 'cancelled'], true)) { http_response_code(403); echo 'Collected or cancelled orders cannot be edited.'; return; }
            $customers = Customer::activeOptions($database);
            if (!array_filter($customers, static fn (array $customer): bool => (int) $customer['id'] === (int) $data['order']['customer_id'])) {
                $currentCustomer = $database->prepare('SELECT id, name, phone FROM customers WHERE id = :id');
                $currentCustomer->execute(['id' => (int) $data['order']['customer_id']]);
                $record = $currentCustomer->fetch();
                if ($record !== false) $customers[] = $record;
            }
            $services = Service::all($database);
            $orderInput = [
                'customer_id' => (string) $data['order']['customer_id'],
                'received_at' => date('Y-m-d', strtotime((string) $data['order']['received_at'])),
                'expected_pickup_at' => date('Y-m-d', strtotime((string) $data['order']['expected_pickup_at'])),
                'notes' => (string) ($data['order']['notes'] ?? ''),
                'items' => array_map(static fn (array $item): array => ['service_id' => (string) $item['service_id'], 'quantity' => (string) $item['quantity'], 'unit_price' => (string) $item['unit_price']], $data['items']),
            ];
            self::renderForm($customers, $services, $orderInput, [], true, (int) $id, (float) $data['order']['paid_amount']);
        } catch (PDOException $exception) {
            self::log($exception); http_response_code(500); echo 'Order data is temporarily unavailable.';
        }
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function update(string $id, array $databaseConfig): void
    {
        if (!ctype_digit($id) || (int) $id < 1) { http_response_code(404); echo '404 Not Found'; return; }
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419); echo 'Your session expired. Please go back and try again.'; return;
        }
        $input = self::input();
        $errors = self::validate($input, true);
        if ($errors !== []) { self::renderEditWithInput($databaseConfig, (int) $id, $input, $errors); return; }
        try {
            OrderService::update(Database::connect($databaseConfig), (int) $id, (int) (Auth::user()['id'] ?? 0), $input);
        } catch (InvalidOrderException $exception) {
            self::renderEditWithInput($databaseConfig, (int) $id, $input, $exception->errors);
            return;
        } catch (PDOException $exception) {
            self::log($exception);
            self::renderEditWithInput($databaseConfig, (int) $id, $input, ['form' => 'Could not update the order. Check the database connection and try again.']);
            return;
        }
        self::flash('success', 'Order updated successfully.');
        header('Location: /orders/' . (int) $id, true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function updateStatus(string $id, array $databaseConfig): void
    {
        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            if ($wantsJson) { http_response_code(419); echo json_encode(['error' => 'Your session expired. Reload the page and try again.']); return; }
            http_response_code(419); echo 'Your session expired. Please go back and try again.'; return;
        }
        $newStatus = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
        $cancellationReason = self::postString('reason');
        if (!ctype_digit($id) || (int) $id < 1 || !in_array($newStatus, self::STATUSES, true)) {
            if ($wantsJson) { http_response_code(422); echo json_encode(['error' => 'Invalid order status request.']); return; }
            http_response_code(422); echo 'Invalid order status request.'; return;
        }
        if ($newStatus === 'cancelled' && ($cancellationReason === '' || mb_strlen($cancellationReason) > 500)) {
            $message = $cancellationReason === '' ? 'Enter a cancellation reason.' : 'Cancellation reason must be 500 characters or fewer.';
            if ($wantsJson) { http_response_code(422); echo json_encode(['error' => $message]); return; }
            self::flash('error', $message);
            header('Location: ' . self::statusReturnTarget(), true, 303); return;
        }
        try {
            $changed = OrderService::changeStatus(Database::connect($databaseConfig), (int) $id, (int) (Auth::user()['id'] ?? 0), $newStatus, $cancellationReason);
            if (!$changed) { http_response_code(404); if ($wantsJson) echo json_encode(['error' => 'Order not found.']); else echo '404 Not Found'; return; }
        } catch (InvalidOrderException $exception) {
            if ($wantsJson) { http_response_code(422); echo json_encode(['error' => $exception->errors['status'] ?? 'That status change is not allowed.']); return; }
            self::flash('error', $exception->errors['status'] ?? 'That status change is not allowed.');
            header('Location: ' . self::statusReturnTarget(), true, 303); return;
        } catch (PDOException $exception) {
            if ($wantsJson) { self::log($exception); http_response_code(500); echo json_encode(['error' => 'Could not update the order status.']); return; }
            self::log($exception); self::flash('error', 'Could not update the order status.');
            header('Location: ' . self::statusReturnTarget(), true, 303); return;
        }
        if ($wantsJson) {
            $user = Auth::user();
            echo json_encode([
                'success' => true,
                'status' => $newStatus,
                'status_label' => ucfirst($newStatus),
                'allowed_statuses' => self::nextStatuses($newStatus),
                'username' => $user['username'] ?? '',
                'changed_at' => date('d M Y, H:i'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }
        self::flash('success', 'Order status updated.');
        header('Location: ' . self::statusReturnTarget('/orders/' . (int) $id), true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function recordPayment(string $id, array $databaseConfig): void
    {
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419); echo 'Your session expired. Please go back and try again.'; return;
        }
        $amount = self::postString('amount');
        $method = self::postString('method');
        if (!ctype_digit($id) || (int) $id < 1) { http_response_code(404); echo '404 Not Found'; return; }
        $returnTarget = self::statusReturnTarget('/orders/' . (int) $id);
        if (!self::validMoney($amount) || (float) $amount <= 0 || !in_array($method, ['cash', 'khqr'], true)) {
            self::flash('error', 'Enter a valid payment amount and method.');
            header('Location: ' . $returnTarget, true, 303); return;
        }
        try {
            OrderService::addPayment(Database::connect($databaseConfig), (int) $id, (int) (Auth::user()['id'] ?? 0), $amount, $method);
        } catch (InvalidOrderException $exception) {
            self::flash('error', $exception->errors['payment'] ?? 'Could not record payment.');
            header('Location: ' . $returnTarget, true, 303); return;
        } catch (PDOException $exception) {
            self::log($exception);
            self::flash('error', 'Could not record payment. Check the database connection and migrations.');
            header('Location: ' . $returnTarget, true, 303); return;
        }
        self::flash('success', 'Payment recorded successfully.');
        header('Location: ' . $returnTarget, true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function correctPayment(string $id, string $paymentId, array $databaseConfig): void
    {
        if (!ctype_digit($id) || (int) $id < 1 || !ctype_digit($paymentId) || (int) $paymentId < 1) { http_response_code(404); echo '404 Not Found'; return; }
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419); echo 'Your session expired. Please go back and try again.'; return;
        }
        $reason = self::postString('reason');
        $amount = self::postString('replacement_amount');
        $method = self::postString('replacement_method');
        if ($reason === '' || mb_strlen($reason) > 500 || ($amount !== '' && (!self::validMoney($amount) || (float) $amount <= 0))) {
            self::flash('error', $reason === '' ? 'Enter a reason for correcting this payment.' : ($amount !== '' ? 'Enter a valid replacement payment amount.' : 'Correction reason must be 500 characters or fewer.'));
            header('Location: /orders/' . (int) $id, true, 303); return;
        }
        try {
            OrderService::correctPayment(Database::connect($databaseConfig), (int) $id, (int) $paymentId, (int) (Auth::user()['id'] ?? 0), $reason, $amount, $method);
        } catch (InvalidOrderException $exception) {
            self::flash('error', $exception->errors['payment'] ?? $exception->errors['reason'] ?? 'Could not correct this payment.');
            header('Location: /orders/' . (int) $id, true, 303); return;
        } catch (PDOException $exception) {
            self::log($exception);
            self::flash('error', 'Could not correct this payment. Check the database connection and migrations.');
            header('Location: /orders/' . (int) $id, true, 303); return;
        }
        self::flash('success', 'Payment corrected. The original entry remains in the payment history.');
        header('Location: /orders/' . (int) $id, true, 303);
    }

    /** @param array<string, mixed> $databaseConfig */
    public static function refundPayment(string $id, string $paymentId, array $databaseConfig): void
    {
        if (!ctype_digit($id) || (int) $id < 1 || !ctype_digit($paymentId) || (int) $paymentId < 1) { http_response_code(404); echo '404 Not Found'; return; }
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419); echo 'Your session expired. Please go back and try again.'; return;
        }
        $amount = self::postString('amount');
        $method = self::postString('method');
        $reason = self::postString('reason');
        if (!self::validMoney($amount) || (float) $amount <= 0 || !in_array($method, ['cash', 'khqr'], true) || $reason === '' || mb_strlen($reason) > 500) {
            self::flash('error', $reason === '' ? 'Enter a reason for the refund.' : (!self::validMoney($amount) || (float) $amount <= 0 ? 'Enter a valid refund amount.' : (!in_array($method, ['cash', 'khqr'], true) ? 'Choose a valid refund method.' : 'Refund reason must be 500 characters or fewer.')));
            header('Location: /orders/' . (int) $id, true, 303); return;
        }
        try {
            OrderService::refundPayment(Database::connect($databaseConfig), (int) $id, (int) $paymentId, (int) (Auth::user()['id'] ?? 0), $amount, $method, $reason);
        } catch (InvalidOrderException $exception) {
            self::flash('error', $exception->errors['refund'] ?? 'Could not record this refund.');
            header('Location: /orders/' . (int) $id, true, 303); return;
        } catch (PDOException $exception) {
            self::log($exception);
            self::flash('error', 'Could not record this refund. Check the database connection and migrations.');
            header('Location: /orders/' . (int) $id, true, 303); return;
        }
        self::flash('success', 'Refund recorded successfully.');
        header('Location: /orders/' . (int) $id, true, 303);
    }

    /** @return array{customer_id: int, received_at: string, expected_pickup_at: string, notes: string, payment_method: string, deposit: string, items: list<array{service_id: int, quantity: string}>} */
    private static function input(): array
    {
        $items = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!is_array($item)) continue;
                $serviceId = isset($item['service_id']) && is_scalar($item['service_id']) ? (string) $item['service_id'] : '';
                $quantity = isset($item['quantity']) && is_string($item['quantity']) ? trim($item['quantity']) : '';
                if ($serviceId === '' && $quantity === '') continue;
                $items[] = ['service_id' => ctype_digit($serviceId) ? (int) $serviceId : 0, 'quantity' => $quantity];
            }
        }
        return [
            'customer_id' => isset($_POST['customer_id']) && is_string($_POST['customer_id']) && ctype_digit($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0,
            'received_at' => self::postString('received_at'),
            'expected_pickup_at' => self::postString('expected_pickup_at'),
            'notes' => self::postString('notes'),
            'payment_method' => self::postString('payment_method'),
            'deposit' => self::postString('deposit'),
            'items' => $items,
        ];
    }

    /** @param array{customer_id: int, received_at: string, expected_pickup_at: string, notes: string, payment_method: string, deposit: string, items: list<array{service_id: int, quantity: string}>} $input
     * @return array<string, string>
     */
    private static function validate(array $input, bool $editing = false): array
    {
        $errors = [];
        if ($input['customer_id'] < 1) $errors['customer_id'] = 'Choose a customer.';
        if (!self::validDate($input['received_at'])) $errors['received_at'] = 'Enter a valid received date.';
        if (!self::validDate($input['expected_pickup_at'])) $errors['expected_pickup_at'] = 'Enter a valid pickup date.';
        if (self::validDate($input['received_at']) && self::validDate($input['expected_pickup_at']) && $input['expected_pickup_at'] < $input['received_at']) $errors['expected_pickup_at'] = 'Pickup date must be on or after the received date.';
        if (mb_strlen($input['notes']) > 5000) $errors['notes'] = 'Notes must be 5000 characters or fewer.';
        if (!$editing && !self::validMoney($input['deposit'])) $errors['deposit'] = 'Enter a valid deposit amount.';
        if (!$editing && !in_array($input['payment_method'], ['cash', 'khqr'], true)) $errors['payment_method'] = 'Choose a valid payment method.';
        if ($input['items'] === []) $errors['items'] = 'Add at least one service.';
        foreach ($input['items'] as $item) {
            if ($item['service_id'] < 1 || $item['quantity'] === '') {
                $errors['items'] = 'Choose a service and enter its quantity.';
                break;
            }
        }
        return $errors;
    }

    /** @param list<array<string, mixed>> $customers @param list<array<string, mixed>> $services
     * @param array<string, mixed> $order @param array<string, string> $errors
     */
    private static function renderForm(array $customers, array $services, array $order, array $errors, bool $editing = false, ?int $orderId = null, float $paidAmount = 0): void
    {
        View::render('orders/form', self::shared() + [
            'title' => $editing ? 'Edit Order' : 'Create Order', 'customers' => $customers, 'services' => $services,
            'orderInput' => $order, 'errors' => $errors, 'isEdit' => $editing, 'orderId' => $orderId, 'paidAmount' => $paidAmount,
        ]);
    }

    /** @param array<string, mixed> $databaseConfig @param array<string, mixed> $input @param array<string, string> $errors */
    private static function renderCreateWithInput(array $databaseConfig, array $input, array $errors): void
    {
        try {
            $database = Database::connect($databaseConfig);
            self::renderForm(Customer::activeOptions($database), Service::all($database, '', 'active'), $input, $errors);
        } catch (PDOException $exception) {
            self::log($exception); http_response_code(500); echo 'Order form data is temporarily unavailable.';
        }
    }

    /** @param array<string, mixed> $databaseConfig @param array<string, mixed> $input @param array<string, string> $errors */
    private static function renderEditWithInput(array $databaseConfig, int $orderId, array $input, array $errors): void
    {
        try {
            $database = Database::connect($databaseConfig);
            $data = Order::find($database, $orderId);
            if ($data === false) { http_response_code(404); echo '404 Not Found'; return; }
            if (in_array($data['order']['status'], ['collected', 'cancelled'], true)) { http_response_code(403); echo 'Collected or cancelled orders cannot be edited.'; return; }
            $savedPrices = [];
            foreach ($data['items'] as $item) $savedPrices[(string) $item['service_id']] = (string) $item['unit_price'];
            foreach ($input['items'] as &$item) {
                if (isset($savedPrices[(string) $item['service_id']])) $item['unit_price'] = $savedPrices[(string) $item['service_id']];
            }
            unset($item);
            $customers = Customer::activeOptions($database);
            if (!array_filter($customers, static fn (array $customer): bool => (int) $customer['id'] === (int) $input['customer_id'])) {
                $currentCustomer = $database->prepare('SELECT id, name, phone FROM customers WHERE id = :id');
                $currentCustomer->execute(['id' => (int) $data['order']['customer_id']]);
                $record = $currentCustomer->fetch();
                if ($record !== false) $customers[] = $record;
            }
            self::renderForm($customers, Service::all($database), $input, $errors, true, $orderId, (float) $data['order']['paid_amount']);
        } catch (PDOException $exception) {
            self::log($exception); http_response_code(500); echo 'Order form data is temporarily unavailable.';
        }
    }

    /** @return list<string> */
    private static function nextStatuses(string $current): array
    {
        return match ($current) {
            'received' => ['processing', 'cancelled'],
            'processing' => ['ready', 'cancelled'],
            'ready' => ['collected', 'cancelled'],
            default => [],
        };
    }

    private static function validMoney(string $amount): bool { return preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/', $amount) === 1; }
    private static function getString(string $key): string { return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : ''; }
    private static function postString(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : ''; }
    private static function validDate(string $date): bool { $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date); return $parsed !== false && $parsed->format('Y-m-d') === $date; }
    /** @return array{csrfToken: string, user: array<string, mixed>|null} */
    private static function shared(): array { return ['csrfToken' => Csrf::token(), 'user' => Auth::user()]; }
    private static function flash(string $type, string $message): void { $_SESSION['_flash'] = ['type' => $type, 'message' => $message]; }
    /** @return array<string, string>|null */
    private static function takeFlash(): ?array { $flash = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return is_array($flash) ? $flash : null; }
    private static function log(PDOException $exception): void { file_put_contents(BASE_PATH . '/storage/logs/app.log', sprintf("[%s] [orders] %s\n", date(DATE_ATOM), $exception->getMessage()), FILE_APPEND | LOCK_EX); }

    private static function statusReturnTarget(string $fallback = '/orders'): string
    {
        $target = isset($_POST['_return_to']) && is_string($_POST['_return_to']) ? $_POST['_return_to'] : '';
        $parts = parse_url($target);
        if ($target !== '' && !str_contains($target, "\r") && !str_contains($target, "\n") && is_array($parts) && ($parts['path'] ?? '') === '/orders' && !isset($parts['host'], $parts['scheme'], $parts['user'])) {
            return $target;
        }
        return $fallback;
    }
}
