<dialog id="app-confirm-dialog" aria-labelledby="app-confirm-title" aria-describedby="app-confirm-message" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-2xl backdrop:bg-zinc-950/70 backdrop:backdrop-blur-sm dark:border-zinc-700 dark:bg-dark-card dark:text-white">
    <div class="p-6 sm:p-7">
        <div class="flex items-start gap-4">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-300" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/><path d="M10.3 3.9 1.9 18.1A2 2 0 0 0 3.6 21h16.8a2 2 0 0 0 1.7-2.9L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
            <div class="min-w-0 pt-0.5">
                <h2 id="app-confirm-title" class="text-lg font-semibold tracking-tight"><?= e(t('Confirm customer deactivation')) ?></h2>
                <p id="app-confirm-message" data-confirm-message class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300"></p>
            </div>
        </div>
        <div class="mt-7 flex justify-end gap-3">
            <button type="button" data-confirm-cancel class="rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><?= e(t('Cancel')) ?></button>
            <button type="button" data-confirm-accept class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-dark-card">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg>
                <?= e(t('Deactivate')) ?>
            </button>
        </div>
    </div>
</dialog>
