<section class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
    <img src="/assets/images/laundry-manager-logo.png" alt="Laundry Manager" class="mx-auto mb-6 h-24 w-60 rounded-lg bg-white p-2 object-contain">
    <h1 class="text-2xl font-bold tracking-tight"><?= e(t('Sign in to your account')) ?></h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"><?= e(t('Use your staff username or email.')) ?></p>

    <?php if (!empty($error)): ?>
        <p role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <form class="mt-6 space-y-5" method="post" action="/login">
        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
        <div>
            <label for="login" class="mb-2 block text-sm font-medium"><?= e(t('Username or email')) ?></label>
            <input id="login" name="login" type="text" autocomplete="username" required maxlength="150"
                   value="<?= e($oldLogin) ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900"
                   placeholder="<?= e(t('Your username or email')) ?>">
        </div>
        <div>
            <label for="password" class="mb-2 block text-sm font-medium"><?= e(t('Password')) ?></label>
            <div class="relative">
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 pr-12 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900"
                       placeholder="<?= e(t('Your password')) ?>">
                <button type="button" data-password-toggle aria-controls="password" data-show-label="<?= e(t('Show password')) ?>" data-hide-label="<?= e(t('Hide password')) ?>" aria-label="<?= e(t('Show password')) ?>" aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3 text-zinc-500 hover:text-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary dark:text-zinc-400 dark:hover:text-violet-300">
                    <svg data-password-show-icon xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.4-7 9.5-7 9.5 7 9.5 7-3.4 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg data-password-hide-icon xmlns="http://www.w3.org/2000/svg" class="hidden size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.1 0 9.5 7 9.5 7a15.6 15.6 0 0 1-3.1 3.9M6.2 6.2C3.8 7.9 2.5 12 2.5 12s3.4 7 9.5 7c1 0 2-.2 2.9-.5"/></svg>
                </button>
            </div>
        </div>
        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            <?= e(t('Sign in')) ?>
        </button>
    </form>
</section>
