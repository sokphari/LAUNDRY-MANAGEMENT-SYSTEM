<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Laundry Management System', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-[#F8F7FC] text-zinc-900 dark:bg-dark-base dark:text-white">
    <main class="mx-auto flex min-h-screen max-w-5xl items-center justify-center px-6 py-12">
        <?= $content ?>
    </main>
</body>
</html>
