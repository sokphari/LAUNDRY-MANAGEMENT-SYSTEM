<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Laundry Management System') ?></title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-[#F8FAFC] text-zinc-900 dark:bg-dark-base dark:text-white">
    <div class="fixed right-5 top-5 z-10">
        <?php require dirname(__DIR__) . '/components/theme-toggle.php'; ?>
    </div>
    <main class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-6 py-12">
        <?= $content ?>
    </main>
</body>
</html>
