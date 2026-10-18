<?php
$moduleItems = [
    ['label' => 'Dashboard', 'path' => '/dashboard', 'available' => true, 'roles' => ['admin', 'staff']],
    ['label' => 'Customers', 'path' => '/customers', 'available' => true, 'roles' => ['admin', 'staff']],
    ['label' => 'Services', 'path' => '/services', 'available' => true, 'roles' => ['admin', 'staff']],
    ['label' => 'Orders', 'path' => '/orders', 'available' => true, 'roles' => ['admin', 'staff']],
    ['label' => 'Audit Logs', 'path' => '/audit-logs', 'available' => true, 'roles' => ['admin']],
    ['label' => 'Payments', 'path' => '/payments', 'available' => false, 'roles' => ['admin', 'staff']],
    ['label' => 'Reports', 'path' => '/reports', 'available' => false, 'roles' => ['admin']],
    ['label' => 'Users', 'path' => '/users', 'available' => false, 'roles' => ['admin']],
];
$currentRole = $user['role'] ?? '';
$navInstance = ($navInstance ?? 0) + 1;
$customerMenuId = 'customer-submenu-' . $navInstance;
$serviceMenuId = 'service-submenu-' . $navInstance;
$orderMenuId = 'order-submenu-' . $navInstance;
$customersActive = $currentPath === '/customers' || str_starts_with($currentPath, '/customers/');
$servicesActive = $currentPath === '/services' || str_starts_with($currentPath, '/services/');
$ordersActive = $currentPath === '/orders' || str_starts_with($currentPath, '/orders/');
?>
<nav aria-label="Modules" class="mt-3 space-y-1">
    <?php foreach ($moduleItems as $item): ?>
        <?php if (in_array($currentRole, $item['roles'], true)): ?>
            <?php if ($item['label'] === 'Customers'): ?>
                <div>
                    <div class="flex items-center gap-1">
                        <a href="/customers" <?= $customersActive ? 'aria-current="page"' : '' ?> class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $customersActive ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><span class="grid size-6 place-items-center text-base" aria-hidden="true">♙</span><?= e($item['label']) ?></a>
                        <button type="button" class="js-module-menu-toggle grid size-10 shrink-0 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="Toggle customer submenu" aria-expanded="<?= $customersActive ? 'true' : 'false' ?>" aria-controls="<?= e($customerMenuId) ?>">
                            <svg class="size-4 transition-transform duration-200 <?= $customersActive ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                    <div id="<?= e($customerMenuId) ?>" class="js-module-submenu overflow-hidden transition-[max-height,opacity] duration-200 ease-in-out <?= $customersActive ? 'customer-submenu-open' : '' ?>" <?= $customersActive ? '' : 'hidden' ?>>
                        <div class="ml-6 mt-1 space-y-1 border-l border-zinc-200 py-1 pl-3 dark:border-zinc-700">
                            <a href="/customers" <?= $currentPath === '/customers' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/customers' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Views customers</a>
                            <a href="/customers/create" <?= $currentPath === '/customers/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/customers/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Create customer</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($item['label'] === 'Services' && $item['available']): ?>
                <div>
                    <div class="flex items-center gap-1">
                        <a href="/services" <?= $servicesActive ? 'aria-current="page"' : '' ?> class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $servicesActive ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><span class="grid size-6 place-items-center text-base" aria-hidden="true">♧</span><?= e($item['label']) ?></a>
                        <button type="button" class="js-module-menu-toggle grid size-10 shrink-0 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="Toggle services submenu" aria-expanded="<?= $servicesActive ? 'true' : 'false' ?>" aria-controls="<?= e($serviceMenuId) ?>">
                            <svg class="size-4 transition-transform duration-200 <?= $servicesActive ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                    <div id="<?= e($serviceMenuId) ?>" class="js-module-submenu overflow-hidden transition-[max-height,opacity] duration-200 ease-in-out <?= $servicesActive ? 'customer-submenu-open' : '' ?>" <?= $servicesActive ? '' : 'hidden' ?>>
                        <div class="ml-6 mt-1 space-y-1 border-l border-zinc-200 py-1 pl-3 dark:border-zinc-700">
                            <a href="/services" <?= $currentPath === '/services' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/services' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Views services</a>
                            <?php if ($currentRole === 'admin'): ?><a href="/services/create" <?= $currentPath === '/services/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/services/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Create service</a><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($item['label'] === 'Orders' && $item['available']): ?>
                <div>
                    <div class="flex items-center gap-1">
                        <a href="/orders" <?= $ordersActive ? 'aria-current="page"' : '' ?> class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $ordersActive ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><span class="grid size-6 place-items-center text-base" aria-hidden="true">▤</span><?= e($item['label']) ?></a>
                        <button type="button" class="js-module-menu-toggle grid size-10 shrink-0 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="Toggle orders submenu" aria-expanded="<?= $ordersActive ? 'true' : 'false' ?>" aria-controls="<?= e($orderMenuId) ?>"><svg class="size-4 transition-transform duration-200 <?= $ordersActive ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
                    </div>
                    <div id="<?= e($orderMenuId) ?>" class="js-module-submenu overflow-hidden transition-[max-height,opacity] duration-200 ease-in-out <?= $ordersActive ? 'customer-submenu-open' : '' ?>" <?= $ordersActive ? '' : 'hidden' ?>><div class="ml-6 mt-1 space-y-1 border-l border-zinc-200 py-1 pl-3 dark:border-zinc-700">
                        <a href="/orders" <?= $currentPath === '/orders' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/orders' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Views orders</a>
                        <a href="/orders/create" <?= $currentPath === '/orders/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/orders/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Create order</a>
                    </div></div>
                </div>
            <?php elseif ($item['available']): ?>
                <?php $active = $currentPath === $item['path'] || ($item['path'] !== '/dashboard' && str_starts_with($currentPath, $item['path'] . '/')); ?>
                <a href="<?= e($item['path']) ?>" <?= $active ? 'aria-current="page"' : '' ?>
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $active ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">
                    <span class="grid size-6 place-items-center text-base" aria-hidden="true"><?= $item['label'] === 'Dashboard' ? '⌂' : '♙' ?></span>
                    <?= e($item['label']) ?>
                </a>
            <?php else: ?>
                <span aria-disabled="true" title="Coming soon" class="flex cursor-not-allowed items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-400 dark:text-zinc-500">
                    <span class="flex items-center gap-3"><span class="grid size-6 place-items-center text-base" aria-hidden="true">◦</span><?= e($item['label']) ?></span>
                    <span class="text-[10px] font-semibold uppercase tracking-wide">Soon</span>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
