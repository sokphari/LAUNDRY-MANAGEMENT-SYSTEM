<?php
$currentRole = (string) ($user['role'] ?? '');
$permissions = array_fill_keys($user['permissions'] ?? [], true);
$canOpen = static function (array $item) use ($currentRole, $permissions): bool {
    if (!in_array($currentRole, ['admin', 'staff'], true)) return false;
    if (isset($item['submenuItems'])) {
        foreach ($item['submenuItems'] as $subItem) {
            if ($currentRole === 'admin' || isset($permissions[$subItem['permission']])) return true;
        }
        return false;
    }
    if (!isset($item['permission'])) return true;
    return $currentRole === 'admin' || isset($permissions[$item['permission']]);
};
$navInstance = ($navInstance ?? 0) + 1;
$ordersActive = $currentPath === '/orders' || str_starts_with($currentPath, '/orders/');
$groups = $currentRole === 'admin'
    ? [
        'Workspace' => [
            ['label' => 'Dashboard', 'path' => '/dashboard', 'permission' => 'dashboard.view', 'icon' => 'dashboard'],
            ['label' => 'Orders', 'path' => '/orders', 'icon' => 'orders', 'submenuItems' => [
                ['label' => 'View orders', 'path' => '/orders', 'permission' => 'orders.view'],
                ['label' => 'Create order', 'path' => '/orders/create', 'permission' => 'orders.manage'],
            ]],
            ['label' => 'Customers', 'path' => '/customers', 'icon' => 'customers', 'submenuItems' => [
                ['label' => 'View customers', 'path' => '/customers', 'permission' => 'customers.view'],
                ['label' => 'Create customer', 'path' => '/customers/create', 'permission' => 'customers.manage'],
            ]],
            ['label' => 'Payments', 'path' => '/payments', 'permission' => 'payments.view', 'icon' => 'payments'],
        ],
        'Management' => [
            ['label' => 'Services', 'path' => '/services', 'icon' => 'services', 'submenuItems' => [
                ['label' => 'View services', 'path' => '/services', 'permission' => 'services.view'],
                ['label' => 'Create service', 'path' => '/services/create', 'permission' => 'services.manage'],
            ]],
            ['label' => 'Users', 'path' => '/users', 'icon' => 'users', 'submenuItems' => [
                ['label' => 'User accounts', 'path' => '/users', 'permission' => 'users.manage'],
                ['label' => 'Roles & permissions', 'path' => '/roles', 'permission' => 'roles.manage'],
            ]],
            ['label' => 'Backup & Restore', 'path' => '/backups', 'icon' => 'backup'],
        ],
        'Reports & System' => [
            ['label' => 'Reports', 'path' => '/reports', 'permission' => 'reports.view', 'icon' => 'reports'],
            ['label' => 'Audit Logs', 'path' => '/audit-logs', 'permission' => 'audit_logs.view', 'icon' => 'audit'],
        ],
    ]
    : [
        'Workspace' => [
            ['label' => 'Dashboard', 'path' => '/dashboard', 'permission' => 'dashboard.view', 'icon' => 'dashboard'],
            ['label' => 'Orders', 'path' => '/orders', 'icon' => 'orders', 'submenuItems' => [
                ['label' => 'View orders', 'path' => '/orders', 'permission' => 'orders.view'],
                ['label' => 'Create order', 'path' => '/orders/create', 'permission' => 'orders.manage'],
            ]],
            ['label' => 'Customers', 'path' => '/customers', 'icon' => 'customers', 'submenuItems' => [
                ['label' => 'View customers', 'path' => '/customers', 'permission' => 'customers.view'],
                ['label' => 'Create customer', 'path' => '/customers/create', 'permission' => 'customers.manage'],
            ]],
            ['label' => 'Payments', 'path' => '/payments', 'permission' => 'payments.view', 'icon' => 'payments'],
            ['label' => 'Services', 'path' => '/services', 'permission' => 'services.view', 'icon' => 'services'],
        ],
    ];
