<?php
$activeLocale = app_locale();
$languageReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/');
if (!str_starts_with($languageReturnTo, '/') || str_starts_with($languageReturnTo, '//') || str_contains($languageReturnTo, "\\")) {
    $languageReturnTo = '/';
}
?>
<details class="language-menu relative">
    <summary aria-label="<?= e(t('Language')) ?>" title="<?= e(t('Language')) ?>" class="grid size-10 cursor-pointer list-none place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 6 6m-7 0 6-6 2-3M2 5h12M7 2h1m12 19-5-11-5 11m2-4h6"/></svg>
        <span class="sr-only"><?= e(t('Language')) ?></span>
    </summary>
    <div class="absolute right-0 top-full z-50 mt-2 w-44 overflow-hidden rounded-xl border border-zinc-200 bg-white p-1.5 shadow-xl dark:border-zinc-700 dark:bg-dark-card">
        <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"><?= e(t('Language')) ?></p>
        <?php foreach (['en' => ['code' => 'EN', 'label' => 'English'], 'km' => ['code' => 'KM', 'label' => 'Khmer']] as $localeCode => $language): ?>
            <form method="post" action="/language">
                <input type="hidden" name="_csrf_token" value="<?= e(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="locale" value="<?= e($localeCode) ?>">
                <input type="hidden" name="return_to" value="<?= e($languageReturnTo) ?>">
                <button type="submit" <?= $activeLocale === $localeCode ? 'aria-current="true"' : '' ?> class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm <?= $activeLocale === $localeCode ? 'bg-violet-50 font-semibold text-primary dark:bg-violet-950/50 dark:text-violet-300' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' ?>">
                    <span><?= $language['code'] ?> <span class="ml-2"><?= e(t($language['label'])) ?></span></span>
                    <?php if ($activeLocale === $localeCode): ?><span aria-hidden="true">✓</span><?php endif; ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</details>
