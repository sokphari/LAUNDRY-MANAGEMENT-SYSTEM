<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!doctype html>
<html lang="<?= e(app_locale()) ?>" data-translations="<?= e(json_encode(app_translations(), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t($title ?? 'Laundry Management System')) ?></title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/sidebar.js" defer></script>
    <script src="/assets/js/i18n.js" defer></script>
    <script src="/assets/js/language-menu.js" defer></script>
    <script src="/assets/js/actions.js" defer></script>
    <script src="/assets/js/notifications.js" defer></script>
    <script src="/assets/js/navigation.js" defer></script>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-zinc-900 dark:bg-dark-base dark:text-white">
    <div id="app-navigation-progress" class="app-navigation-progress" aria-hidden="true"><span></span></div>
    <button id="sidebar-backdrop" type="button" aria-label="Close navigation" class="fixed inset-0 z-30 hidden bg-black/50 md:hidden"></button>
    <aside id="app-sidebar" class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-zinc-200 bg-white transition-[width,transform] duration-200 md:translate-x-0 dark:border-zinc-800 dark:bg-dark-card">
        <a href="/dashboard" class="flex h-20 shrink-0 items-center gap-3 border-b border-zinc-100 px-6 font-bold tracking-tight dark:border-zinc-800">
            <img src="/assets/images/laundry-manager-logo.png" alt="Laundry Manager" class="sidebar-brand-logo h-14 w-full min-w-0 rounded-lg bg-white p-1 object-contain object-left">
            <span class="sidebar-brand-fallback hidden size-9 shrink-0 place-items-center rounded-xl bg-primary text-lg text-white" aria-hidden="true">L</span>
        </a>
        <div class="sidebar-content min-h-0 flex-1 overflow-y-auto px-4 pt-5">
            <?php require dirname(__DIR__) . '/components/module-nav.php'; ?>
        </div>
        <?php
        $profileActive = $currentPath === '/profile' || str_starts_with($currentPath, '/profile/');
        $profileLinkClasses = 'module-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ' . ($profileActive
            ? 'bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-200'
            : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white');
        ?>
        <div id="sidebar-account" class="sidebar-account shrink-0 border-t border-zinc-200 p-4 dark:border-zinc-800">
            <p class="sidebar-section-title mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500"><?= e(t('Account')) ?></p>
            <a href="/profile" <?= $profileActive ? 'aria-current="page"' : '' ?> title="<?= e(t('My Profile')) ?>" class="<?= $profileLinkClasses ?>">
                <svg class="module-icon size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.5-3.8 3.1-5.8 7-5.8s6.5 2 7 5.8"/></svg>
                <span class="module-label truncate"><?= e(t('My Profile')) ?></span>
            </a>
        </div>
    </aside>

    <div id="app-shell" class="min-h-screen transition-[padding] duration-200 md:pl-64">
        <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-zinc-200 bg-white/95 px-5 backdrop-blur sm:px-7 md:px-9 dark:border-zinc-800 dark:bg-dark-card/95">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation" title="Open navigation" class="grid size-10 shrink-0 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    <svg class="size-5 md:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg class="hidden size-5 md:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m4-11 3 3-3 3"/></svg>
                </button>
                <span class="hidden font-bold min-[400px]:inline md:hidden"><?= e(t('Laundry Manager')) ?></span>
                <h1 class="hidden text-sm font-semibold text-zinc-700 md:block dark:text-zinc-200"><?= e(t($title ?? 'Laundry Management System')) ?></h1>
            </div>
            <div class="ml-auto flex items-center gap-2 sm:gap-4">
                <?php require dirname(__DIR__) . '/components/theme-toggle.php'; ?>
                <?php require dirname(__DIR__) . '/components/language-switch.php'; ?>
                <?php require dirname(__DIR__) . '/components/notification-bell.php'; ?>
                <?php require dirname(__DIR__) . '/components/profile-menu.php'; ?>
            </div>
        </header>
        <main tabindex="-1" class="w-full px-4 py-5 transition-opacity duration-150 sm:px-5 md:px-6 md:py-6">
            <?= $content ?>
        </main>
    </div>
    <?php require dirname(__DIR__) . '/components/confirm-dialog.php'; ?>
    <script src="/assets/js/profile-menu.js" defer></script>
</body>
</html>
