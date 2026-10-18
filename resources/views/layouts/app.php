<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Laundry Management System') ?></title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/sidebar.js" defer></script>
    <script src="/assets/js/actions.js" defer></script>
    <script src="/assets/js/customers.js" defer></script>
    <script src="/assets/js/services.js" defer></script>
    <script src="/assets/js/orders.js" defer></script>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-zinc-900 dark:bg-dark-base dark:text-white">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-zinc-200 bg-white md:flex dark:border-zinc-800 dark:bg-dark-card">
        <a href="/dashboard" class="flex h-20 items-center gap-3 border-b border-zinc-100 px-6 font-bold tracking-tight dark:border-zinc-800">
            <span class="grid size-9 place-items-center rounded-xl bg-primary text-lg text-white" aria-hidden="true">L</span>
            <span>Laundry Manager</span>
        </a>
        <div class="px-4 pt-6">
            <p class="px-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">Workspace</p>
            <?php require dirname(__DIR__) . '/components/module-nav.php'; ?>
        </div>
    </aside>

    <div class="min-h-screen md:pl-64">
        <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-zinc-200 bg-white/95 px-5 backdrop-blur sm:px-7 md:px-9 dark:border-zinc-800 dark:bg-dark-card/95">
            <div class="flex items-center gap-3">
                <details class="relative md:hidden">
                    <summary class="cursor-pointer list-none rounded-lg border border-zinc-200 px-3 py-2 text-sm font-medium dark:border-zinc-700">Menu</summary>
                    <div class="absolute left-0 top-12 z-40 w-72 rounded-xl border border-zinc-200 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-dark-card">
                        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">Workspace</p>
                        <?php require dirname(__DIR__) . '/components/module-nav.php'; ?>
                    </div>
                </details>
                <span class="font-bold md:hidden">Laundry Manager</span>
                <h1 class="hidden text-sm font-semibold text-zinc-700 md:block dark:text-zinc-200"><?= e($title ?? 'Laundry Management System') ?></h1>
            </div>
            <div class="ml-auto flex items-center gap-2 sm:gap-4">
                <?php require dirname(__DIR__) . '/components/notification-bell.php'; ?>
                <?php require dirname(__DIR__) . '/components/theme-toggle.php'; ?>
                <div class="hidden text-right sm:block">
                    <p class="max-w-36 truncate text-sm font-semibold sm:max-w-56"><?= e($user['username'] ?? '') ?></p>
                    <p class="text-xs capitalize text-zinc-500 dark:text-zinc-400"><?= e($user['role'] ?? '') ?></p>
                </div>
                <form method="post" action="/logout">
                <input type="hidden" name="_csrf_token" value="<?= e($csrfToken ?? '') ?>">
                    <button class="rounded-lg border border-zinc-300 px-3 py-2.5 text-sm font-medium hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:hover:bg-zinc-800" type="submit">Sign out</button>
                </form>
            </div>
        </header>
        <main class="w-full px-4 py-5 sm:px-5 md:px-6 md:py-6">
            <?= $content ?>
        </main>
    </div>
</body>
</html>
