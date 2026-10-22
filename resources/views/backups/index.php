<section class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-primary"><?= e(t('System protection')) ?></p>
            <h1 class="mt-2 text-3xl font-bold"><?= e(t('Backup & Restore')) ?></h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300"><?= e(t('Protect and restore your laundry management data.')) ?></p>
        </div>
        <form method="post" action="/backups/create">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><?= e(t('Create backup now')) ?></button>
        </form>
    </header>

    <?php if ($notice !== ''): ?><p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"><?= e(t($notice)) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100"><?= e(t($error)) ?></p><?php endif; ?>

    <?php if (!$settings['automatic_enabled'] || !$settings['database_enabled']): ?>
        <aside role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-100">
            <h2 class="font-semibold"><?= e(t(!$settings['database_enabled'] ? 'Database backups are disabled' : 'Automatic backups are disabled')) ?></h2>
            <p class="mt-1 text-sm"><?= e(t('Your existing backup files are preserved. New scheduled backups will not be created until backups are enabled again.')) ?></p>
            <?php if ($environment === 'production'): ?><p class="mt-2 text-sm font-semibold"><?= e(t('Production warning: scheduled database backups are not currently protected. Enable automatic database backups or create backups manually.')) ?></p><?php endif; ?>
        </aside>
    <?php endif; ?>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
            <div class="flex items-start justify-between gap-4">
                <div><h2 class="text-lg font-semibold"><?= e(t('Backup Settings')) ?></h2><p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400"><?= e(t('System Administration')) ?></p></div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $settings['automatic_enabled'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>"><?= e(t($settings['automatic_enabled'] ? 'Automatic ON' : 'Automatic OFF')) ?></span>
            </div>
            <form method="post" action="/backups/settings" class="mt-2">
                <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                <?php
                $toggleRows = [
                    ['automatic_enabled', 'Automatic Backup', 'Run backups on a schedule', true],
                    ['database_enabled', 'Database Backup', 'Customers, orders, services, payments, users, and audit logs', true],
                    ['uploaded_files_enabled', 'Uploaded Files', 'Profile images uploaded to this application', true],
                    ['offsite_enabled', 'Off-site Storage', 'Keep a protected copy on another server', false],
                    ['notifications_enabled', 'Backup Notifications', 'Alert admins when a scheduled backup fails', true],
                    ['auto_cleanup_enabled', 'Auto Cleanup', 'Remove expired automatic backups after 30 days', true],
                ];
                ?>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php foreach ($toggleRows as [$key, $label, $description, $editable]): ?>
                        <div class="flex items-center justify-between gap-5 py-4">
                            <div><p class="text-sm font-medium"><?= e(t($label)) ?></p><p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400"><?= e(t($description)) ?></p><?php if (!$editable): ?><p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300"><?= e(t('Not configured — set a protected remote destination first')) ?></p><?php endif; ?></div>
                            <label class="relative inline-flex shrink-0 items-center <?= $editable ? 'cursor-pointer' : 'cursor-not-allowed' ?>" aria-label="<?= e(t($label)) ?>">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $settings[$key] ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?> class="peer sr-only">
                                <span class="h-6 w-11 rounded-full bg-zinc-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-white after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5 dark:bg-zinc-700 dark:peer-focus-visible:ring-offset-dark-card"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <fieldset class="border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <legend class="mb-2 text-sm font-medium"><?= e(t('Backup Frequency')) ?></legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <?php foreach (['daily' => 'Daily — Recommended', '6hours' => 'Every 6 Hours', 'weekly' => 'Weekly'] as $value => $label): ?>
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm"><input type="radio" name="frequency" value="<?= e($value) ?>" <?= $settings['frequency'] === $value ? 'checked' : '' ?> class="size-4 border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600 dark:bg-zinc-900"> <?= e(t($label)) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="mt-5 flex justify-end"><button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><?= e(t('Save backup settings')) ?></button></div>
            </form>
            <div class="mt-4 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                <p class="font-semibold"><?= e(t('Run the scheduler hourly')) ?></p>
                <p class="mt-1"><?= e(t('The selected frequency determines when a new backup is due. Disabling automatic backup preserves existing files.')) ?></p>
                <code class="mt-3 block overflow-x-auto rounded-md bg-zinc-950 px-3 py-2 text-xs text-zinc-100">0 * * * * /usr/bin/php <?= e(escapeshellarg(BASE_PATH . '/database/backup-scheduler.php')) ?></code>
                <?php if ($settings['last_automatic_at'] !== null): ?><p class="mt-3 text-xs"><?= e(t('Last automatic backup')) ?>: <?= e(date('d M Y, H:i', strtotime($settings['last_automatic_at']))) ?></p><?php endif; ?>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
            <h2 class="text-lg font-semibold"><?= e(t('Backup contents and security')) ?></h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400"><?= e(t('Backups include customers, orders and order history, services, payments and refunds, users and permissions, audit logs, and related application tables.')) ?></p>
            <ul class="mt-4 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                <li>• <?= e(t('Compressed backup files are stored outside the public website folder.')) ?></li>
                <li>• <?= e(t('Files are restricted to the server account and Admin-only download routes.')) ?></li>
                <li>• <?= e(t('Restore saves a safety backup before replacing current data.')) ?></li>
                <li>• <?= e(t('Disabling automatic backups never removes existing backup files.')) ?></li>
            </ul>
        </section>
    </div>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div><h2 class="text-lg font-semibold"><?= e(t('Available backups')) ?></h2><p class="text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('{count} backup files', ['count' => count($backups)])) ?></p></div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400"><?= e(t('Storage')) ?>: <code>storage/backups/</code></p>
        </div>
        <?php if ($backups === []): ?>
            <p class="px-5 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('No backups found. Create a backup to get started.')) ?></p>
        <?php else: ?>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                <?php foreach ($backups as $backup): ?>
                    <article class="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-0">
                            <p class="break-all font-medium"><?= e($backup['name']) ?></p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><?= e(ucwords(str_replace('-', ' ', $backup['type']))) ?> · <?= e(date('d M Y, H:i:s', $backup['created_at'])) ?> · <?= e(number_format($backup['size'] / 1024, 1)) ?> KB</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="/backups/<?= rawurlencode($backup['name']) ?>/download" data-no-ajax class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold hover:border-primary hover:text-primary dark:border-zinc-700"><?= e(t('Download')) ?></a>
                            <form method="post" action="/backups/<?= rawurlencode($backup['name']) ?>/restore" data-confirm-title="<?= e(t('Confirm database restore')) ?>" data-confirm-accept-label="<?= e(t('Restore backup')) ?>" data-confirm="<?= e(t('Restore this backup? Current application data will be replaced. A safety backup will be created first.')) ?>" class="flex flex-wrap items-end gap-2">
                                <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300"><?= e(t('Type RESTORE to confirm')) ?>
                                    <input type="text" name="confirm_restore" required pattern="RESTORE" autocomplete="off" class="mt-1 block h-10 w-36 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900" placeholder="RESTORE">
                                </label>
                                <button type="submit" class="h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950"><?= e(t('Restore')) ?></button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
