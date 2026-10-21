<section class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-primary"><?= e(t('Customer management')) ?></p>
            <h1 class="mt-2 text-3xl font-bold"><?= e(t('Customers')) ?></h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300"><?= e(t('Search and update your customer contact details.')) ?></p>
        </div>
        <a href="/customers/create" class="inline-flex items-center rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            <span class="mr-2 text-lg" aria-hidden="true">+</span> <?= e(t('Add Customer')) ?>
        </a>
    </div>

    <?php if (is_array($flash) && in_array($flash['type'] ?? '', ['success', 'error'], true)): ?>
        <p role="status" class="rounded-lg border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' ?>">
            <?= e(t($flash['message'] ?? '')) ?>
        </p>
    <?php endif; ?>

    <form id="customer-search-form" method="get" action="/customers" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
        <label for="customer-search" class="mb-2 block text-sm font-medium"><?= e(t('Search customers')) ?></label>
        <input id="customer-search" name="q" type="search" value="<?= e($query) ?>" placeholder="<?= e(t('Search by name or phone…')) ?>" autocomplete="off" aria-describedby="customer-search-status"
               class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900">
    </form>
    <p id="customer-search-status" class="sr-only" aria-live="polite"></p>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-dark-card dark:ring-zinc-800">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr><th scope="col" class="px-5 py-3"><?= e(t('ID')) ?></th><th scope="col" class="px-5 py-3"><?= e(t('Name')) ?></th><th scope="col" class="px-5 py-3"><?= e(t('Phone')) ?></th><th scope="col" class="px-5 py-3"><?= e(t('Address')) ?></th><th scope="col" class="px-5 py-3"><?= e(t('Created at')) ?></th><th scope="col" class="px-5 py-3 text-right"><?= e(t('Action')) ?></th></tr>
                </thead>
                <tbody id="customer-rows" data-csrf-token="<?= e($csrfToken) ?>" data-i18n="<?= e(json_encode([
                    'noSearchResults' => t('No customers matched your search.'),
                    'noCustomers' => t('No customers yet. Add your first customer to get started.'),
                    'resultCountOne' => t('Showing {first}–{last} of {total} customer{matching}.'),
                    'resultCountMany' => t('Showing {first}–{last} of {total} customers{matching}.'),
                    'matching' => t(' matching your search'),
                    'found' => t('{total} customers found. Page {page} of {pages}.'),
                    'searching' => t('Searching customers…'),
                    'searchFailed' => t('Search failed. Please try again.'),
                    'loadFailed' => t('Could not load customer search results. Please try again.'),
                    'previous' => t('Previous'), 'next' => t('Next'),
                    'previousPage' => t('Previous page'), 'nextPage' => t('Next page'),
                    'view' => t('View customer'), 'edit' => t('Edit customer'), 'delete' => t('Delete customer'),
                    'deactivate' => t('Deactivate {name}? Existing records will be preserved.'),
                ], JSON_UNESCAPED_UNICODE)) ?>" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php foreach ($customers as $index => $customer): ?>
                        <tr>
                            <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400"><?= e(($page - 1) * $perPage + $index + 1) ?></td>
                            <td class="px-5 py-4"><span class="inline-flex max-w-full rounded-full bg-violet-100 px-3 py-1.5 font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200"><?= e($customer['name'] ?? '') ?></span></td>
                            <td class="px-5 py-4 whitespace-nowrap"><?= e($customer['phone'] ?? '') ?></td>
                            <td class="max-w-sm px-5 py-4"><span class="inline-block max-w-full truncate rounded-lg bg-sky-50 px-3 py-1.5 text-sky-900 dark:bg-sky-950/60 dark:text-sky-100" title="<?= e($customer['address'] ?: '—') ?>"><?= e($customer['address'] ?: '—') ?></span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-zinc-600 dark:text-zinc-300"><?= e(date('d M Y, H:i', strtotime((string) $customer['created_at']))) ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/customers/<?= (int) $customer['id'] ?>" aria-label="<?= e(t('View customer')) ?> <?= e($customer['name'] ?? t('customer')) ?>" title="<?= e(t('View customer')) ?>" class="grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    </a>
                                    <a href="/customers/<?= (int) $customer['id'] ?>/edit" aria-label="<?= e(t('Edit customer')) ?> <?= e($customer['name'] ?? t('customer')) ?>" title="<?= e(t('Edit customer')) ?>" class="grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14 5 5 5M4 20l4.5-1 11-11a2.12 2.12 0 0 0-3-3l-11 11L4 20Z"/></svg>
                                    </a>
                                    <form method="post" action="/customers/<?= (int) $customer['id'] ?>/delete" data-confirm="<?= e(strtr(t('Deactivate {name}? Existing records will be preserved.'), ['{name}' => (string) ($customer['name'] ?? t('customer'))])) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                                        <button type="submit" aria-label="<?= e(t('Delete customer')) ?> <?= e($customer['name'] ?? t('customer')) ?>" title="<?= e(t('Delete customer')) ?>" class="grid size-9 place-items-center rounded-lg border border-zinc-200 text-red-600 hover:border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-zinc-700 dark:hover:border-red-900 dark:hover:bg-red-950">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($customers === []): ?>
                        <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400"><?= e(t($query !== '' ? 'No customers matched your search.' : 'No customers yet. Add your first customer to get started.')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
            <?php $start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0; $end = min($page * $perPage, $total); ?>
            <p id="customer-result-count" class="text-xs text-zinc-500 dark:text-zinc-400" aria-live="polite">
                <?= e(strtr(t($total === 1 ? 'Showing {first}–{last} of {total} customer{matching}.' : 'Showing {first}–{last} of {total} customers{matching}.'), ['{first}' => (string) $start, '{last}' => (string) $end, '{total}' => (string) $total, '{matching}' => $query !== '' ? t(' matching your search') : ''])) ?>
            </p>
            <nav id="customer-pagination" aria-label="<?= e(t('Customer pages')) ?>" class="flex flex-wrap items-center gap-1">
                <?php $firstPage = max(1, $page - 2); $lastPage = min($totalPages, $page + 2); ?>
                <a data-customer-page="<?= e(max(1, $page - 1)) ?>" href="/customers?<?= e(http_build_query(['q' => $query, 'page' => max(1, $page - 1)])) ?>" aria-label="<?= e(t('Previous page')) ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?> class="rounded-md border border-zinc-200 px-3 py-1.5 text-sm <?= $page <= 1 ? 'pointer-events-none opacity-50' : 'hover:border-primary hover:text-primary' ?> dark:border-zinc-700"><?= e(t('Previous')) ?></a>
                <?php if ($firstPage > 1): ?><a data-customer-page="1" href="/customers?<?= e(http_build_query(['q' => $query, 'page' => 1])) ?>" class="rounded-md border border-zinc-200 px-3 py-1.5 text-sm hover:border-primary hover:text-primary dark:border-zinc-700">1</a><?php if ($firstPage > 2): ?><span class="px-1 text-zinc-500" aria-hidden="true">…</span><?php endif; ?><?php endif; ?>
                <?php for ($pageNumber = $firstPage; $pageNumber <= $lastPage; $pageNumber++): ?>
                    <a data-customer-page="<?= e($pageNumber) ?>" href="/customers?<?= e(http_build_query(['q' => $query, 'page' => $pageNumber])) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?> class="rounded-md border px-3 py-1.5 text-sm <?= $pageNumber === $page ? 'border-primary bg-violet-50 font-semibold text-primary dark:bg-violet-950/50' : 'border-zinc-200 hover:border-primary hover:text-primary dark:border-zinc-700' ?>"><?= e($pageNumber) ?></a>
                <?php endfor; ?>
                <?php if ($lastPage < $totalPages): ?><?php if ($lastPage < $totalPages - 1): ?><span class="px-1 text-zinc-500" aria-hidden="true">…</span><?php endif; ?><a data-customer-page="<?= e($totalPages) ?>" href="/customers?<?= e(http_build_query(['q' => $query, 'page' => $totalPages])) ?>" class="rounded-md border border-zinc-200 px-3 py-1.5 text-sm hover:border-primary hover:text-primary dark:border-zinc-700"><?= e($totalPages) ?></a><?php endif; ?>
                <a data-customer-page="<?= e(min($totalPages, $page + 1)) ?>" href="/customers?<?= e(http_build_query(['q' => $query, 'page' => min($totalPages, $page + 1)])) ?>" aria-label="<?= e(t('Next page')) ?>" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?> class="rounded-md border border-zinc-200 px-3 py-1.5 text-sm <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : 'hover:border-primary hover:text-primary' ?> dark:border-zinc-700"><?= e(t('Next')) ?></a>
            </nav>
        </div>
    </div>
</section>
