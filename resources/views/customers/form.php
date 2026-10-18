<section class="w-full space-y-5">
    <div>
        <a href="/customers" class="text-sm font-medium text-primary hover:underline">← Back to customers</a>
        <p class="mt-4 text-sm font-semibold uppercase tracking-widest text-primary">Customer management</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl"><?= e($heading) ?></h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Enter the customer’s contact details. Name and phone are required.</p>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 sm:p-7 dark:bg-dark-card dark:ring-zinc-800">
        <?php if (isset($errors['form'])): ?>
            <p role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"><?= e($errors['form']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e($formAction) ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
            <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="mb-2 block text-sm font-medium">Customer name <span class="text-red-600">*</span></label>
                <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" placeholder="e.g. Sokha Chan" value="<?= e($customer['name'] ?? '') ?>"
                       class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900 <?= isset($errors['name']) ? 'border-red-500' : '' ?>">
                <?php if (isset($errors['name'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="phone" class="mb-2 block text-sm font-medium">Phone number <span class="text-red-600">*</span></label>
                <input id="phone" name="phone" type="tel" inputmode="tel" required maxlength="20" autocomplete="tel" placeholder="e.g. 012 345 678" value="<?= e($customer['phone'] ?? '') ?>"
                       class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900 <?= isset($errors['phone']) ? 'border-red-500' : '' ?>">
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Stored as text so leading zeroes are preserved.</p>
                <?php if (isset($errors['phone'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
            </div>
            <div>
                <label for="address" class="mb-2 block text-sm font-medium">Address <span class="text-zinc-400">(optional)</span></label>
                <textarea id="address" name="address" rows="2" maxlength="5000" autocomplete="street-address" placeholder="e.g. Street 123, Phnom Penh"
                          class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900 <?= isset($errors['address']) ? 'border-red-500' : '' ?>"><?= e($customer['address'] ?? '') ?></textarea>
                <?php if (isset($errors['address'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['address']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="notes" class="mb-2 block text-sm font-medium">Notes <span class="text-zinc-400">(optional)</span></label>
                <textarea id="notes" name="notes" rows="3" maxlength="5000" placeholder="Any helpful notes about this customer…"
                          class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-zinc-700 dark:bg-zinc-900 <?= isset($errors['notes']) ? 'border-red-500' : '' ?>"><?= e($customer['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['notes']) ?></p><?php endif; ?>
            </div>
            <div class="flex flex-col-reverse justify-end gap-3 border-t border-zinc-100 pt-5 sm:flex-row dark:border-zinc-800">
                <a href="/customers" class="rounded-lg px-4 py-2.5 text-center font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</a>
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"><?= $heading === 'Edit Customer' ? 'Save changes' : 'Save customer' ?></button>
            </div>
        </form>
    </div>
</section>
