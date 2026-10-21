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
    <script src="/assets/js/customers.js" defer></script>
    <script src="/assets/js/services.js" defer></script>
    <script src="/assets/js/orders.js" defer></script>
    <script src="/assets/js/payments.js" defer></script>
    <script src="/assets/js/dashboard.js" defer></script>
    <script src="/assets/js/notifications.js" defer></script>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-zinc-900 dark:bg-dark-base dark:text-white">
    <button id="sidebar-backdrop" type="button" aria-label="Close navigation" class="fixed inset-0 z-30 hidden bg-black/50 md:hidden"></button>
    <aside id="app-sidebar" class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-zinc-200 bg-white transition-[width,transform] duration-200 md:translate-x-0 dark:border-zinc-800 dark:bg-dark-card">
        <a href="/dashboard" class="flex h-20 shrink-0 items-center gap-3 border-b border-zinc-100 px-6 font-bold tracking-tight dark:border-zinc-800">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary text-lg text-white" aria-hidden="true">L</span>
            <span class="module-label"><?= e(t('Laundry Manager')) ?></span>
        </a>
        <div class="sidebar-content min-h-0 flex-1 overflow-y-auto px-4 pt-6">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-widest text-zinc-400"><?= e(t('Workspace')) ?></p>
            <?php require dirname(__DIR__) . '/components/module-nav.php'; ?>
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
        <main class="w-full px-4 py-5 sm:px-5 md:px-6 md:py-6">
            <?= $content ?>
        </main>
    </div>
    <script src="/assets/js/profile-menu.js" defer></script>
</body>
</html>
