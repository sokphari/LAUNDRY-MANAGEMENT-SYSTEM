<?php
$isAuthenticated = \App\Core\Auth::check();
$returnPath = $isAuthenticated ? '/dashboard' : '/login';
$returnLabel = $isAuthenticated ? t('Return to dashboard') : t('Go to sign in');
?>
<section class="w-full max-w-xl rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800 sm:p-12">
    <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-violet-100 text-primary dark:bg-violet-950/70 dark:text-violet-300" aria-hidden="true">
        <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/><path d="M4.5 7.8 12 12l7.5-4.2M12 12v8.5"/><path d="m9 5 8 4.5"/></svg>
    </span>
    <p class="mt-6 text-sm font-bold uppercase tracking-[0.18em] text-primary">404</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight"><?= e(t('Page not found')) ?></h1>
    <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-zinc-600 dark:text-zinc-300"><?= e(t('The page you requested may have moved or the address may be incorrect.')) ?></p>
    <a href="<?= e($returnPath) ?>" class="mt-7 inline-flex items-center justify-center rounded-lg bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-offset-dark-card"><?= e($returnLabel) ?></a>
</section>
