<?php
$profileName = (string) ($user['full_name'] ?? $user['username'] ?? 'User');
$profileUsername = (string) ($user['username'] ?? '');
$profileInitial = strtoupper(substr($profileName, 0, 1));
$profileImage = is_string($user['profile'] ?? null) ? $user['profile'] : '';
$profileImageUrl = filter_var($profileImage, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($profileImage, PHP_URL_SCHEME)), ['http', 'https'], true) && !parse_url($profileImage, PHP_URL_USER) && !parse_url($profileImage, PHP_URL_PASS);
$profileImageLocal = preg_match('~\\A/uploads/profiles/[a-f0-9]{32}\\.(?:jpg|png|webp|gif)\\z~', $profileImage) === 1;
?>
<details class="profile-menu relative">
    <summary class="flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-zinc-200 bg-white py-1 pl-1 pr-2 hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:bg-zinc-900 sm:gap-3 sm:pr-3">
        <?php if ($profileImageUrl || $profileImageLocal): ?><img src="<?= e($profileImage) ?>" alt="" class="size-8 shrink-0 rounded-full border border-zinc-200 object-cover dark:border-zinc-700"><?php else: ?><span class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white" aria-hidden="true"><?= e($profileInitial) ?></span><?php endif; ?>
        <span class="hidden min-w-0 text-left sm:block">
            <span class="block max-w-28 truncate text-sm font-semibold leading-4"><?= e($profileName) ?></span>
            <span class="mt-0.5 block text-xs capitalize leading-3 text-zinc-500 dark:text-zinc-400"><?= e(t(ucfirst((string) ($user['role'] ?? '')))) ?></span>
        </span>
        <svg class="hidden size-4 shrink-0 text-zinc-500 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5"/></svg>
        <span class="sr-only"><?= e(t('Open user menu')) ?></span>
    </summary>
    <div class="absolute right-0 top-full z-50 mt-2 w-56 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-dark-card">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <p class="truncate text-sm font-semibold"><?= e($profileName) ?></p>
            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400"><?= e($user['email'] ?? '@' . $profileUsername) ?></p>
            <p class="mt-1 text-xs capitalize text-zinc-500 dark:text-zinc-400"><?= e(t(ucfirst((string) ($user['role'] ?? '')))) ?></p>
        </div>
        <div class="border-b border-zinc-200 py-1 dark:border-zinc-700">
            <a href="/profile" class="block px-4 py-2.5 text-sm font-medium hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-primary dark:hover:bg-zinc-800"><?= e(t('My Profile')) ?></a>
            <a href="/account/security" class="block px-4 py-2.5 text-sm font-medium hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-primary dark:hover:bg-zinc-800"><?= e(t('Account Security')) ?></a>
        </div>
        <form method="post" action="/logout" class="p-1">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken ?? '') ?>">
            <button class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-primary dark:text-red-300 dark:hover:bg-red-950/50" type="submit">
                <span class="inline-flex items-center gap-2">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                    <?= e(t('Sign out')) ?>
                </span>
            </button>
        </form>
    </div>
</details>
