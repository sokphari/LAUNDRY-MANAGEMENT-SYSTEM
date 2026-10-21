<?php $notificationCsrfToken = $csrfToken ?? \App\Core\Csrf::token(); ?>
<div class="relative" data-notifications data-endpoint="/notifications" data-read-endpoint="/notifications/read" data-csrf-token="<?= e($notificationCsrfToken) ?>">
    <button id="notifications-toggle" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="notifications-panel"
            class="relative grid size-10 shrink-0 place-items-center rounded-lg border border-zinc-200 bg-white text-zinc-500 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12a2 2 0 0 0 4 0"/>
        </svg>
        <span data-notification-badge hidden aria-hidden="true" class="absolute -right-1 -top-1 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-4 text-white"></span>
    </button>
    <section id="notifications-panel" hidden aria-labelledby="notifications-title"
             class="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-dark-card">
        <header class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <h2 id="notifications-title" class="text-sm font-semibold">Security notifications</h2>
            <div class="flex items-center gap-3">
                <button type="button" data-notification-mark-read hidden class="text-xs font-semibold text-zinc-500 hover:text-primary dark:text-zinc-400">Mark all read</button>
                <?php if (in_array('audit_logs.view', $user['permissions'] ?? [], true)): ?>
                    <a href="/audit-logs" class="text-xs font-semibold text-primary hover:underline">Audit logs</a>
                <?php endif; ?>
            </div>
        </header>
        <div data-notification-list role="list" aria-live="polite" class="max-h-96 overflow-y-auto">
            <p class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Loading notifications…</p>
        </div>
    </section>
</div>
