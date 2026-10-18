<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class OrderService
{
    /** @param array{customer_id: int, received_at: string, expected_pickup_at: string, notes: string, payment_method: string, deposit: string, items: list<array{service_id: int, quantity: string}>} $data */
    public static function create(PDO $database, int $userId, array $data): int
    {
        $database->beginTransaction();
        try {
            $customerStatement = $database->prepare('SELECT id FROM customers WHERE id = :id AND is_active = 1 FOR UPDATE');
            $customerStatement->execute(['id' => $data['customer_id']]);
            if ($customerStatement->fetchColumn() === false) {
                throw new InvalidOrderException(['customer_id' => 'Choose an active customer.']);
            }
            if ($data['items'] === [] || count($data['items']) > 3) {
                throw new InvalidOrderException(['items' => 'Add between 1 and 3 service items.']);
            }

            $code = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $insertOrder = $database->prepare(
                'INSERT INTO orders (order_code, customer_id, created_by, received_at, expected_pickup_at, status, notes)
                 VALUES (:order_code, :customer_id, :created_by, :received_at, :expected_pickup_at, \'received\', :notes)'
            );
            $insertOrder->execute([
                'order_code' => $code,
                'customer_id' => $data['customer_id'],
                'created_by' => $userId,
                'received_at' => $data['received_at'] . ' 00:00:00',
                'expected_pickup_at' => $data['expected_pickup_at'] . ' 00:00:00',
                'notes' => $data['notes'] === '' ? null : $data['notes'],
            ]);
            $orderId = (int) $database->lastInsertId();

            $seenServices = [];
            $findService = $database->prepare('SELECT id, name, billing_unit, price FROM services WHERE id = :id AND is_active = 1 FOR UPDATE');
            $lineTotalStatement = $database->prepare('SELECT ROUND(CAST(:price AS DECIMAL(12,2)) * CAST(:quantity AS DECIMAL(10,3)), 2)');
            $insertItem = $database->prepare(
                'INSERT INTO order_items (order_id, service_id, service_name, billing_unit, quantity, unit_price, line_total)
                 VALUES (:order_id, :service_id, :service_name, :billing_unit, :quantity, :unit_price, :line_total)'
            );

            foreach ($data['items'] as $index => $item) {
                if (isset($seenServices[$item['service_id']])) {
                    throw new InvalidOrderException(['items' => 'Each service can only be added once. Change its quantity instead.']);
                }
                $seenServices[$item['service_id']] = true;
                $findService->execute(['id' => $item['service_id']]);
                $service = $findService->fetch();
                if ($service === false) {
                    throw new InvalidOrderException(['items' => 'A selected service is inactive or no longer available. Reload the form and try again.']);
                }
                if (!self::validQuantity($item['quantity'], (string) $service['billing_unit'])) {
                    throw new InvalidOrderException(['items' => 'Enter a positive quantity. Piece quantities must be whole numbers; kg can use up to 3 decimals.']);
                }
                $lineTotalStatement->execute(['price' => (string) $service['price'], 'quantity' => $item['quantity']]);
                $lineTotal = (string) $lineTotalStatement->fetchColumn();
                $insertItem->execute([
                    'order_id' => $orderId,
                    'service_id' => (int) $service['id'],
                    'service_name' => (string) $service['name'],
                    'billing_unit' => (string) $service['billing_unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => (string) $service['price'],
                    'line_total' => $lineTotal,
                ]);
            }

            $sum = $database->prepare('SELECT COALESCE(SUM(line_total), 0.00) FROM order_items WHERE order_id = :id');
            $sum->execute(['id' => $orderId]);
            $total = (string) $sum->fetchColumn();
            $updateTotal = $database->prepare('UPDATE orders SET subtotal = :subtotal, total_amount = :total WHERE id = :id');
            $updateTotal->execute(['subtotal' => $total, 'total' => $total, 'id' => $orderId]);

            if ((float) $data['deposit'] > (float) $total) {
                throw new InvalidOrderException(['deposit' => 'Deposit cannot be greater than the order total.']);
            }
            if ((float) $data['deposit'] > 0) {
                $payment = $database->prepare('INSERT INTO order_payments (order_id, received_by, amount, method) VALUES (:order_id, :received_by, :amount, :method)');
                $payment->execute(['order_id' => $orderId, 'received_by' => $userId, 'amount' => $data['deposit'], 'method' => $data['payment_method']]);
                AuditLog::record($database, $userId, 'payment.recorded', 'payment', (int) $database->lastInsertId(), 'Recorded an initial payment for order ' . $code, ['order_id' => $orderId, 'amount' => (float) $data['deposit'], 'method' => $data['payment_method'], 'initial_deposit' => true]);
            }

            $log = $database->prepare('INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status) VALUES (:order_id, :changed_by, NULL, \'received\')');
            $log->execute(['order_id' => $orderId, 'changed_by' => $userId]);
            AuditLog::record($database, $userId, 'order.created', 'order', $orderId, 'Created order ' . $code, ['order_code' => $code, 'customer_id' => $data['customer_id'], 'total' => (float) $total, 'deposit' => (float) $data['deposit']]);
            $database->commit();
            return $orderId;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    /** @param array{customer_id: int, received_at: string, expected_pickup_at: string, notes: string, items: list<array{service_id: int, quantity: string}>} $data */
    public static function update(PDO $database, int $orderId, int $userId, array $data): void
    {
        $database->beginTransaction();
        try {
            $orderStatement = $database->prepare('SELECT order_code, customer_id, received_at, expected_pickup_at, notes, status, subtotal, discount_amount, total_amount FROM orders WHERE id = :id FOR UPDATE');
            $orderStatement->execute(['id' => $orderId]);
            $order = $orderStatement->fetch();
            if ($order === false) throw new InvalidOrderException(['form' => 'Order not found.']);
            if (in_array($order['status'], ['collected', 'cancelled'], true)) {
                throw new InvalidOrderException(['form' => 'Collected or cancelled orders cannot be edited.']);
            }
            if ($data['items'] === [] || count($data['items']) > 3) {
                throw new InvalidOrderException(['items' => 'Add between 1 and 3 service items.']);
            }
            $customer = $database->prepare('SELECT id FROM customers WHERE id = :id AND is_active = 1 FOR UPDATE');
            $customer->execute(['id' => $data['customer_id']]);
            if ($customer->fetchColumn() === false) throw new InvalidOrderException(['customer_id' => 'Choose an active customer.']);

            $oldItemsStatement = $database->prepare('SELECT service_id, service_name, billing_unit, quantity, unit_price, line_total FROM order_items WHERE order_id = :id');
            $oldItemsStatement->execute(['id' => $orderId]);
            $oldItems = [];
            foreach ($oldItemsStatement->fetchAll() as $oldItem) $oldItems[(int) $oldItem['service_id']] = $oldItem;

            $seenServices = [];
            $lineTotalStatement = $database->prepare('SELECT ROUND(CAST(:price AS DECIMAL(12,2)) * CAST(:quantity AS DECIMAL(10,3)), 2)');
            $validatedItems = [];
            $subtotal = 0.0;
            foreach ($data['items'] as $item) {
                $serviceId = $item['service_id'];
                if (isset($seenServices[$serviceId])) throw new InvalidOrderException(['items' => 'Each service can only be added once. Change its quantity instead.']);
                $seenServices[$serviceId] = true;
                if (isset($oldItems[$serviceId])) {
                    $service = $oldItems[$serviceId];
                } else {
                    $serviceStatement = $database->prepare('SELECT id, name AS service_name, billing_unit, price AS unit_price FROM services WHERE id = :id AND is_active = 1 FOR UPDATE');
                    $serviceStatement->execute(['id' => $serviceId]);
                    $service = $serviceStatement->fetch();
                    if ($service === false) throw new InvalidOrderException(['items' => 'A selected service is inactive or no longer available. Reload the form and try again.']);
                }
                if (!self::validQuantity($item['quantity'], (string) $service['billing_unit'])) {
                    throw new InvalidOrderException(['items' => 'Enter a positive quantity. Piece quantities must be whole numbers; kg can use up to 3 decimals.']);
                }
                $lineTotalStatement->execute(['price' => (string) $service['unit_price'], 'quantity' => $item['quantity']]);
                $lineTotal = (string) $lineTotalStatement->fetchColumn();
                $subtotal += (float) $lineTotal;
                $validatedItems[] = ['service_id' => $serviceId, 'service_name' => (string) $service['service_name'], 'billing_unit' => (string) $service['billing_unit'], 'quantity' => $item['quantity'], 'unit_price' => (string) $service['unit_price'], 'line_total' => $lineTotal];
            }
            $discount = (float) $order['discount_amount'];
            $total = round($subtotal - $discount, 2);
            $paidStatement = $database->prepare('SELECT COALESCE(SUM(amount), 0) FROM order_payments WHERE order_id = :id AND voided_at IS NULL');
            $paidStatement->execute(['id' => $orderId]);
            if ($total + 0.0001 < (float) $paidStatement->fetchColumn()) {
                throw new InvalidOrderException(['items' => 'The new total cannot be less than payments already received.']);
            }
            $database->prepare('DELETE FROM order_items WHERE order_id = :id')->execute(['id' => $orderId]);
            $insertItem = $database->prepare('INSERT INTO order_items (order_id, service_id, service_name, billing_unit, quantity, unit_price, line_total) VALUES (:order_id, :service_id, :service_name, :billing_unit, :quantity, :unit_price, :line_total)');
            foreach ($validatedItems as $item) $insertItem->execute(['order_id' => $orderId] + $item);
            $updateOrder = $database->prepare('UPDATE orders SET customer_id = :customer_id, received_at = :received_at, expected_pickup_at = :expected_pickup_at, notes = :notes, subtotal = :subtotal, total_amount = :total WHERE id = :id');
            $updateOrder->execute(['customer_id' => $data['customer_id'], 'received_at' => $data['received_at'] . ' 00:00:00', 'expected_pickup_at' => $data['expected_pickup_at'] . ' 00:00:00', 'notes' => $data['notes'] === '' ? null : $data['notes'], 'subtotal' => number_format($subtotal, 2, '.', ''), 'total' => number_format($total, 2, '.', ''), 'id' => $orderId]);
            AuditLog::record($database, $userId, 'order.updated', 'order', $orderId, 'Updated order ' . $order['order_code'], [
                'before' => ['customer_id' => (int) $order['customer_id'], 'received_at' => $order['received_at'], 'pickup_at' => $order['expected_pickup_at'], 'subtotal' => (float) $order['subtotal'], 'total' => (float) $order['total_amount'], 'items' => array_values(array_map(static fn (array $item): array => ['service_id' => (int) $item['service_id'], 'name' => $item['service_name'], 'quantity' => (float) $item['quantity'], 'unit_price' => (float) $item['unit_price'], 'line_total' => (float) $item['line_total']], $oldItems))],
                'after' => ['customer_id' => $data['customer_id'], 'received_at' => $data['received_at'], 'pickup_at' => $data['expected_pickup_at'], 'subtotal' => round($subtotal, 2), 'total' => $total, 'items' => array_map(static fn (array $item): array => ['service_id' => $item['service_id'], 'name' => $item['service_name'], 'quantity' => (float) $item['quantity'], 'unit_price' => (float) $item['unit_price'], 'line_total' => (float) $item['line_total']], $validatedItems)],
                'notes_changed' => (string) ($order['notes'] ?? '') !== $data['notes'],
            ]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    public static function addPayment(PDO $database, int $orderId, int $userId, string $amount, string $method): void
    {
        $database->beginTransaction();
        try {
            $order = $database->prepare('SELECT order_code, total_amount, status FROM orders WHERE id = :id FOR UPDATE');
            $order->execute(['id' => $orderId]);
            $record = $order->fetch();
            if ($record === false) {
                $database->rollBack();
                throw new InvalidOrderException(['payment' => 'Order not found.']);
            }
            if ($record['status'] === 'cancelled') {
                throw new InvalidOrderException(['payment' => 'Payments cannot be added to a cancelled order.']);
            }
            $paid = $database->prepare('SELECT COALESCE(SUM(amount), 0) FROM order_payments WHERE order_id = :id AND voided_at IS NULL');
            $paid->execute(['id' => $orderId]);
            $balance = max(0, round((float) $record['total_amount'] - (float) $paid->fetchColumn(), 2));
            if ((float) $amount > $balance) {
                throw new InvalidOrderException(['payment' => 'Payment cannot be greater than the remaining balance.']);
            }
            if ((float) $amount <= 0) {
                throw new InvalidOrderException(['payment' => 'Enter a payment greater than 0.']);
            }
            if (!in_array($method, ['cash', 'khqr'], true)) {
                throw new InvalidOrderException(['payment' => 'Choose a valid payment method.']);
            }
            $insert = $database->prepare('INSERT INTO order_payments (order_id, received_by, amount, method) VALUES (:order_id, :received_by, :amount, :method)');
            $insert->execute(['order_id' => $orderId, 'received_by' => $userId, 'amount' => $amount, 'method' => $method]);
            $paymentId = (int) $database->lastInsertId();
            AuditLog::record($database, $userId, 'payment.recorded', 'payment', $paymentId, 'Recorded a payment for order ' . $record['order_code'], ['order_id' => $orderId, 'amount' => (float) $amount, 'method' => $method]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    public static function correctPayment(PDO $database, int $orderId, int $paymentId, int $userId, string $reason, string $replacementAmount = '', string $replacementMethod = ''): void
    {
        $database->beginTransaction();
        try {
            $orderStatement = $database->prepare('SELECT order_code, total_amount, status FROM orders WHERE id = :id FOR UPDATE');
            $orderStatement->execute(['id' => $orderId]);
            $order = $orderStatement->fetch();
            if ($order === false) throw new InvalidOrderException(['payment' => 'Order not found.']);
            if (in_array($order['status'], ['collected', 'cancelled'], true)) throw new InvalidOrderException(['payment' => 'Payments on collected or cancelled orders cannot be corrected.']);

            $paymentStatement = $database->prepare('SELECT id, amount, method, voided_at FROM order_payments WHERE id = :payment_id AND order_id = :order_id FOR UPDATE');
            $paymentStatement->execute(['payment_id' => $paymentId, 'order_id' => $orderId]);
            $payment = $paymentStatement->fetch();
            if ($payment === false) throw new InvalidOrderException(['payment' => 'Payment not found.']);
            if ($payment['voided_at'] !== null) throw new InvalidOrderException(['payment' => 'This payment has already been corrected.']);
            if (trim($reason) === '' || mb_strlen($reason) > 500) throw new InvalidOrderException(['reason' => 'Enter a correction reason (up to 500 characters).']);

            $paidStatement = $database->prepare('SELECT COALESCE(SUM(amount), 0) FROM order_payments WHERE order_id = :id AND voided_at IS NULL AND id <> :payment_id');
            $paidStatement->execute(['id' => $orderId, 'payment_id' => $paymentId]);
            $paidWithoutOriginal = (float) $paidStatement->fetchColumn();
            $replacementId = null;
            if ($replacementAmount !== '') {
                if (!in_array($replacementMethod, ['cash', 'khqr'], true)) throw new InvalidOrderException(['payment' => 'Choose a valid replacement payment method.']);
                $remaining = max(0, round((float) $order['total_amount'] - $paidWithoutOriginal, 2));
                if ((float) $replacementAmount <= 0 || (float) $replacementAmount > $remaining) {
                    throw new InvalidOrderException(['payment' => 'Replacement payment must be greater than 0 and no more than the remaining balance.']);
                }
                $replacement = $database->prepare('INSERT INTO order_payments (order_id, received_by, amount, method) VALUES (:order_id, :received_by, :amount, :method)');
                $replacement->execute(['order_id' => $orderId, 'received_by' => $userId, 'amount' => $replacementAmount, 'method' => $replacementMethod]);
                $replacementId = (int) $database->lastInsertId();
            }
            $void = $database->prepare('UPDATE order_payments SET voided_at = CURRENT_TIMESTAMP, voided_by = :voided_by, void_reason = :reason, replacement_payment_id = :replacement_id WHERE id = :id AND voided_at IS NULL');
            $void->execute(['voided_by' => $userId, 'reason' => trim($reason), 'replacement_id' => $replacementId, 'id' => $paymentId]);
            if ($void->rowCount() !== 1) throw new InvalidOrderException(['payment' => 'This payment was already corrected. Reload the order and try again.']);
            AuditLog::record($database, $userId, 'payment.corrected', 'payment', $paymentId, 'Corrected a payment for order ' . $order['order_code'], ['order_id' => $orderId, 'original_amount' => (float) $payment['amount'], 'original_method' => $payment['method'], 'reason' => trim($reason), 'replacement_payment_id' => $replacementId, 'replacement_amount' => $replacementAmount === '' ? null : (float) $replacementAmount, 'replacement_method' => $replacementAmount === '' ? null : $replacementMethod]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    public static function changeStatus(PDO $database, int $orderId, int $userId, string $newStatus, string $reason = ''): bool
    {
        $database->beginTransaction();
        try {
            $statement = $database->prepare('SELECT order_code, status, total_amount FROM orders WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $orderId]);
            $record = $statement->fetch();
            if ($record === false) { $database->rollBack(); return false; }
            $oldStatus = (string) $record['status'];
            if (in_array($oldStatus, ['collected', 'cancelled'], true) || !in_array($newStatus, ['received', 'processing', 'ready', 'collected', 'cancelled'], true)) {
                $database->rollBack();
                throw new InvalidOrderException(['status' => 'A collected or cancelled order cannot be changed.']);
            }
            if ($newStatus === 'cancelled' && (trim($reason) === '' || mb_strlen($reason) > 500)) {
                throw new InvalidOrderException(['status' => 'Enter a cancellation reason (up to 500 characters).']);
            }
            if ($newStatus === $oldStatus) {
                $database->commit();
                return true;
            }
            if ($newStatus === 'collected') {
                $paid = $database->prepare('SELECT COALESCE(SUM(amount), 0) FROM order_payments WHERE order_id = :id AND voided_at IS NULL');
                $paid->execute(['id' => $orderId]);
                if ((float) $paid->fetchColumn() + 0.0001 < (float) $record['total_amount']) {
                    throw new InvalidOrderException(['status' => 'Record the full payment before marking this order collected.']);
                }
            }
            $update = $database->prepare('UPDATE orders SET status = :status, picked_up_at = CASE WHEN :picked_status = \'collected\' THEN CURRENT_TIMESTAMP ELSE picked_up_at END WHERE id = :id');
            $update->execute(['status' => $newStatus, 'picked_status' => $newStatus, 'id' => $orderId]);
            $log = $database->prepare('INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status, notes) VALUES (:order_id, :changed_by, :old_status, :new_status, :notes)');
            $log->execute(['order_id' => $orderId, 'changed_by' => $userId, 'old_status' => $oldStatus, 'new_status' => $newStatus, 'notes' => $newStatus === 'cancelled' ? trim($reason) : null]);
            $details = ['old_status' => $oldStatus, 'new_status' => $newStatus];
            if ($newStatus === 'cancelled') $details['reason'] = trim($reason);
            AuditLog::record($database, $userId, $newStatus === 'cancelled' ? 'order.cancelled' : 'order.status_changed', 'order', $orderId, $newStatus === 'cancelled' ? 'Cancelled order ' . $record['order_code'] : 'Changed order ' . $record['order_code'] . ' status to ' . $newStatus, $details);
            $database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    private static function validQuantity(string $quantity, string $unit): bool
    {
        if ($unit === 'piece') return preg_match('/^[1-9][0-9]{0,6}$/', $quantity) === 1;
        if (preg_match('/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/', $quantity) !== 1) return false;
        return preg_match('/[1-9]/', $quantity) === 1;
    }
}
