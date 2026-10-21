<!doctype html>
<html lang="<?= e(app_locale()) ?>" data-translations="<?= e(json_encode(app_translations(), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t($title ?? 'Laundry Management System')) ?></title>
    <script src="/assets/js/theme.js"></script>
    <script src="/assets/js/auth.js" defer></script>
    <script src="/assets/js/i18n.js" defer></script>
    <script src="/assets/js/language-menu.js" defer></script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-[#F8FAFC] text-zinc-900 dark:bg-dark-base dark:text-white">
    <div class="fixed right-5 top-5 z-10 flex items-center gap-2">
        <?php require dirname(__DIR__) . '/components/language-switch.php'; ?>
        <?php require dirname(__DIR__) . '/components/theme-toggle.php'; ?>
    </div>
    <main class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-6 py-12">
        <?= $content ?>
    </main>
</body>
</html>
