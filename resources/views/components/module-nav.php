<?php
$moduleItems = [
    ['label' => 'Dashboard', 'path' => '/dashboard', 'available' => true, 'roles' => ['admin', 'staff']],
    ['label' => 'Customers', 'path' => '/customers', 'available' => true, 'roles' => ['admin', 'staff']],
    ['label' => 'Services', 'path' => '/services', 'available' => false, 'roles' => ['admin', 'staff']],
    ['label' => 'Orders', 'path' => '/orders', 'available' => false, 'roles' => ['admin', 'staff']],
    ['label' => 'Payments', 'path' => '/payments', 'available' => false, 'roles' => ['admin', 'staff']],
    ['label' => 'Reports', 'path' => '/reports', 'available' => false, 'roles' => ['admin']],
    ['label' => 'Users', 'path' => '/users', 'available' => false, 'roles' => ['admin']],
];
$currentRole = $user['role'] ?? '';
$navInstance = ($navInstance ?? 0) + 1;
$customerMenuId = 'customer-submenu-' . $navInstance;
$customersActive = $currentPath === '/customers' || str_starts_with($currentPath, '/customers/');
?>
<nav aria-label="Modules" class="mt-3 space-y-1">
    <?php foreach ($moduleItems as $item): ?>
        <?php if (in_array($currentRole, $item['roles'], true)): ?>
            <?php if ($item['label'] === 'Customers'): ?>
                <div>
                    <button type="button" class="js-customer-menu-toggle flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium <?= $customersActive ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"
                            aria-expanded="<?= $customersActive ? 'true' : 'false' ?>" aria-controls="<?= e($customerMenuId) ?>">
                        <span class="flex items-center gap-3"><span class="grid size-6 place-items-center text-base" aria-hidden="true">♙</span><?= e($item['label']) ?></span>
                        <svg class="size-4 shrink-0 transition-transform duration-200 <?= $customersActive ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div id="<?= e($customerMenuId) ?>" class="js-customer-submenu overflow-hidden transition-[max-height,opacity] duration-200 ease-in-out <?= $customersActive ? 'customer-submenu-open' : '' ?>" <?= $customersActive ? '' : 'hidden' ?>>
                        <div class="ml-6 mt-1 space-y-1 border-l border-zinc-200 py-1 pl-3 dark:border-zinc-700">
                            <a href="/customers" <?= $currentPath === '/customers' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/customers' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">All customers</a>
                            <a href="/customers/create" <?= $currentPath === '/customers/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/customers/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>">Add customer</a>
                        </div>
                    </div>
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
