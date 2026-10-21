<?php
$moduleItems = [
    ['label' => 'Dashboard', 'path' => '/dashboard', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'dashboard.view', 'icon' => 'dashboard'],
    ['label' => 'Customers', 'path' => '/customers', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'customers.view', 'icon' => 'customers'],
    ['label' => 'Services', 'path' => '/services', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'services.view', 'icon' => 'services'],
    ['label' => 'Orders', 'path' => '/orders', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'orders.view', 'icon' => 'orders'],
    ['label' => 'Audit Logs', 'path' => '/audit-logs', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'audit_logs.view', 'icon' => 'audit'],
    ['label' => 'Payments', 'path' => '/payments', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'payments.view', 'icon' => 'payments'],
    ['label' => 'Reports', 'path' => '/reports', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'reports.view', 'icon' => 'reports'],
    ['label' => 'Users', 'path' => '/users', 'available' => true, 'roles' => ['admin', 'staff'], 'permission' => 'users.manage', 'icon' => 'users'],
];
$currentRole = $user['role'] ?? '';
$navInstance = ($navInstance ?? 0) + 1;
$customersActive = $currentPath === '/customers' || str_starts_with($currentPath, '/customers/');
$servicesActive = $currentPath === '/services' || str_starts_with($currentPath, '/services/');
$ordersActive = $currentPath === '/orders' || str_starts_with($currentPath, '/orders/');
$usersActive = $currentPath === '/users' || str_starts_with($currentPath, '/users/') || $currentPath === '/roles';
$iconMarkup = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'services' => '<path d="M4 4h16v16H4z"/><path d="M4 8h16M8 4v4"/><circle cx="12" cy="14" r="4"/><path d="m14.8 11.2-5.6 5.6"/>',
    'orders' => '<path d="M8 4H6a2 2 0 0 0-2 2v14h16V6a2 2 0 0 0-2-2h-2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 11h8M8 15h8"/>',
    'audit' => '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h5M8 16h3"/><circle cx="17" cy="16" r="2"/>',
    'payments' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 10h18M7 15h4"/><path d="M7 5V3h12"/>',
    'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v2"/>',
];
?>
<nav aria-label="Modules" class="mt-3 space-y-1">
    <?php foreach ($moduleItems as $item): ?>
        <?php if (in_array($currentRole, $item['roles'], true) && (!isset($item['permission']) || in_array($item['permission'], $user['permissions'] ?? [], true) || ($item['label'] === 'Users' && in_array('roles.manage', $user['permissions'] ?? [], true)))): ?>
            <?php
            $active = $item['label'] === 'Customers' ? $customersActive : ($item['label'] === 'Services' ? $servicesActive : ($item['label'] === 'Orders' ? $ordersActive : ($item['label'] === 'Users' ? $usersActive : ($currentPath === $item['path'] || ($item['path'] !== '/dashboard' && str_starts_with($currentPath, $item['path'] . '/'))))));
            $hasSubmenu = in_array($item['label'], ['Customers', 'Services', 'Orders', 'Users'], true);
            $submenuId = strtolower($item['label']) . '-submenu-' . $navInstance;
            $linkClasses = 'module-link flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ' . ($active ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white');
            ?>
            <div>
                <div class="module-row flex items-center gap-1">
                    <?php if ($hasSubmenu): ?>
                        <button type="button" class="js-module-menu-toggle module-link flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors <?= $active ? 'bg-violet-50 text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>" title="<?= e(t('Toggle')) ?> <?= e(t($item['label'])) ?> <?= e(t('submenu')) ?>" aria-label="<?= e(t('Toggle')) ?> <?= e(strtolower(t($item['label']))) ?> <?= e(t('submenu')) ?>" aria-expanded="<?= $active ? 'true' : 'false' ?>" aria-controls="<?= e($submenuId) ?>">
                            <span class="flex min-w-0 items-center gap-3"><svg class="module-icon size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconMarkup[$item['icon']] ?></svg><span class="module-label truncate"><?= e(t($item['label'])) ?></span></span>
                            <svg class="module-group-chevron size-4 shrink-0 transition-transform duration-300 <?= $active ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                    <?php else: ?>
                        <a href="<?= e($item['path']) ?>" <?= $active ? 'aria-current="page"' : '' ?> title="<?= e(t($item['label'])) ?>" class="<?= $linkClasses ?>">
                            <svg class="module-icon size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconMarkup[$item['icon']] ?></svg>
                            <span class="module-label truncate"><?= e(t($item['label'])) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($hasSubmenu): ?>
                    <div id="<?= e($submenuId) ?>" class="js-module-submenu overflow-hidden transition-[max-height,opacity] duration-300 ease-in-out <?= $active ? 'customer-submenu-open' : '' ?>" <?= $active ? '' : 'hidden' ?>>
                        <div class="ml-6 mt-1 space-y-1 border-l border-zinc-200 py-1 pl-3 dark:border-zinc-700">
                            <?php if ($item['label'] === 'Users'): ?>
                                <?php if (in_array('users.manage', $user['permissions'] ?? [], true)): ?><a href="/users" <?= $currentPath === '/users' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/users' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('User accounts')) ?></a><?php endif; ?>
                                <?php if (in_array('roles.manage', $user['permissions'] ?? [], true)): ?><a href="/roles" <?= $currentPath === '/roles' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/roles' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('Roles & permissions')) ?></a><?php endif; ?>
                            <?php elseif ($item['label'] === 'Customers'): ?>
                                <a href="/customers" <?= $currentPath === '/customers' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/customers' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('View customers')) ?></a>
                                <a href="/customers/create" <?= $currentPath === '/customers/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/customers/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('Create customer')) ?></a>
                            <?php elseif ($item['label'] === 'Services'): ?>
                                <a href="/services" <?= $currentPath === '/services' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/services' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('View services')) ?></a>
                                <?php if ($currentRole === 'admin'): ?><a href="/services/create" <?= $currentPath === '/services/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/services/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('Create service')) ?></a><?php endif; ?>
                            <?php else: ?>
                                <a href="/orders" <?= $currentPath === '/orders' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/orders' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('View orders')) ?></a>
                                <a href="/orders/create" <?= $currentPath === '/orders/create' ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm <?= $currentPath === '/orders/create' ? 'font-semibold text-primary' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t('Create order')) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
