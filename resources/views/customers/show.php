<section class="w-full space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="/customers" class="text-sm font-medium text-primary hover:underline">← Back to customers</a>
            <p class="mt-4 text-sm font-semibold uppercase tracking-widest text-primary">Customer management</p>
            <h2 class="mt-1 text-2xl font-bold sm:text-3xl">Customer details</h2>
        </div>
        <a href="/customers/<?= (int) $customer['id'] ?>/edit" class="rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover">Edit customer</a>
    </div>

    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 sm:p-7 dark:bg-dark-card dark:ring-zinc-800">
        <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Name</dt><dd class="mt-1 text-lg font-semibold"><?= e($customer['name'] ?? '') ?></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Phone</dt><dd class="mt-1 text-lg font-semibold"><?= e($customer['phone'] ?? '') ?></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Address</dt><dd class="mt-1 whitespace-pre-wrap"><?= e($customer['address'] ?: '—') ?></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Notes</dt><dd class="mt-1 whitespace-pre-wrap"><?= e($customer['notes'] ?: '—') ?></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Status</dt><dd class="mt-1"><?= (int) ($customer['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></dd></div>
        </dl>
    </article>
</section>
