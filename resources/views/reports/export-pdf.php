<?php
$methodTotals = ['received' => 0.0, 'refunds' => 0.0, 'net' => 0.0];
foreach ($report['methods'] as $method) {
    $methodTotals['received'] += (float)$method['received'];
    $methodTotals['refunds'] += (float)$method['refunds'];
    $methodTotals['net'] += (float)$method['net'];
}
$statusTotal = array_sum(array_column($report['statuses'], 'count'));
$maxStatus = max(1, ...array_column($report['statuses'], 'count'));
$statusPairs = [['received', 'processing'], ['ready', 'collected'], ['cancelled', null]];
$statusMap = [];
foreach ($report['statuses'] as $status) $statusMap[strtolower($status['label'])] = $status;
?>
<main class="business-report">
    <header class="business-report-header">
        <div class="brand-lockup"><img src="/assets/images/laundry-manager-logo.png" alt="Laundry Manager" class="report-brand-logo"></div>
        <div class="report-meta"><p>Generated: <?= e($exportedAt) ?></p><p>Prepared by: <?= e($preparedBy) ?></p><p>Currency: KHR</p></div>
    </header>
    <div class="brand-rule"></div>
    <div class="report-title-row"><div><h2>Business Performance Report</h2><p>Period: <?= e($periodLabel) ?></p></div><span class="period-badge"><?= e($periodBadge) ?></span></div>

    <section class="report-section">
        <div class="section-heading"><h3>Executive Summary</h3><span>Snapshot &amp; transaction metrics</span></div>
        <div class="metric-grid">
            <article class="metric-card"><span>Orders Created</span><strong><?= number_format((int)$report['summary']['Orders']) ?></strong></article>
            <article class="metric-card"><span>Orders Collected</span><strong><?= number_format((int)($statusMap['collected']['count'] ?? 0)) ?></strong></article>
            <article class="metric-card"><span>Payments Received</span><strong><?= e(format_khr($methodTotals['received'])) ?> KHR</strong></article>
            <article class="metric-card metric-refund"><span>Refunds Completed</span><strong><?= e(format_khr($methodTotals['refunds'])) ?> KHR</strong></article>
            <article class="metric-card metric-net"><span>Net Cash Received</span><strong><?= e(format_khr($methodTotals['net'])) ?> KHR</strong></article>
            <article class="metric-card"><span>Outstanding Balance *</span><strong><?= e(format_khr($report['snapshotOutstanding'])) ?> KHR</strong></article>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading"><h3>Orders by Status</h3><span><?= number_format($statusTotal) ?> total orders in period</span></div>
        <div class="status-grid">
            <?php foreach ($statusPairs as [$left, $right]): foreach ([$left, $right] as $statusKey): if ($statusKey === null) { echo '<div class="status-item status-empty"></div>'; continue; } $status = $statusMap[$statusKey] ?? ['label'=>ucfirst($statusKey),'count'=>0]; $width = $statusTotal > 0 ? ((int)$status['count'] / $maxStatus) * 100 : 0; ?>
                <div class="status-item"><span class="status-label"><?= e($status['label']) ?></span><span class="status-track"><span class="status-fill status-<?= e($statusKey) ?>" style="width:<?= e(number_format($width, 1, '.', '')) ?>%"></span></span><strong><?= number_format((int)$status['count']) ?></strong></div>
            <?php endforeach; endforeach; ?>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading"><h3>Payments by Method</h3><span>Completed transactions in the selected period</span></div>
        <table class="business-table"><thead><tr><th>Method</th><th>Received</th><th>Refunded</th><th>Net Received</th></tr></thead><tbody>
            <?php foreach ($report['methods'] as $method): ?><tr><td><?= e($method['label']) ?></td><td><?= e(format_khr($method['received'])) ?> KHR</td><td class="refund-amount"><?= e(format_khr($method['refunds'])) ?> KHR</td><td><?= e(format_khr($method['net'])) ?> KHR</td></tr><?php endforeach; ?>
            <tr class="table-total"><th>Total</th><td><?= e(format_khr($methodTotals['received'])) ?> KHR</td><td><?= e(format_khr($methodTotals['refunds'])) ?> KHR</td><td><?= e(format_khr($methodTotals['net'])) ?> KHR</td></tr>
        </tbody></table>
    </section>

    <section class="report-section">
        <div class="section-heading"><h3>Top Services by Order Value</h3><span>Based on non-cancelled order items; not cash received</span></div>
        <table class="business-table"><thead><tr><th>Service</th><th class="align-right">Quantity</th><th class="align-right">Order Value</th></tr></thead><tbody>
            <?php foreach ($report['services'] as $service): $quantity = rtrim(rtrim(number_format((float)$service['quantity'], 3, '.', ','), '0'), '.'); ?><tr><td><?= e($service['service_name']) ?></td><td class="align-right"><?= e($quantity) ?> <?= e($service['billing_unit']) ?></td><td class="align-right"><?= e(format_khr($service['revenue'])) ?> KHR</td></tr><?php endforeach; ?>
            <?php if ($report['services'] === []): ?><tr><td colspan="3" class="empty-row">No service records in this period.</td></tr><?php endif; ?>
        </tbody></table>
    </section>

    <aside class="report-notes"><strong>Reporting notes:</strong> Payment and refund amounts use successful transaction dates within the selected period. Order counts use the application's documented status/date rules. <strong>* Outstanding Balance</strong> is a snapshot of collectible balances on non-cancelled orders at report generation; it is not cash received and may include earlier orders. Net cash received = payments received − completed refunds, before expenses.</aside>
    <footer class="report-footer"><span>Generated by Laundry Management System</span><span>Confidential · Internal business use</span></footer>
    <button type="button" data-print-report>Print report / Save as PDF</button>
</main>
