<main class="report">
    <header class="report-header">
        <div>
            <p class="eyebrow">Laundry Manager</p>
            <h1>Payments &amp; Refunds</h1>
            <p>Exported <?= e($exportedAt) ?></p>
        </div>
        <button class="print-button" type="button" data-print-report>Print / Save as PDF</button>
    </header>
    <section class="summary" aria-label="Payment totals">
        <div><span>Payments received</span><strong><?= e(format_khr($summary['payments'])) ?> KHR</strong></div>
        <div><span>Refunds recorded</span><strong><?= e(format_khr($summary['refunds'])) ?> KHR</strong></div>
        <div><span>Pending balance</span><strong><?= e(format_khr($summary['pending'])) ?> KHR</strong></div>
        <div><span>Net received</span><strong><?= e(format_khr($summary['net'])) ?> KHR</strong></div>
    </section>
    <h2>Payment Method Summary</h2>
    <p class="reconciliation-note">These totals use the selected date range only. Search, status, transaction type, and staff filters do not change this summary.</p>
    <table class="reconciliation">
        <thead><tr><th>Payment method</th><th class="amount">Payments received</th><th class="amount">Refunds</th><th class="amount">Net received</th></tr></thead>
        <tbody>
        <?php foreach (['cash' => 'Cash', 'khqr' => 'KHQR', 'total' => 'Total'] as $methodKey => $methodLabel): ?>
            <tr><th><?= e($methodLabel) ?></th><td class="amount"><?= e(format_khr($reconciliation[$methodKey]['payments'])) ?> KHR</td><td class="amount"><?= e(format_khr($reconciliation[$methodKey]['refunds'])) ?> KHR</td><td class="amount"><?= e(format_khr($reconciliation[$methodKey]['net'])) ?> KHR</td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <table>
        <thead><tr><th>Date &amp; time</th><th>Status</th><th>Type</th><th>Order</th><th>Customer</th><th>Method</th><th class="amount">Amount (KHR)</th><th>Staff</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $transaction): ?>
            <?php $isRefund = $transaction['transaction_type'] === 'refund'; $isPending = $transaction['transaction_type'] === 'pending'; ?>
            <tr>
                <td><?= e(date('d M Y, H:i', strtotime((string) $transaction['occurred_at']))) ?></td>
                <td><span class="status status-<?= e($transaction['transaction_status']) ?>"><?= e(ucfirst((string) $transaction['transaction_status'])) ?></span></td>
                <td><?= $isPending ? 'Pending balance' : ($isRefund ? 'Refund' : 'Payment') ?></td>
                <td><?= e($transaction['order_code']) ?></td>
                <td><?= e($transaction['customer_name']) ?><small><?= e($transaction['customer_phone']) ?></small></td>
                <td><?= $isPending ? '—' : e(strtoupper((string) $transaction['method'])) ?></td>
                <td class="amount"><?= $isRefund ? '−' : '' ?><?= e(format_khr($transaction['amount'])) ?></td>
                <td><?= e($transaction['username'] ?? '—') ?></td>
                <td><?= e($transaction['reason'] ?? $transaction['void_reason'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($transactions === []): ?><tr><td colspan="9" class="empty">No payment transactions match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <footer><?= count($transactions) ?> transaction<?= count($transactions) === 1 ? '' : 's' ?></footer>
</main>
