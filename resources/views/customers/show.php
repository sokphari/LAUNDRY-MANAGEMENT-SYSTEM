<section class="w-full space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="/customers" class="text-sm font-medium text-primary hover:underline">← <?= e(t('Back to customers')) ?></a>
            <p class="mt-4 text-sm font-semibold uppercase tracking-widest text-primary"><?= e(t('Customer management')) ?></p>
            <h2 class="mt-1 text-2xl font-bold sm:text-3xl"><?= e(t('Customer details')) ?></h2>
        </div>
        <a href="/customers/<?= (int) $customer['id'] ?>/edit" class="rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover"><?= e(t('Edit customer')) ?></a>
    </div>

    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 sm:p-7 dark:bg-dark-card dark:ring-zinc-800">
        <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Name')) ?></dt><dd class="mt-2"><span class="inline-flex max-w-full rounded-full bg-violet-100 px-3 py-1.5 text-base font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200"><?= e($customer['name'] ?? '') ?></span></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Phone')) ?></dt><dd class="mt-1 text-lg font-semibold"><?= e($customer['phone'] ?? '') ?></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Address')) ?></dt><dd class="mt-2"><span class="inline-flex max-w-full whitespace-pre-wrap rounded-lg bg-sky-50 px-3 py-2 text-sky-900 dark:bg-sky-950/60 dark:text-sky-100"><?= e($customer['address'] ?: '—') ?></span></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Notes')) ?></dt><dd class="mt-1 whitespace-pre-wrap"><?= e($customer['notes'] ?: '—') ?></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400"><?= e(t('Status')) ?></dt><dd class="mt-1"><?= e(t((int) ($customer['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive')) ?></dd></div>
        </dl>
    </article>
</section>
