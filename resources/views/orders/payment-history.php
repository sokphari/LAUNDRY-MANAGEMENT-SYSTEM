<?php if ($payments !== []): ?>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                <tr>
                    <th scope="col" class="border-b border-zinc-100 py-2 pr-4 dark:border-zinc-800">Date</th>
                    <th scope="col" class="border-b border-zinc-100 py-2 pr-4 dark:border-zinc-800">Method</th>
                    <th scope="col" class="border-b border-zinc-100 py-2 pr-4 dark:border-zinc-800">Received by</th>
                    <th scope="col" class="border-b border-zinc-100 py-2 pr-4 text-right dark:border-zinc-800">Payment</th>
                    <th scope="col" class="border-b border-zinc-100 py-2 pr-4 text-right dark:border-zinc-800">Refunded</th>
                    <th scope="col" class="border-b border-zinc-100 py-2 text-right dark:border-zinc-800">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <?php $paymentRefunded = (float) ($payment['refunded_amount'] ?? 0); $remainingRefundable = max(0, round((float) $payment['amount'] - $paymentRefunded, 2)); $corrected = !empty($payment['voided_at']); ?>
                    <tr class="<?= $corrected ? 'text-zinc-400' : '' ?>">
                        <td class="border-b border-zinc-100 py-3 pr-4 align-top dark:border-zinc-800">
                            <?= e(date('d M Y, H:i', strtotime((string) $payment['paid_at']))) ?>
                            <?php if ($corrected): ?><span class="ml-2 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">Corrected</span><p class="mt-1 text-xs">On <?= e(date('d M Y, H:i', strtotime((string) $payment['voided_at']))) ?></p><?php endif; ?>
                        </td>
                        <td class="border-b border-zinc-100 py-3 pr-4 align-top dark:border-zinc-800">
                            <?= e(strtoupper((string) $payment['method']) === 'KHQR' ? 'KHQR' : 'Cash') ?>
                            <?php if (!empty($payment['replacement_payment_id'])): ?><p class="mt-1 text-xs">Replaced by payment #<?= (int) $payment['replacement_payment_id'] ?></p><?php endif; ?>
                            <?php if (!empty($payment['void_reason'])): ?><p class="mt-1 text-xs">Correction reason: <?= e($payment['void_reason']) ?></p><?php endif; ?>
                        </td>
                        <td class="border-b border-zinc-100 py-3 pr-4 align-top dark:border-zinc-800">
                            <?= e($payment['username']) ?>
                            <?php if (!empty($payment['voided_by_username'])): ?><p class="mt-1 text-xs">Corrected by <?= e($payment['voided_by_username']) ?></p><?php endif; ?>
                        </td>
                        <td class="border-b border-zinc-100 py-3 pr-4 text-right align-top font-medium dark:border-zinc-800"><?= e(number_format((float) $payment['amount'], 2)) ?> KHR</td>
                        <td class="border-b border-zinc-100 py-3 pr-4 text-right align-top dark:border-zinc-800">
                            <?= e(number_format($paymentRefunded, 2)) ?> KHR
                            <?php if ($paymentRefunded > 0): ?><span class="mt-1 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-300"><?= $remainingRefundable > 0 ? 'Partial refund' : 'Fully refunded' ?></span><?php endif; ?>
                            <?php if ($payment['refunds'] !== []): ?>
                                <details class="mt-1 text-left">
                                    <summary class="cursor-pointer text-xs font-medium text-primary">Refund history (<?= count($payment['refunds']) ?>)</summary>
                                    <ul class="mt-2 space-y-2 rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-900">
                                        <?php foreach ($payment['refunds'] as $refund): ?>
                                            <li class="border-b border-zinc-200 pb-2 last:border-0 last:pb-0 dark:border-zinc-700">
                                                <p class="font-semibold"><?= e(number_format((float) $refund['amount'], 2)) ?> KHR · <?= e(strtoupper((string) $refund['refund_method']) === 'KHQR' ? 'KHQR' : 'Cash') ?> · <?= e(date('d M Y, H:i', strtotime((string) $refund['refunded_at']))) ?></p>
                                                <p>By <?= e($refund['username']) ?> · <?= e($refund['reason']) ?></p>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="border-b border-zinc-100 py-3 text-right align-top dark:border-zinc-800">
                            <?php if (!$corrected && $remainingRefundable > 0): ?>
                                <button type="button" data-refund-dialog-open="refund-dialog-<?= (int) $payment['id'] ?>" class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900">Refund payment</button>
                                <dialog id="refund-dialog-<?= (int) $payment['id'] ?>" aria-labelledby="refund-dialog-title-<?= (int) $payment['id'] ?>" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-2xl backdrop:bg-black/60 dark:border-zinc-700 dark:bg-dark-card dark:text-white">
                                    <form method="post" action="/orders/<?= (int) $order['id'] ?>/payments/<?= (int) $payment['id'] ?>/refund" class="space-y-4 p-5 text-left">
                                        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                                        <div>
                                            <h2 id="refund-dialog-title-<?= (int) $payment['id'] ?>" class="text-lg font-semibold">Refund payment</h2>
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Up to <?= e(number_format($remainingRefundable, 2)) ?> KHR remains refundable.</p>
                                        </div>
                                        <label class="block text-sm font-medium">Refund amount (KHR)<input name="amount" type="number" min="0.01" max="<?= e(number_format($remainingRefundable, 2, '.', '')) ?>" step="0.01" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"></label>
                                        <label class="block text-sm font-medium">Refund method<select name="method" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"><option value="cash" <?= $payment['method'] === 'cash' ? 'selected' : '' ?>>Cash</option><option value="khqr" <?= $payment['method'] === 'khqr' ? 'selected' : '' ?>>KHQR</option></select></label>
                                        <label class="block text-sm font-medium">Reason<textarea name="reason" maxlength="500" required rows="3" placeholder="Why is this money being refunded?" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"></textarea></label>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" data-refund-dialog-close class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Cancel</button>
                                            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900">Record refund</button>
                                        </div>
                                    </form>
                                </dialog>
                            <?php elseif (!$corrected && $paymentRefunded > 0): ?>
                                <span class="inline-flex rounded-full bg-red-50 px-2 py-1 text-[10px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">Fully refunded</span>
                            <?php endif; ?>
                            <?php if (!$corrected && $paymentRefunded <= 0 && $canCorrectPayments): ?>
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer rounded-md px-2 py-1 text-xs font-semibold text-primary hover:bg-violet-50 dark:hover:bg-violet-950">Correct</summary>
                                    <form method="post" action="/orders/<?= (int) $order['id'] ?>/payments/<?= (int) $payment['id'] ?>/correct" class="mt-2 w-64 space-y-2 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                                        <label class="block text-xs font-medium">Reason<input name="reason" maxlength="500" required placeholder="e.g. Incorrect amount entered" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-2.5 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-950"></label>
                                        <label class="block text-xs font-medium">Correct amount (optional)<input name="replacement_amount" type="number" min="0.01" step="0.01" placeholder="Leave blank to void only" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-2.5 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-950"></label>
                                        <label class="block text-xs font-medium">Method<select name="replacement_method" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-2.5 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-950"><option value="cash">Cash</option><option value="khqr">KHQR</option></select></label>
                                        <button type="submit" class="w-full rounded-md bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-hover">Void and correct payment</button>
                                    </form>
                                </details>
                            <?php elseif (!$corrected && $paymentRefunded > 0): ?>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Refunded payments cannot be corrected.</p>
                            <?php elseif ($corrected): ?>
                                <span class="text-xs text-zinc-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="mt-5 text-sm text-zinc-500 dark:text-zinc-400">No payments recorded yet.</p>
<?php endif; ?>
