<?php
$toneStyles = [
    'violet' => ['icon' => 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300', 'dot' => 'bg-violet-500'],
    'emerald' => ['icon' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
    'blue' => ['icon' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300', 'dot' => 'bg-blue-500'],
    'amber' => ['icon' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', 'dot' => 'bg-amber-500'],
];
$statusTotal = array_sum(array_column($orderStatuses, 'value'));
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
<section class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">Overview</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight">Welcome, <?= e($user['full_name'] ?? '') ?></h2>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300">Here is what is happening in your laundry shop.</p>
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

    <form id="dashboard-filter-form" method="get" action="/dashboard" data-current-year="<?= e($years[0]) ?>" class="grid w-full gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:grid-cols-2 xl:grid-cols-3 dark:bg-dark-card dark:ring-zinc-800">
            <div>
                <label for="dashboard-range" class="mb-2 block text-sm font-medium">Date range</label>
                <select id="dashboard-range" name="range" data-dashboard-range class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="today" <?= $selectedRange === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $selectedRange === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="week" <?= $selectedRange === 'week' ? 'selected' : '' ?>>This week</option>
                    <option value="month" <?= $selectedRange === 'month' ? 'selected' : '' ?>>This month</option>
                    <option value="custom" <?= $selectedRange === 'custom' ? 'selected' : '' ?>>Custom range</option>
                    <option value="all" <?= $selectedRange === 'all' ? 'selected' : '' ?>>All time</option>
                </select>
                <div data-dashboard-custom-range class="mt-3 grid grid-cols-2 gap-2 <?= $selectedRange === 'custom' ? '' : 'hidden' ?>">
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
            <div>
                <label for="revenue-year" class="mb-2 block text-sm font-medium">Chart year</label>
                <select id="revenue-year" name="year" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e($year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>><?= e($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" data-dashboard-reset class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold hover:border-primary hover:text-primary dark:border-zinc-700">Reset</button>
            </div>
        </form>
    <p id="dashboard-filter-status" class="sr-only" role="status" aria-live="polite"></p>

    <div id="dashboard-stats" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($stats as $index => $stat): ?>
            <?php $tone = $toneStyles[$stat['tone']] ?? $toneStyles['violet']; ?>
            <article data-dashboard-stat="<?= e($index) ?>" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p data-dashboard-stat-label class="text-sm font-medium text-zinc-500 dark:text-zinc-400"><?= e($stat['label']) ?></p>
                        <p data-dashboard-stat-value class="mt-3 text-2xl font-bold tracking-tight"><?= e($stat['value']) ?></p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl <?= e($tone['icon']) ?>" aria-hidden="true">
                        <?php if ($index === 0): ?>♙<?php elseif ($index === 1): ?>៛<?php elseif ($index === 2): ?>▤<?php else: ?>◷<?php endif; ?>
                    </span>
                </div>
                <p class="mt-3 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"><span class="size-1.5 shrink-0 rounded-full <?= e($tone['dot']) ?>"></span><span data-dashboard-stat-note><?= e($stat['note']) ?></span></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-5 xl:grid-cols-5 xl:items-start">
        <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 xl:col-span-3 dark:bg-dark-card dark:ring-zinc-800">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold">Net payments by month in <?= e($selectedYear) ?></h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Payments minus refunds · KHR</p>
                </div>
            </div>
            <div class="mt-3">
                <p class="mb-1 text-xs text-zinc-500 dark:text-zinc-400 sm:hidden">Swipe chart horizontally to view all months.</p>
                <div class="overflow-x-auto overscroll-x-contain focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" role="region" aria-label="Monthly revenue line chart" tabindex="0">
                <svg id="dashboard-revenue-chart" class="h-[250px] min-w-[520px] w-full" viewBox="0 0 640 270" preserveAspectRatio="none" role="img" aria-label="Line chart showing monthly net payments for <?= e($selectedYear) ?>">
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

        <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 xl:col-span-2 dark:bg-dark-card dark:ring-zinc-800">
            <div>
                <h3 class="text-lg font-semibold">Order status</h3>
                <p id="dashboard-status-range" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Orders received: <?= e($rangeLabel) ?></p>
            </div>
            <div class="mt-4 flex flex-row items-center justify-center gap-3 sm:justify-start sm:gap-4">
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
                <ul id="dashboard-status-legend" class="min-w-0 flex-1 space-y-2 sm:max-w-44">
                    <?php foreach ($orderStatuses as $status): ?>
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><svg class="size-2.5" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?= e($status['color']) ?>" /></svg><?= e($status['label']) ?></span>
                            <span class="font-semibold"><?= e($status['value']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>
    </div>
</section>
