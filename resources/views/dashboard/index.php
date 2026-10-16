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
$chartMax = 1200000;
foreach ($yearlyRevenue as $index => $entry) {
    $chartPoints[] = [
        'x' => 66 + ($index * 46),
        'y' => 210 - ((int) $entry['amount'] / $chartMax * 165),
        'month' => $entry['month'],
        'amount' => $entry['amount'],
    ];
}
$linePath = implode(' ', array_map(
    static fn (array $point): string => number_format($point['x'], 1, '.', '') . ',' . number_format($point['y'], 1, '.', ''),
    $chartPoints
));
$areaPath = 'M ' . number_format($chartPoints[0]['x'], 1, '.', '') . ' 210 L ' . str_replace(',', ' ', $linePath)
    . ' L ' . number_format($chartPoints[array_key_last($chartPoints)]['x'], 1, '.', '') . ' 210 Z';
?>
<section class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">Overview</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight">Welcome, <?= e($user['full_name'] ?? '') ?></h2>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300">Here is what is happening in your laundry shop.</p>
        </div>
    </div>

    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
        These are example figures for the dashboard design. Live totals will be connected when orders and payments are implemented.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($stats as $index => $stat): ?>
            <?php $tone = $toneStyles[$stat['tone']] ?? $toneStyles['violet']; ?>
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400"><?= e($stat['label']) ?></p>
                        <p class="mt-3 text-2xl font-bold tracking-tight"><?= e($stat['value']) ?></p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl <?= e($tone['icon']) ?>" aria-hidden="true">
                        <?php if ($index === 0): ?>♙<?php elseif ($index === 1): ?>៛<?php elseif ($index === 2): ?>▤<?php else: ?>◷<?php endif; ?>
                    </span>
                </div>
                <p class="mt-3 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"><span class="size-1.5 rounded-full <?= e($tone['dot']) ?>"></span><?= e($stat['note']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-5 xl:grid-cols-5 xl:items-start">
        <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 xl:col-span-3 dark:bg-dark-card dark:ring-zinc-800">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold">Revenue in <?= e($selectedYear) ?></h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Sample monthly revenue · KHR · January to December</p>
                </div>
                <form method="get" action="/dashboard" class="flex items-center gap-2">
                    <label for="revenue-year" class="text-sm text-zinc-600 dark:text-zinc-300">Year</label>
                    <select id="revenue-year" name="year" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900">
                        <?php foreach ($years as $year): ?>
                            <option value="<?= e($year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>><?= e($year) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-hover" type="submit">Show</button>
                </form>
            </div>
            <div class="mt-3 overflow-x-auto">
                <svg class="h-[250px] min-w-[520px] w-full" viewBox="0 0 640 270" preserveAspectRatio="none" role="img" aria-label="Line chart showing example monthly revenue for <?= e($selectedYear) ?>">
                    <defs>
                        <linearGradient id="revenue-fill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.22" />
                            <stop offset="100%" stop-color="#7C3AED" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <g stroke="currentColor" class="text-zinc-100 dark:text-zinc-800" stroke-width="1">
                        <line x1="58" y1="45" x2="620" y2="45" />
                        <line x1="58" y1="100" x2="620" y2="100" />
                        <line x1="58" y1="155" x2="620" y2="155" />
                        <line x1="58" y1="210" x2="620" y2="210" />
                    </g>
                    <g fill="currentColor" class="text-zinc-400" font-size="11" text-anchor="end">
                        <text x="50" y="49">1.0M</text><text x="50" y="104">750K</text><text x="50" y="159">500K</text><text x="50" y="214">0</text>
                    </g>
                    <path d="<?= e($areaPath) ?>" fill="url(#revenue-fill)" />
                    <polyline points="<?= e($linePath) ?>" fill="none" stroke="#7C3AED" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <?php foreach ($chartPoints as $point): ?>
                        <circle cx="<?= e(number_format($point['x'], 1, '.', '')) ?>" cy="<?= e(number_format($point['y'], 1, '.', '')) ?>" r="4" fill="#fff" stroke="#7C3AED" stroke-width="3">
                            <title><?= e($point['month'] . ' ' . $selectedYear . ': ' . number_format($point['amount']) . ' KHR') ?></title>
                        </circle>
                        <text x="<?= e(number_format($point['x'], 1, '.', '')) ?>" y="238" fill="currentColor" class="text-zinc-500" font-size="11" text-anchor="middle"><?= e($point['month']) ?></text>
                    <?php endforeach; ?>
                </svg>
            </div>
        </article>

        <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 xl:col-span-2 dark:bg-dark-card dark:ring-zinc-800">
            <div>
                <h3 class="text-lg font-semibold">Order status</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Example order distribution</p>
            </div>
            <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:justify-start">
                <div class="relative size-40 shrink-0">
                    <svg class="size-full -rotate-90" viewBox="0 0 140 140" role="img" aria-label="Pie chart showing example orders by status">
                        <circle cx="70" cy="70" r="36" fill="none" stroke="currentColor" class="text-zinc-100 dark:text-zinc-800" stroke-width="14" />
                        <?php foreach ($orderStatuses as $status): ?>
                            <?php $segmentLength = ($status['value'] / $statusTotal) * $circumference; ?>
                            <circle cx="70" cy="70" r="36" fill="none" stroke="<?= e($status['color']) ?>" stroke-width="14"
                                    stroke-dasharray="<?= e(number_format($segmentLength, 2, '.', '')) ?> <?= e(number_format($circumference - $segmentLength, 2, '.', '')) ?>"
                                    stroke-dashoffset="-<?= e(number_format($statusOffset, 2, '.', '')) ?>" />
                            <?php $statusOffset += $segmentLength; ?>
                        <?php endforeach; ?>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-bold"><?= e($statusTotal) ?></span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">orders</span>
                    </div>
                </div>
                <ul class="w-full space-y-2 sm:max-w-44">
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
