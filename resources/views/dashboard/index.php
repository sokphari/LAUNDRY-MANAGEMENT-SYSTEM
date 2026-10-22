<?php
$toneStyles = [
    'violet' => ['icon' => 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300', 'dot' => 'bg-violet-500'],
    'emerald' => ['icon' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
    'blue' => ['icon' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300', 'dot' => 'bg-blue-500'],
    'amber' => ['icon' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', 'dot' => 'bg-amber-500'],
];
$statusTotal = array_sum(array_column($orderStatuses, 'value'));
$isStaffDashboard = (bool) ($isStaffDashboard ?? false);
$circumference = 2 * pi() * 36;
$statusOffset = 0.0;
$chartPoints = [];
$chartMin = min(0, ...array_column($yearlyRevenue, 'amount'));
$chartMax = max(0, ...array_column($yearlyRevenue, 'amount'));
if ($chartMax === $chartMin) {
    $chartMax = $chartMax === 0.0 ? 1000.0 : $chartMin + 1;
}
$chartRange = $chartMax - $chartMin;
$zeroY = 210 - ((0 - $chartMin) / $chartRange * 165);
$chartTicks = [];
foreach ([0, 1, 2, 3] as $tick) {
    $value = $chartMax - ($chartRange * $tick / 3);
    $chartTicks[] = ['y' => 45 + ($tick * 55), 'value' => $value];
}
$formatChartValue = static function (float $value): string {
    $absolute = abs($value);
    if ($absolute >= 1000000) return number_format($value / 1000000, 1) . 'M';
    if ($absolute >= 1000) return number_format($value / 1000, 0) . 'K';
    return number_format($value, 0);
};
foreach ($yearlyRevenue as $index => $entry) {
    $chartPoints[] = [
        'x' => 66 + ($index * 46),
        'y' => 210 - (((float) $entry['amount'] - $chartMin) / $chartRange * 165),
        'month' => $entry['month'],
        'amount' => $entry['amount'],
    ];
}
$linePath = implode(' ', array_map(
    static fn (array $point): string => number_format($point['x'], 1, '.', '') . ',' . number_format($point['y'], 1, '.', ''),
    $chartPoints
));
$areaPath = 'M ' . number_format($chartPoints[0]['x'], 1, '.', '') . ' ' . number_format($zeroY, 1, '.', '') . ' L ' . str_replace(',', ' ', $linePath)
    . ' L ' . number_format($chartPoints[array_key_last($chartPoints)]['x'], 1, '.', '') . ' ' . number_format($zeroY, 1, '.', '') . ' Z';
?>
<section class="min-w-0 space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-primary"><?= e(t('Overview')) ?></p>
            <?php if ($isStaffDashboard): ?>
                <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl"><?= e(t('My Work Overview')) ?> · <span id="dashboard-staff-period"><?= e(t($rangeLabel)) ?></span></h2>
            <?php else: ?>
                <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl"><?= e(t('Welcome, {name}', ['name' => $user['full_name'] ?? ''])) ?></h2>
            <?php endif; ?>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300"><?= $isStaffDashboard ? e(t('Your dashboard shows your own work and activity. Use Orders and Payments to help with any shop order.')) : e(t('Store overview and staff activity for the selected date range.')) ?></p>
        </div>
        <?php $dashboardNow = new DateTimeImmutable('now'); ?>
        <aside class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800" aria-label="Current Cambodia date and time">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-100 text-primary dark:bg-violet-950 dark:text-violet-300" aria-hidden="true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
            </span>
            <span class="min-w-0">
                <!-- <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400">Cambodia time</span> -->
                <time id="dashboard-live-clock" data-timezone="<?= e(date_default_timezone_get()) ?>" datetime="<?= e($dashboardNow->format(DATE_ATOM)) ?>" class="block font-semibold tabular-nums"><?= e($dashboardNow->format('M j, Y · H:i:s')) ?></time>
            </span>
        </aside>
    </div>

    <form id="dashboard-filter-form" method="get" action="/dashboard" data-current-year="<?= e($years[0]) ?>" class="grid w-full gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:grid-cols-2 <?= $isStaffDashboard ? 'xl:grid-cols-4' : 'xl:grid-cols-3' ?> dark:bg-dark-card dark:ring-zinc-800">
            <div>
                <label for="dashboard-range" class="mb-2 block text-sm font-medium">Date range</label>
                <select id="dashboard-range" name="range" data-dashboard-range class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="today" <?= $selectedRange === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $selectedRange === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="week" <?= $selectedRange === 'week' ? 'selected' : '' ?>>This week</option>
                    <option value="month" <?= $selectedRange === 'month' ? 'selected' : '' ?>>This month</option>
                    <option value="custom" <?= $selectedRange === 'custom' ? 'selected' : '' ?>>Custom range</option>
                    <option value="all" <?= $selectedRange === 'all' ? 'selected' : '' ?>>All time</option>
                </select>
                <div data-dashboard-custom-range class="mt-3 grid grid-cols-1 gap-2 min-[400px]:grid-cols-2 <?= $selectedRange === 'custom' ? '' : 'hidden' ?>">
                    <div>
                        <label for="dashboard-from" class="mb-2 block text-sm font-medium">From</label>
                        <input id="dashboard-from" name="from" type="date" value="<?= e($fromDate) ?>" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                    <div>
                        <label for="dashboard-to" class="mb-2 block text-sm font-medium">To</label>
                        <input id="dashboard-to" name="to" type="date" value="<?= e($toDate) ?>" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                </div>
            </div>
            <?php if (!$isStaffDashboard): ?><div>
                <label for="revenue-year" class="mb-2 block text-sm font-medium">Chart year</label>
                <select id="revenue-year" name="year" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e($year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>><?= e($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div><?php endif; ?>
            <div class="flex items-end">
                <button type="button" data-dashboard-reset class="h-11 w-full rounded-lg border border-zinc-300 px-4 text-sm font-semibold hover:border-primary hover:text-primary dark:border-zinc-700">Reset</button>
            </div>
            <?php if ($isStaffDashboard): ?>
                <div class="flex items-end"><a href="/orders/create" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-center text-sm font-semibold text-white transition hover:bg-violet-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <span class="text-lg leading-none" aria-hidden="true">+</span><span><?= e(t('New Order')) ?></span>
                </a></div>
                <div class="flex items-end"><a href="/orders" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-violet-100 px-4 text-center text-sm font-semibold text-violet-900 transition hover:bg-violet-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:bg-violet-950 dark:text-violet-200 dark:hover:bg-violet-900">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path stroke-linecap="round" d="m16 16 4.5 4.5"/></svg><span><?= e(t('Find Order')) ?></span>
                </a></div>
            <?php endif; ?>
        </form>
    <p id="dashboard-filter-status" class="sr-only" role="status" aria-live="polite"></p>

    <div id="dashboard-stats" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($stats as $index => $stat): ?>
            <?php $tone = $toneStyles[$stat['tone']] ?? $toneStyles['violet']; ?>
            <article data-dashboard-stat="<?= e($index) ?>" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p data-dashboard-stat-label class="text-sm font-medium text-zinc-500 dark:text-zinc-400"><?= e(t($stat['label'])) ?></p>
                        <p data-dashboard-stat-value class="mt-3 text-2xl font-bold tracking-tight"><?= e($stat['value']) ?></p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl <?= e($tone['icon']) ?>" aria-hidden="true">
                        <?php if ($isStaffDashboard && $index === 0): ?><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4.75H6.75A1.75 1.75 0 0 0 5 6.5v13A1.75 1.75 0 0 0 6.75 21h10.5A1.75 1.75 0 0 0 19 19.5v-13a1.75 1.75 0 0 0-1.75-1.75H15M9 4.75a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M12 10v6m-3-3h6"/></svg>
                        <?php elseif ($isStaffDashboard && $index === 1): ?><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16m-4 5h2m-12-9V3m12 2V3"/></svg>
                        <?php elseif ($isStaffDashboard && $index === 2): ?><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5m10.2-2A7 7 0 0 0 7.1 6L5 8m14 8-2.1 2a7 7 0 0 1-12.1-4"/></svg>
                        <?php elseif ($isStaffDashboard): ?><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm0 9 8-4.5M12 12v9m0-9L4 7.5m12 6 2 2 4-4"/></svg>
                        <?php elseif ($index === 0): ?>♙<?php elseif ($index === 1): ?>៛<?php elseif ($index === 2): ?>▤<?php else: ?>◷<?php endif; ?>
                    </span>
                </div>
                <p class="mt-3 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"><span class="size-1.5 shrink-0 rounded-full <?= e($tone['dot']) ?>"></span><span data-dashboard-stat-note><?= e(t($stat['note'])) ?></span></p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$isStaffDashboard): ?>
    <div class="grid min-w-0 grid-cols-1 items-stretch gap-5 xl:grid-cols-5">
        <article class="min-w-0 rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 xl:col-span-3 dark:bg-dark-card dark:ring-zinc-800">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold"><?= $isStaffDashboard ? e(t('My net payments by month in')) : e(t('Net payments by month in')) ?> <?= e($selectedYear) ?></h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Payments minus refunds · KHR</p>
                </div>
            </div>
            <div class="mt-3">
                <p class="mb-1 text-xs text-zinc-500 dark:text-zinc-400 sm:hidden">Swipe chart horizontally to view all months.</p>
                <div class="w-full min-w-0 max-w-full overflow-x-auto overscroll-x-contain focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" role="region" aria-label="Monthly revenue line chart" tabindex="0">
                <svg id="dashboard-revenue-chart" class="h-[250px] w-[640px] max-w-none" viewBox="0 0 640 270" preserveAspectRatio="none" role="img" aria-label="Line chart showing monthly net payments for <?= e($selectedYear) ?>">
                    <defs>
                        <linearGradient id="revenue-fill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.22" />
                            <stop offset="100%" stop-color="#7C3AED" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <g stroke="currentColor" class="text-zinc-100 dark:text-zinc-800" stroke-width="1">
                        <?php foreach ($chartTicks as $tick): ?><line x1="58" y1="<?= e($tick['y']) ?>" x2="620" y2="<?= e($tick['y']) ?>" /><?php endforeach; ?>
                        <line x1="58" y1="<?= e(number_format($zeroY, 1, '.', '')) ?>" x2="620" y2="<?= e(number_format($zeroY, 1, '.', '')) ?>" stroke-dasharray="3 3" />
                    </g>
                    <g fill="currentColor" class="text-zinc-400" font-size="11" text-anchor="end">
                        <?php foreach ($chartTicks as $tick): ?><text x="50" y="<?= e($tick['y'] + 4) ?>"><?= e($formatChartValue($tick['value'])) ?></text><?php endforeach; ?>
                    </g>
                    <path d="<?= e($areaPath) ?>" fill="url(#revenue-fill)" />
                    <polyline points="<?= e($linePath) ?>" fill="none" stroke="#7C3AED" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <?php foreach ($chartPoints as $point): ?>
                        <circle cx="<?= e(number_format($point['x'], 1, '.', '')) ?>" cy="<?= e(number_format($point['y'], 1, '.', '')) ?>" r="4" fill="#fff" stroke="#7C3AED" stroke-width="3">
                            <title><?= e($point['month'] . ' ' . $selectedYear . ': ' . number_format($point['amount']) . ' KHR net payments') ?></title>
                        </circle>
                        <text x="<?= e(number_format($point['x'], 1, '.', '')) ?>" y="238" fill="currentColor" class="text-zinc-500" font-size="11" text-anchor="middle"><?= e($point['month']) ?></text>
                    <?php endforeach; ?>
                </svg>
                </div>
            </div>
        </article>

        <article class="min-w-0 rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 xl:col-span-2 dark:bg-dark-card dark:ring-zinc-800">
            <div>
                <h3 class="text-lg font-semibold">Order status</h3>
                <p id="dashboard-status-range" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"><?= $isStaffDashboard ? e(t('My orders received')) : e(t('Orders received')) ?>: <?= e($rangeLabel) ?></p>
            </div>
            <p class="mb-1 mt-3 text-xs text-zinc-500 dark:text-zinc-400 sm:hidden">Swipe chart horizontally to view chart and legend.</p>
            <div class="w-full min-w-0 max-w-full overflow-x-auto overscroll-x-contain focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" role="region" aria-label="Order status chart and legend" tabindex="0">
            <div class="mt-2 flex min-w-[360px] flex-row items-center justify-center gap-4 sm:justify-start">
                <div class="relative size-32 shrink-0 sm:size-40">
                <svg id="dashboard-status-chart" class="size-full -rotate-90" viewBox="0 0 140 140" role="img" aria-label="Pie chart showing orders by status">
                        <circle cx="70" cy="70" r="36" fill="none" stroke="currentColor" class="text-zinc-100 dark:text-zinc-800" stroke-width="14" />
                        <?php foreach ($orderStatuses as $status): ?>
                            <?php $segmentLength = $statusTotal > 0 ? ($status['value'] / $statusTotal) * $circumference : 0; ?>
                            <circle cx="70" cy="70" r="36" fill="none" stroke="<?= e($status['color']) ?>" stroke-width="14"
                                    stroke-dasharray="<?= e(number_format($segmentLength, 2, '.', '')) ?> <?= e(number_format($circumference - $segmentLength, 2, '.', '')) ?>"
                                    stroke-dashoffset="-<?= e(number_format($statusOffset, 2, '.', '')) ?>" />
                            <?php $statusOffset += $segmentLength; ?>
                        <?php endforeach; ?>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span id="dashboard-status-total" class="text-3xl font-bold"><?= e($statusTotal) ?></span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">orders</span>
                    </div>
                </div>
                <ul id="dashboard-status-legend" class="w-44 shrink-0 space-y-2">
                    <?php foreach ($orderStatuses as $status): ?>
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><svg class="size-2.5" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?= e($status['color']) ?>" /></svg><?= e($status['label']) ?></span>
                            <span class="font-semibold"><?= e($status['value']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            </div>
        </article>
    </div>
    <?php endif; ?>

    <?php if ($isStaffDashboard): ?>
        <div class="grid gap-5 xl:grid-cols-2">
            <article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800"><h3 class="font-semibold"><?= e(t('My recent activity')) ?></h3><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Actions you performed in the selected period.')) ?></p></div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800" id="dashboard-personal-activities">
                    <?php foreach ($recentActivities as $activity): ?><div class="flex items-start justify-between gap-4 px-5 py-3"><div class="min-w-0"><p class="font-medium"><?= e($activity['summary']) ?></p><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><?= e(t(ucwords(str_replace(['.', '_'], ' ', (string) $activity['action'])))) ?></p></div><time class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400"><?= e($activity['created_display']) ?></time></div><?php endforeach; ?>
                    <?php if ($recentActivities === []): ?><p data-empty-state class="px-5 py-8 text-center text-sm text-zinc-500"><?= e(t('No activity in this date range.')) ?></p><?php endif; ?>
                </div>
            </article>
            <article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800"><h3 class="font-semibold"><?= e(t('My payments')) ?></h3><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Payments you recorded in the selected period.')) ?></p></div>
                <div class="overflow-x-auto"><table data-responsive-cards class="w-full min-w-[520px] text-left text-sm"><thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400"><tr><th class="px-5 py-3"><?= e(t('Order')) ?></th><th class="px-5 py-3"><?= e(t('Customer')) ?></th><th class="px-5 py-3"><?= e(t('Method')) ?></th><th class="px-5 py-3 text-right"><?= e(t('Amount')) ?></th></tr></thead><tbody id="dashboard-personal-payments" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php foreach ($personalPayments as $payment): ?><tr><td class="px-5 py-3"><a class="font-semibold text-primary hover:underline" href="/orders/<?= (int) $payment['order_id'] ?>"><?= e($payment['order_code']) ?></a></td><td class="px-5 py-3"><?= e($payment['customer_name']) ?></td><td class="px-5 py-3"><?= e(strtoupper((string) $payment['method'])) ?></td><td class="px-5 py-3 text-right font-semibold"><?= e(format_khr((float) $payment['amount'])) ?> KHR</td></tr><?php endforeach; ?>
                    <?php if ($personalPayments === []): ?><tr data-empty-state><td colspan="4" class="px-5 py-8 text-center text-zinc-500"><?= e(t('No payments recorded in this date range.')) ?></td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
        </div>
        <article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
            <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800"><h3 class="font-semibold"><?= e(t('My open orders')) ?></h3><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Open orders you created. You can still work on any order from the Orders module.')) ?></p></div>
            <div class="overflow-x-auto"><table data-responsive-cards class="w-full min-w-[640px] text-left text-sm"><thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400"><tr><th class="px-5 py-3"><?= e(t('Order')) ?></th><th class="px-5 py-3"><?= e(t('Customer')) ?></th><th class="px-5 py-3"><?= e(t('Status')) ?></th><th class="px-5 py-3"><?= e(t('Pickup date')) ?></th></tr></thead><tbody id="dashboard-personal-work" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                <?php foreach ($personalWork as $workOrder): ?><tr><td class="px-5 py-3"><a class="font-semibold text-primary hover:underline" href="/orders/<?= (int) $workOrder['id'] ?>"><?= e($workOrder['order_code']) ?></a></td><td class="px-5 py-3"><?= e($workOrder['customer_name']) ?></td><td class="px-5 py-3"><?= e(t(ucfirst((string) $workOrder['status']))) ?></td><td class="px-5 py-3"><?= e(date('d M Y', strtotime((string) $workOrder['expected_pickup_at']))) ?></td></tr><?php endforeach; ?>
                <?php if ($personalWork === []): ?><tr data-empty-state><td colspan="4" class="px-5 py-8 text-center text-zinc-500"><?= e(t('No open orders created by you.')) ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </article>
    <?php else: ?>
        <article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
            <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800"><h3 class="font-semibold"><?= e(t('Staff activity')) ?></h3><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Orders, payments, and latest recorded action by staff for the selected period.')) ?></p></div>
            <div class="overflow-x-auto"><table data-responsive-cards class="w-full min-w-[760px] text-left text-sm"><thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400"><tr><th class="px-5 py-3"><?= e(t('Staff member')) ?></th><th class="px-5 py-3"><?= e(t('Orders created')) ?></th><th class="px-5 py-3"><?= e(t('Net payments recorded')) ?></th><th class="px-5 py-3"><?= e(t('Latest activity')) ?></th><th class="px-5 py-3"><?= e(t('Date & time')) ?></th><th class="px-5 py-3 text-right"><?= e(t('Details')) ?></th></tr></thead><tbody id="dashboard-staff-activity" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                <?php foreach ($staffActivities as $activity): ?><tr><td class="px-5 py-3"><span class="block font-semibold"><?= e($activity['full_name']) ?></span><span class="text-xs text-zinc-500">@<?= e($activity['username']) ?></span></td><td class="px-5 py-3"><?= e($activity['orders']) ?></td><td class="px-5 py-3 font-semibold"><?= e(format_khr((float) $activity['payments'])) ?> KHR</td><td class="max-w-md px-5 py-3"><span class="block truncate" title="<?= e($activity['last_activity']) ?>"><?= e($activity['last_activity']) ?></span></td><td class="whitespace-nowrap px-5 py-3 text-zinc-500"><?= e($activity['last_activity_at']) ?></td><td class="px-5 py-3 text-right"><a class="font-semibold text-primary hover:underline" href="/audit-logs?user=<?= (int) $activity['user_id'] ?>"><?= e(t('View activity')) ?></a></td></tr><?php endforeach; ?>
                <?php if ($staffActivities === []): ?><tr data-empty-state><td colspan="6" class="px-5 py-8 text-center text-zinc-500"><?= e(t('No staff accounts found.')) ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </article>
    <?php endif; ?>
</section>
