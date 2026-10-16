<section class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
    <p class="text-sm font-semibold uppercase tracking-widest text-primary">Laundry Management</p>
    <h1 class="mt-3 text-2xl font-bold tracking-tight">Sign in to your account</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Use your staff username or email.</p>

    <?php if (!empty($error)): ?>
        <p role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <form class="mt-6 space-y-5" method="post" action="/login">
        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
        <div>
            <label for="login" class="mb-2 block text-sm font-medium">Username or email</label>
            <input id="login" name="login" type="text" autocomplete="username" required maxlength="150"
                   value="<?= e($oldLogin) ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900"
                   placeholder="Your username or email">
        </div>
        <div>
            <label for="password" class="mb-2 block text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900"
                   placeholder="Your password">
        </div>
        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            Sign in
        </button>
    </form>
</section>
