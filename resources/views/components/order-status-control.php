<?php
$statusValue = (string) ($order['status'] ?? 'received');
$statusIcons = [
    'received' => '<path d="M12 3 4.5 7.5 12 12l7.5-4.5L12 3Z"/><path d="M4.5 7.5v9L12 21l7.5-4.5v-9M12 12v9"/>',
    'processing' => '<circle cx="12" cy="12" r="8.5" stroke-dasharray="40 16"/>',
    'ready' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.3 2.3 4.8-4.8"/>',
    'collected' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m8.5 12.5 2.2 2.2 4.8-4.8"/>',
    'cancelled' => '<circle cx="12" cy="12" r="8.5"/><path d="m9 9 6 6m0-6-6 6"/>',
];
$allowedStatuses = array_merge([$statusValue], match ($statusValue) {
    'received' => ['processing', 'cancelled'],
    'processing' => ['ready', 'cancelled'],
    'ready' => ['collected', 'cancelled'],
    default => [],
});
?>
<form method="post" action="/orders/<?= (int) $order['id'] ?>/status" data-order-status-form class="w-fit">
    <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="_return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/orders') ?>">
    <label class="sr-only" for="status-<?= (int) $order['id'] ?>"><?= e(t('Status for {order}', ['order' => $order['order_code']])) ?></label>
    <span class="order-status-control" data-order-status-control data-status="<?= e($statusValue) ?>">
        <svg data-order-status-icon class="order-status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $statusIcons[$statusValue] ?? $statusIcons['received'] ?></svg>
        <select id="status-<?= (int) $order['id'] ?>" name="status" data-order-status data-current-status="<?= e($statusValue) ?>" class="order-status-select" <?= in_array($statusValue, ['collected', 'cancelled'], true) ? 'disabled' : '' ?>>
            <?php foreach ($allowedStatuses as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= $statusValue === $statusOption ? 'selected' : '' ?>><?= e(t(ucfirst($statusOption))) ?></option><?php endforeach; ?>
        </select>
        <svg class="order-status-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
    </span>
</form>
