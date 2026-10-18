<?php $isEdit = $isEdit ?? false; $paidAmount = $paidAmount ?? 0.0; ?>
<section class="w-full space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <a href="/orders" class="text-sm font-medium text-primary hover:underline">← Back to orders</a>
            <p class="mt-4 text-sm font-semibold uppercase tracking-widest text-primary">Order management</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl"><?= $isEdit ? 'Edit Order' : 'Create Order' ?></h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300"><?= $isEdit ? 'Update order details. Existing payments stay in the payment history.' : 'Create a new laundry order.' ?></p>
        </div>
        <span class="hidden size-11 place-items-center rounded-xl bg-violet-100 text-primary sm:grid dark:bg-violet-950" aria-hidden="true">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="2"/><path stroke-linecap="round" d="M9 3.5h6M8 10h8M8 14h8M8 18h4"/></svg>
        </span>
    </div>

    <?php if ($customers === []): ?><div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Add an active customer before creating an order. <a href="/customers/create" class="font-semibold underline">Create customer</a></div><?php endif; ?>
    <?php if ($services === []): ?><div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Add an active service before creating an order. <a href="/services/create" class="font-semibold underline">Create service</a></div><?php endif; ?>

    <?php if (isset($errors['form'])): ?><p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"><?= e($errors['form']) ?></p><?php endif; ?>
    <form method="post" action="<?= $isEdit ? '/orders/' . (int) $orderId . '/edit' : '/orders/create' ?>" data-order-form class="grid gap-4 lg:grid-cols-[minmax(0,1.7fr)_minmax(300px,0.9fr)]">
        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
        <div class="space-y-4">
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 dark:bg-dark-card dark:ring-zinc-800">
                <h2 class="flex items-center gap-2 font-semibold"><svg class="size-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 20a7 7 0 0 1 14 0"/></svg>Customer information</h2>
                <?php $selectedCustomer = null; foreach ($customers as $customer) { if ((string) ($orderInput['customer_id'] ?? '') === (string) $customer['id']) { $selectedCustomer = $customer; break; } } ?>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start" data-customer-field>
                <div class="relative min-w-0 flex-1" data-customer-picker>
                    <input type="hidden" id="customer_id" name="customer_id" data-customer-value value="<?= $selectedCustomer ? (int) $selectedCustomer['id'] : '' ?>">
                    <button type="button" data-customer-picker-trigger aria-haspopup="listbox" aria-expanded="false" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-left text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900">
                        <span data-customer-selected-label class="truncate <?= $selectedCustomer ? 'text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400' ?>"><?= $selectedCustomer ? e($selectedCustomer['name'] . ' · ' . $selectedCustomer['phone']) : 'Choose a customer' ?></span>
                        <svg class="size-4 shrink-0 text-zinc-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div data-customer-picker-menu class="absolute inset-x-0 top-full z-20 mt-1 hidden overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 p-2 dark:border-zinc-700"><label for="customer-search" class="sr-only">Search customers</label><input id="customer-search" type="search" data-customer-search placeholder="Search by name or phone…" autocomplete="off" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-dark-base"></div>
                        <div data-customer-options role="listbox" aria-label="Customers" class="max-h-52 overflow-y-auto p-1">
                            <?php foreach ($customers as $customer): ?><button type="button" role="option" aria-selected="<?= (string) ($orderInput['customer_id'] ?? '') === (string) $customer['id'] ? 'true' : 'false' ?>" data-customer-option data-id="<?= (int) $customer['id'] ?>" data-search="<?= e(mb_strtolower($customer['name'] . ' ' . $customer['phone'])) ?>" data-label="<?= e($customer['name'] . ' · ' . $customer['phone']) ?>" class="flex w-full flex-col rounded-md px-3 py-2 text-left hover:bg-violet-50 focus:bg-violet-50 focus:outline-none dark:hover:bg-violet-950 dark:focus:bg-violet-950"><span class="text-sm font-medium"><?= e($customer['name']) ?></span><span class="text-xs text-zinc-500 dark:text-zinc-400"><?= e($customer['phone']) ?></span></button><?php endforeach; ?>
                            <p data-customer-empty class="hidden px-3 py-3 text-sm text-zinc-500">No matching customers.</p>
                        </div>
                    </div>
                    <?php if (isset($errors['customer_id'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['customer_id']) ?></p><?php endif; ?>
                    <p data-customer-client-error class="mt-1 hidden text-sm text-red-600">Choose a customer to continue.</p>
                </div>
                <a href="/customers/create" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-violet-100 px-4 py-2.5 text-sm font-semibold text-black dark:text-white hover:bg-violet-200 dark:bg-violet-950 dark:hover:bg-violet-900">+ New Customer</a>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div><label for="received_at" class="mb-1.5 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Received</label><input id="received_at" name="received_at" type="date" required value="<?= e($orderInput['received_at'] ?? '') ?>" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"><?php if (isset($errors['received_at'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['received_at']) ?></p><?php endif; ?></div>
                    <div><label for="expected_pickup_at" class="mb-1.5 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Pickup</label><input id="expected_pickup_at" name="expected_pickup_at" type="date" required value="<?= e($orderInput['expected_pickup_at'] ?? '') ?>" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"><?php if (isset($errors['expected_pickup_at'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['expected_pickup_at']) ?></p><?php endif; ?></div>
                </div>
            </article>

            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 dark:bg-dark-card dark:ring-zinc-800">
                <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Laundry services</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Prices are saved with the order. Up to 3 services.</p></div><button type="button" data-add-order-item class="rounded-lg px-3 py-2 text-sm font-semibold text-primary hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-violet-950">+ Add</button></div>
                <?php if (isset($errors['items'])): ?><p role="alert" class="mt-3 text-sm text-red-600"><?= e($errors['items']) ?></p><?php endif; ?>
                <div data-order-items class="mt-3 space-y-2.5">
                    <?php foreach (($orderInput['items'] ?? [['service_id' => '', 'quantity' => '']]) as $index => $item): ?>
                        <div class="grid gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-[minmax(0,1fr)_120px_minmax(90px,auto)_40px] sm:items-end dark:border-zinc-800" data-order-item>
                            <div><label class="sr-only" for="service-<?= (int) $index ?>">Service</label><select id="service-<?= (int) $index ?>" name="items[<?= (int) $index ?>][service_id]" data-service-select required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="">Choose service</option><?php foreach ($services as $service): ?><?php $selectedItemService = (string) ($item['service_id'] ?? '') === (string) $service['id']; $shownPrice = $selectedItemService && isset($item['unit_price']) ? $item['unit_price'] : $service['price']; ?><?php if ((int) $service['is_active'] === 1 || $selectedItemService): ?><option value="<?= (int) $service['id'] ?>" data-price="<?= e((string) $shownPrice) ?>" data-unit="<?= e($service['billing_unit']) ?>" <?= $selectedItemService ? 'selected' : '' ?>><?= e($service['name']) ?><?= (int) $service['is_active'] === 1 ? '' : ' · inactive' ?> · <?= e($service['billing_unit']) ?> · <?= e(number_format((float) $shownPrice, 0)) ?> KHR</option><?php endif; ?><?php endforeach; ?></select><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><span data-order-unit>—</span> × <span data-order-unit-price>0</span> KHR</p></div>
                            <div><label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400" for="quantity-<?= (int) $index ?>">Quantity</label><input id="quantity-<?= (int) $index ?>" name="items[<?= (int) $index ?>][quantity]" data-order-quantity type="number" min="0.001" step="0.001" placeholder="Enter qty" required value="<?= e($item['quantity'] ?? '') ?>" class="quantity-input w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900"></div>
                            <span data-order-line-total class="pb-2 text-left text-sm font-semibold text-primary sm:text-right">0 KHR</span>
                            <button type="button" data-remove-order-item aria-label="Remove service" title="Remove service" class="grid size-9 place-items-center rounded-lg text-zinc-500 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500/30 dark:text-zinc-400 dark:hover:bg-red-950"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template id="order-item-template"><div class="grid gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-[minmax(0,1fr)_120px_minmax(90px,auto)_40px] sm:items-end dark:border-zinc-800" data-order-item>
                    <div><label class="sr-only" for="service-__INDEX__">Service</label><select id="service-__INDEX__" name="items[__INDEX__][service_id]" data-service-select required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="">Choose service</option><?php foreach ($services as $service): ?><?php if ((int) $service['is_active'] === 1): ?><option value="<?= (int) $service['id'] ?>" data-price="<?= e($service['price']) ?>" data-unit="<?= e($service['billing_unit']) ?>"><?= e($service['name']) ?> · <?= e($service['billing_unit']) ?> · <?= e(number_format((float) $service['price'], 0)) ?> KHR</option><?php endif; ?><?php endforeach; ?></select><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><span data-order-unit>—</span> × <span data-order-unit-price>0</span> KHR</p></div>
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400" for="quantity-__INDEX__">Quantity</label><input id="quantity-__INDEX__" name="items[__INDEX__][quantity]" data-order-quantity type="number" min="0.001" step="0.001" placeholder="Enter qty" required class="quantity-input w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900"></div>
                    <span data-order-line-total class="pb-2 text-left text-sm font-semibold text-primary sm:text-right">0 KHR</span>
                    <button type="button" data-remove-order-item aria-label="Remove service" title="Remove service" class="grid size-9 place-items-center rounded-lg text-zinc-500 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500/30 dark:text-zinc-400 dark:hover:bg-red-950"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg></button>
                </div></template>
            </article>

            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 dark:bg-dark-card dark:ring-zinc-800">
                <label for="notes" class="block font-semibold">Order notes <span class="font-normal text-zinc-400">(optional)</span></label>
                <textarea id="notes" name="notes" rows="2" maxlength="5000" placeholder="Special instructions…" class="mt-3 w-full resize-y rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"><?= e($orderInput['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['notes']) ?></p><?php endif; ?>
            </article>
        </div>

        <aside class="flex h-full flex-col rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 sm:p-5 lg:sticky lg:top-24 dark:bg-dark-card dark:ring-zinc-800">
            <h2 class="flex items-center gap-3 text-lg font-semibold"><svg class="size-5 text-violet-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h12a1.5 1.5 0 0 1 1.5 1.5v15l-3.75-2.25L12 20.25l-3.75-2.0L4.5 20.25v-15a1.5 1.5 0 0 1 1.5-1.5Z"/><path stroke-linecap="round" d="M9 8h6M9 12h6"/></svg>Order Summary</h2>
            <dl class="mt-7 space-y-5 text-sm">
                <div class="flex justify-between gap-4 text-zinc-500 dark:text-zinc-400"><dt>Items</dt><dd data-order-item-count class="font-medium text-zinc-900 dark:text-white">0</dd></div>
                <div class="flex justify-between gap-4 text-zinc-500 dark:text-zinc-400"><dt>Subtotal</dt><dd data-order-subtotal class="font-medium text-zinc-900 dark:text-white">0 KHR</dd></div>
                <div class="flex justify-between gap-4 text-zinc-500 dark:text-zinc-400"><dt>Discount</dt><dd class="font-medium text-zinc-900 dark:text-white">0 KHR</dd></div>
            </dl>
            <div class="my-6 border-t border-zinc-200 dark:border-zinc-700"></div>
            <div><span class="text-sm text-zinc-500 dark:text-zinc-400">Grand Total</span><strong data-order-total class="mt-2 block text-3xl font-bold tracking-tight text-primary">0 KHR</strong></div>
            <div class="my-6 border-t border-zinc-200 dark:border-zinc-700"></div>
            <section aria-label="Payment summary">
                <h3 class="font-semibold">Payment</h3>
                <?php if ($isEdit): ?>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Recorded payments are preserved when you save changes.</p>
                    <input type="hidden" data-order-deposit value="<?= e(number_format($paidAmount, 2, '.', '')) ?>">
                    <dl class="mt-5 space-y-5 text-sm"><div class="flex justify-between gap-4 text-zinc-500 dark:text-zinc-400"><dt>Paid so far</dt><dd class="font-medium text-zinc-900 dark:text-white"><?= e(number_format($paidAmount, 2)) ?> KHR</dd></div><div class="flex justify-between gap-4 text-zinc-500 dark:text-zinc-400"><dt>Balance after changes</dt><dd data-order-balance class="text-base font-semibold text-zinc-900 dark:text-white">0 KHR</dd></div></dl>
                <?php else: ?>
                <label for="payment_method" class="sr-only">Payment method</label><select id="payment_method" name="payment_method" required class="mt-3 w-full rounded-lg border border-zinc-300 bg-zinc-100 px-3 py-3 text-sm text-zinc-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-zinc-800 dark:bg-dark-base dark:text-zinc-200"><option value="cash" <?= ($orderInput['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash</option><option value="khqr" <?= ($orderInput['payment_method'] ?? '') === 'khqr' ? 'selected' : '' ?>>KHQR</option></select>
                <?php if (isset($errors['payment_method'])): ?><p role="alert" class="mt-1 text-xs text-red-600"><?= e($errors['payment_method']) ?></p><?php endif; ?>
                <dl class="mt-5 space-y-5 text-sm">
                    <div class="flex items-center justify-between gap-3 text-zinc-500 dark:text-zinc-400"><dt><label for="deposit">Deposit</label></dt><dd><div class="flex items-center gap-2"><input id="deposit" name="deposit" data-order-deposit type="number" min="0" max="9999999999.99" step="0.01" value="<?= e($orderInput['deposit'] ?? '0') ?>" class="w-32 rounded-lg border border-zinc-300 bg-white px-2.5 py-2 text-right text-sm text-zinc-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><span class="text-xs">KHR</span></div></dd></div>
                    <div class="flex justify-between gap-4 text-zinc-500 dark:text-zinc-400"><dt>Balance</dt><dd data-order-balance class="text-base font-semibold text-zinc-900 dark:text-white">0 KHR</dd></div>
                </dl>
                <?php if (isset($errors['deposit'])): ?><p role="alert" class="mt-2 text-xs text-red-600"><?= e($errors['deposit']) ?></p><?php endif; ?>
                <?php endif; ?>
            </section>
            <div class="mt-auto pt-7"><button type="submit" <?= $customers === [] || $services === [] ? 'disabled' : '' ?> class="w-full rounded-lg bg-primary px-4 py-4 text-base font-semibold text-white transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"><?= $isEdit ? 'Save Changes' : 'Create Order' ?></button><p class="mt-4 text-center text-sm text-zinc-500 dark:text-zinc-400"><?= $isEdit ? 'Review the updated order before saving' : 'Review before confirming' ?></p></div>
        </aside>
    </form>
</section>