$iconMarkup = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'services' => '<path d="M4 4h16v16H4z"/><path d="M4 8h16M8 4v4"/><circle cx="12" cy="14" r="4"/><path d="m14.8 11.2-5.6 5.6"/>',
    'orders' => '<path d="M8 4H6a2 2 0 0 0-2 2v14h16V6a2 2 0 0 0-2-2h-2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 11h8M8 15h8"/>',
    'audit' => '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h5M8 16h3"/><circle cx="17" cy="16" r="2"/>',
    'payments' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 10h18M7 15h4"/><path d="M7 5V3h12"/>',
    'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v2"/>',
    'profile' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.5-3.8 3.1-5.8 7-5.8s6.5 2 7 5.8"/>',
    'backup' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
];
$renderLink = static function (array $item, bool $active, string $icon) use ($iconMarkup): void {
    $classes = 'module-link flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ' . ($active
        ? 'bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-200'
        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white');
    ?>
    <a href="<?= e($item['path']) ?>" <?= $active ? 'aria-current="page"' : '' ?> title="<?= e(t($item['label'])) ?>" class="<?= $classes ?>">
        <svg class="module-icon size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconMarkup[$icon] ?></svg>
        <span class="module-label truncate"><?= e(t($item['label'])) ?></span>
    </a>
    <?php
};
?>
<nav aria-label="Modules" class="mt-3 space-y-5">
    <?php foreach ($groups as $groupLabel => $items): ?>
        <?php $visibleItems = array_values(array_filter($items, $canOpen)); if ($visibleItems === []) continue; ?>
        <section aria-label="<?= e(t($groupLabel)) ?>">
            <h2 class="sidebar-section-title mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500"><?= e(t($groupLabel)) ?></h2>
            <div class="space-y-1">
                <?php foreach ($visibleItems as $item): ?>
                    <?php
                    $active = $item['label'] === 'Orders'
                        ? $ordersActive
                        : ($currentPath === $item['path'] || ($item['path'] !== '/dashboard' && str_starts_with($currentPath, $item['path'] . '/')) || ($item['label'] === 'Users' && $currentPath === '/roles'));
                    $subItems = isset($item['submenuItems']) ? array_values(array_filter($item['submenuItems'], $canOpen)) : [];
                    if (count($subItems) < 2) {
                        if (count($subItems) === 1) $item['path'] = $subItems[0]['path'];
                        $renderLink($item, $active, $item['icon']);
                        continue;
                    }
                    $submenuId = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $item['label'])) . '-submenu-' . $navInstance;
                    ?>
                    <div>
                        <button type="button" class="js-module-menu-toggle module-link flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary <?= $active ? 'bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-200' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' ?>" title="<?= e(t('Toggle')) ?> <?= e(t($item['label'])) ?> <?= e(t('submenu')) ?>" aria-label="<?= e(t('Toggle')) ?> <?= e(strtolower(t($item['label']))) ?> <?= e(t('submenu')) ?>" aria-expanded="<?= $active ? 'true' : 'false' ?>" aria-controls="<?= e($submenuId) ?>">
                            <span class="flex min-w-0 items-center gap-3"><svg class="module-icon size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconMarkup[$item['icon']] ?></svg><span class="module-label truncate"><?= e(t($item['label'])) ?></span></span>
                            <svg class="module-group-chevron size-4 shrink-0 transition-transform duration-300 <?= $active ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                        <div id="<?= e($submenuId) ?>" class="js-module-submenu overflow-hidden transition-[max-height,opacity] duration-300 ease-in-out <?= $active ? 'customer-submenu-open' : '' ?>" <?= $active ? '' : 'hidden' ?>>
                            <div class="ml-6 mt-1 space-y-1 border-l border-zinc-200 py-1 pl-3 dark:border-zinc-700">
                                <?php foreach ($subItems as $subItem): ?>
                                    <?php
                                    $subActive = $currentPath === $subItem['path'];
                                    if (!$subActive && str_starts_with($currentPath, $subItem['path'] . '/')) {
                                        $subActive = true;
                                        foreach ($subItems as $moreSpecificItem) {
                                            if ($moreSpecificItem['path'] !== $subItem['path'] && ($currentPath === $moreSpecificItem['path'] || str_starts_with($currentPath, $moreSpecificItem['path'] . '/'))) {
                                                $subActive = false;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <a href="<?= e($subItem['path']) ?>" <?= $subActive ? 'aria-current="page"' : '' ?> class="block rounded-md px-3 py-2 text-sm transition-colors <?= $subActive ? 'font-semibold text-primary dark:text-violet-300' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' ?>"><?= e(t($subItem['label'])) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</nav>
