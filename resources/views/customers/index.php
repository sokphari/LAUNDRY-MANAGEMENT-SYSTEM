<section class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">Customer management</p>
            <h1 class="mt-2 text-3xl font-bold">Customers</h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300">Search and update your customer contact details.</p>
        </div>
        <a href="/customers/create" class="inline-flex items-center rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            <span class="mr-2 text-lg" aria-hidden="true">+</span> Add Customer
        </a>
    </div>

    <?php if (is_array($flash) && in_array($flash['type'] ?? '', ['success', 'error'], true)): ?>
        <p role="status" class="rounded-lg border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' ?>">
            <?= e($flash['message'] ?? '') ?>
        </p>
    <?php endif; ?>

    <form id="customer-search-form" method="get" action="/customers" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
        <label for="customer-search" class="mb-2 block text-sm font-medium">Search customers</label>
        <input id="customer-search" name="q" type="search" value="<?= e($query) ?>" placeholder="Search by name or phone…" autocomplete="off" aria-describedby="customer-search-status"
               class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900">
    </form>
    <p id="customer-search-status" class="sr-only" aria-live="polite"></p>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr><th scope="col" class="px-5 py-3">ID</th><th scope="col" class="px-5 py-3">Name</th><th scope="col" class="px-5 py-3">Phone</th><th scope="col" class="px-5 py-3">Address</th><th scope="col" class="px-5 py-3">Created at</th><th scope="col" class="px-5 py-3 text-right">Action</th></tr>
                </thead>
                <tbody id="customer-rows" data-csrf-token="<?= e($csrfToken) ?>" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php foreach ($customers as $index => $customer): ?>
                        <tr>
                            <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400"><?= e($index + 1) ?></td>
                            <td class="px-5 py-4 font-medium"><?= e($customer['name'] ?? '') ?></td>
                            <td class="px-5 py-4 whitespace-nowrap"><?= e($customer['phone'] ?? '') ?></td>
                            <td class="max-w-sm truncate px-5 py-4 text-zinc-600 dark:text-zinc-300"><?= e($customer['address'] ?: '—') ?></td>
                            <td class="whitespace-nowrap px-5 py-4 text-zinc-600 dark:text-zinc-300"><?= e(date('d M Y, H:i', strtotime((string) $customer['created_at']))) ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/customers/<?= (int) $customer['id'] ?>" aria-label="View <?= e($customer['name'] ?? 'customer') ?>" title="View customer" class="grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    </a>
                                    <a href="/customers/<?= (int) $customer['id'] ?>/edit" aria-label="Edit <?= e($customer['name'] ?? 'customer') ?>" title="Edit customer" class="grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 5 5 5M4 20l4.5-1 11-11a2.12 2.12 0 0 0-3-3l-11 11L4 20Z"/></svg>
                                    </a>
                                    <form method="post" action="/customers/<?= (int) $customer['id'] ?>/delete" data-confirm="Deactivate <?= e($customer['name'] ?? 'this customer') ?>? Existing records will be preserved.">
                                        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                                        <button type="submit" aria-label="Delete <?= e($customer['name'] ?? 'customer') ?>" title="Delete customer" class="grid size-9 place-items-center rounded-lg border border-zinc-200 text-red-600 hover:border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-zinc-700 dark:hover:border-red-900 dark:hover:bg-red-950">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($customers === []): ?>
                        <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400"><?= $query !== '' ? 'No customers matched your search.' : 'No customers yet. Add your first customer to get started.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="customer-result-count" class="border-t border-zinc-100 px-5 py-3 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            Showing <?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?><?= $query !== '' ? ' matching your search' : '' ?>.
        </div>
    </div>
</section>
