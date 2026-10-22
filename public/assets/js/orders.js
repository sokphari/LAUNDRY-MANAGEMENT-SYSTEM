const initializeOrdersPage = () => {
const mainContent = document.querySelector('main');
const receiptFormat = document.querySelector('[data-receipt-format]');
const appT = (message, values = {}) => window.appTranslate(message, values);

document.querySelectorAll('[data-print-document]').forEach((button) => {
  button.addEventListener('click', () => {
    document.body.dataset.printType = button.dataset.printDocument || 'ticket';
    document.body.dataset.printSize = receiptFormat?.value || '80';
    window.print();
  });
});

if (!window.__ordersAfterPrintBound) {
  window.__ordersAfterPrintBound = true;
  window.addEventListener('afterprint', () => {
  delete document.body.dataset.printType;
  delete document.body.dataset.printSize;
  });
}

const printParams = new URLSearchParams(location.search);
if (['ticket', 'payment'].includes(printParams.get('print'))) {
  document.body.dataset.printType = printParams.get('print');
  document.body.dataset.printSize = ['58', 'a4'].includes(printParams.get('size')) ? printParams.get('size') : '80';
    const printDocument = () => window.setTimeout(() => window.print(), 150);
    if (document.readyState === 'complete') printDocument();
    else window.addEventListener('load', printDocument, { once: true });
}

const cancelOrderDialog = document.querySelector('#cancel-order-dialog');
const cancelOrderForm = cancelOrderDialog?.querySelector('[data-cancel-order-form]');
const cancelOrderReason = cancelOrderDialog?.querySelector('[data-cancel-order-reason]');
const cancelOrderError = cancelOrderDialog?.querySelector('[data-cancel-order-error]');

cancelOrderForm?.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!cancelOrderReason?.value.trim()) {
    cancelOrderError?.classList.remove('hidden');
    cancelOrderReason?.focus();
    return;
  }
  cancelOrderError?.classList.add('hidden');
  cancelOrderDialog?.close('confirm');
});

cancelOrderDialog?.querySelector('[data-cancel-order-dismiss]')?.addEventListener('click', () => cancelOrderDialog.close('cancel'));
cancelOrderDialog?.addEventListener('click', (event) => {
  if (event.target === cancelOrderDialog) cancelOrderDialog.close('cancel');
});

const requestCancellationReason = () => {
  if (!cancelOrderDialog || !cancelOrderReason) return Promise.resolve(null);
  cancelOrderReason.value = '';
  cancelOrderError?.classList.add('hidden');
  cancelOrderDialog.returnValue = '';
  const result = new Promise((resolve) => {
    cancelOrderDialog.addEventListener('close', () => {
      resolve(cancelOrderDialog.returnValue === 'confirm' ? cancelOrderReason.value.trim() : null);
    }, { once: true });
  });
  cancelOrderDialog.showModal();
  cancelOrderReason.focus();
  return result;
};

document.querySelectorAll('[data-order-form]').forEach((form) => {
  const rows = form.querySelector('[data-order-items]');
  const template = form.querySelector('#order-item-template');
  const addButton = form.querySelector('[data-add-order-item]');
  const totalOutput = form.querySelector('[data-order-total]');
  const subtotalOutput = form.querySelector('[data-order-subtotal]');
  const itemCountOutput = form.querySelector('[data-order-item-count-value]');
  const summaryItems = form.querySelector('[data-order-summary-items]');
  const balanceOutput = form.querySelector('[data-order-balance]');
  const depositInput = form.querySelector('[data-order-deposit]');
  if (!rows || !template || !addButton || !totalOutput) return;

  const maxItems = 3;
  let nextIndex = rows.querySelectorAll('[data-order-item]').length;
  const money = (value) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value)} KHR`;

  const updateRow = (row) => {
    const select = row.querySelector('[data-service-select]');
    const quantity = row.querySelector('[data-order-quantity]');
    const unitOutput = row.querySelector('[data-order-unit]');
    const unitPriceOutput = row.querySelector('[data-order-unit-price]');
    const lineOutput = row.querySelector('[data-order-line-total]');
    const option = select?.selectedOptions[0];
    const unit = option?.dataset.unit || '';
    const price = Number(option?.dataset.price || 0);
    if (unitOutput) unitOutput.textContent = unit || '—';
    if (unitPriceOutput) unitPriceOutput.textContent = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(price);
    if (quantity) {
      quantity.step = unit === 'piece' ? '1' : '0.001';
      quantity.min = unit === 'piece' ? '1' : '0.001';
      quantity.placeholder = unit === 'piece' ? 'e.g. 2' : 'e.g. 2.5';
      if (unit === 'piece' && quantity.value.includes('.')) quantity.value = '';
    }
    const amount = price * Number(quantity?.value || 0);
    if (lineOutput) lineOutput.textContent = money(amount);
    return amount;
  };

  const updateTotal = () => {
    let total = 0;
    let itemCount = 0;
    const selectedItems = [];
    rows.querySelectorAll('[data-order-item]').forEach((row, index) => {
      const rowNumber = row.querySelector('[data-order-row-number]');
      if (rowNumber) rowNumber.textContent = String(index + 1);
      total += updateRow(row);
      const select = row.querySelector('[data-service-select]');
      if (select?.value) {
        itemCount += 1;
        const option = select.selectedOptions[0];
        const quantity = row.querySelector('[data-order-quantity]');
        const unit = option?.dataset.unit || '';
        const unitPrice = Number(option?.dataset.price || 0);
        const serviceName = (option?.textContent || '').split(' · ')[0].trim();
        selectedItems.push({ serviceName, quantity: Number(quantity?.value || 0), unit, unitPrice, total: unitPrice * Number(quantity?.value || 0) });
      }
    });
    totalOutput.textContent = money(total);
    if (subtotalOutput) subtotalOutput.textContent = money(total);
    if (itemCountOutput) itemCountOutput.textContent = String(itemCount);
    if (summaryItems) {
      summaryItems.replaceChildren();
      if (selectedItems.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'rounded-lg bg-zinc-50 px-3 py-4 text-center text-sm text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400';
        empty.textContent = 'No services selected yet.';
        summaryItems.append(empty);
      } else {
        selectedItems.forEach((item) => {
          const line = document.createElement('div');
          line.className = 'flex items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900';
          const detail = document.createElement('div');
          detail.className = 'flex min-w-0 items-center gap-3';
          const icon = document.createElement('span');
          icon.className = 'grid size-9 shrink-0 place-items-center rounded-lg bg-violet-100 text-primary dark:bg-violet-950 dark:text-white';
          icon.setAttribute('aria-hidden', 'true');
          icon.textContent = '◈';
          const description = document.createElement('div');
          description.className = 'min-w-0';
          const name = document.createElement('p');
          name.className = 'truncate text-sm font-medium';
          name.textContent = item.serviceName || 'Service';
          const quantity = document.createElement('p');
          quantity.className = 'text-xs text-zinc-500 dark:text-zinc-400';
          const quantityText = item.quantity.toLocaleString('en-US', { maximumFractionDigits: 3 });
          const priceText = item.unitPrice.toLocaleString('en-US', { maximumFractionDigits: 2 });
          quantity.textContent = `${quantityText} ${item.unit} × ${priceText} KHR`;
          description.append(name, quantity);
          detail.append(icon, description);
          const amount = document.createElement('strong');
          amount.className = 'shrink-0 text-right text-sm font-semibold';
          amount.textContent = money(item.total);
          line.append(detail, amount);
          summaryItems.append(line);
        });
      }
    }
    if (depositInput) depositInput.max = total.toFixed(2);
    if (balanceOutput) balanceOutput.textContent = money(Math.max(0, total - Number(depositInput?.value || 0)));
    const atLimit = rows.querySelectorAll('[data-order-item]').length >= maxItems;
    addButton.disabled = atLimit;
    addButton.setAttribute('aria-disabled', String(atLimit));
    addButton.classList.toggle('cursor-not-allowed', atLimit);
    addButton.classList.toggle('opacity-50', atLimit);
  };

  const addRow = () => {
    if (rows.querySelectorAll('[data-order-item]').length >= maxItems) return;
    const markup = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
    const wrapper = document.createElement('tbody');
    wrapper.innerHTML = markup;
    const row = wrapper.firstElementChild;
    if (row) rows.append(row);
    updateTotal();
  };

  addButton.addEventListener('click', addRow);
  const customerPicker = form.querySelector('[data-customer-picker]');
  const customerValue = customerPicker?.querySelector('[data-customer-value]');
  const customerTrigger = customerPicker?.querySelector('[data-customer-picker-trigger]');
  const customerMenu = customerPicker?.querySelector('[data-customer-picker-menu]');
  const customerSearch = customerPicker?.querySelector('[data-customer-search]');
  const customerOptions = [...(customerPicker?.querySelectorAll('[data-customer-option]') || [])];
  const customerEmpty = customerPicker?.querySelector('[data-customer-empty]');
  const customerLabel = customerPicker?.querySelector('[data-customer-selected-label]');
  const customerClientError = customerPicker?.querySelector('[data-customer-client-error]');
  const closeCustomerMenu = () => {
    customerMenu?.classList.add('hidden');
    customerTrigger?.setAttribute('aria-expanded', 'false');
  };
  const openCustomerMenu = () => {
    customerMenu?.classList.remove('hidden');
    customerTrigger?.setAttribute('aria-expanded', 'true');
    if (customerSearch) {
      customerSearch.value = '';
      customerSearch.dispatchEvent(new Event('input'));
      customerSearch.focus();
    }
  };
  customerTrigger?.addEventListener('click', () => {
    if (customerMenu?.classList.contains('hidden')) openCustomerMenu();
    else closeCustomerMenu();
  });
  customerSearch?.addEventListener('input', () => {
    const query = customerSearch.value.trim().toLocaleLowerCase();
    let visibleCount = 0;
    customerOptions.forEach((option) => {
      const matches = option.dataset.search.includes(query);
      option.classList.toggle('hidden', !matches);
      if (matches) visibleCount += 1;
    });
    customerEmpty?.classList.toggle('hidden', visibleCount !== 0);
  });
  customerOptions.forEach((option) => option.addEventListener('click', () => {
    if (customerValue) customerValue.value = option.dataset.id || '';
    if (customerLabel) {
      customerLabel.textContent = option.dataset.label || '';
      customerLabel.classList.remove('text-zinc-500', 'dark:text-zinc-400');
      customerLabel.classList.add('text-zinc-900', 'dark:text-white');
    }
    customerOptions.forEach((item) => item.setAttribute('aria-selected', item === option ? 'true' : 'false'));
    customerClientError?.classList.add('hidden');
    customerTrigger?.removeAttribute('aria-invalid');
    closeCustomerMenu();
  }));
  customerSearch?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeCustomerMenu();
      customerTrigger?.focus();
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      customerOptions.find((option) => !option.classList.contains('hidden'))?.click();
    }
  });
  mainContent?.addEventListener('click', (event) => {
    if (customerPicker && !customerPicker.contains(event.target)) closeCustomerMenu();
  });
  form.addEventListener('submit', (event) => {
    if (customerValue?.value) return;
    event.preventDefault();
    customerClientError?.classList.remove('hidden');
    customerTrigger?.setAttribute('aria-invalid', 'true');
    openCustomerMenu();
  });
  rows.addEventListener('change', (event) => {
    if (event.target.matches('[data-service-select]')) updateTotal();
  });
  rows.addEventListener('input', (event) => {
    if (event.target.matches('[data-order-quantity]')) updateTotal();
  });
  depositInput?.addEventListener('input', updateTotal);
  rows.addEventListener('click', (event) => {
    const stepButton = event.target.closest('[data-quantity-step]');
    if (stepButton) {
      const row = stepButton.closest('[data-order-item]');
      const quantity = row?.querySelector('[data-order-quantity]');
      if (!quantity) return;
      const direction = Number(stepButton.dataset.quantityStep) < 0 ? -1 : 1;
      const step = Number(quantity.step) || 1;
      const minimum = Number(quantity.min) || step;
      const current = quantity.value === '' ? 0 : Number(quantity.value);
      const next = Math.max(minimum, Math.round((current + direction * step) * 1000) / 1000);
      quantity.value = String(next);
      quantity.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    const remove = event.target.closest('[data-remove-order-item]');
    if (!remove) return;
    const itemRows = rows.querySelectorAll('[data-order-item]');
    if (itemRows.length > 1) remove.closest('[data-order-item]')?.remove();
    updateTotal();
  });

  updateTotal();
});

const orderSearchForm = document.querySelector('#order-search-form');
const orderRows = document.querySelector('#order-rows');
const orderCount = document.querySelector('#order-result-count');
const orderPagination = document.querySelector('#order-pagination');
const orderSearchStatus = document.querySelector('#order-search-status');
const orderActionNotice = document.querySelector('#order-action-notice');
let orderActionNoticeTimer;

const showOrderActionNotice = (text, type = 'success') => {
  if (!orderActionNotice) return;
  orderActionNotice.textContent = text;
  orderActionNotice.className = type === 'error'
    ? 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
    : 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200';
  clearTimeout(orderActionNoticeTimer);
  orderActionNoticeTimer = setTimeout(() => orderActionNotice.classList.add('hidden'), 5000);
};
const orderStatusIconPaths = {
  received: '<path d="M12 3 4.5 7.5 12 12l7.5-4.5L12 3Z"/><path d="M4.5 7.5v9L12 21l7.5-4.5v-9L12 12v9"/>',
  processing: '<circle cx="12" cy="12" r="8.5" stroke-dasharray="40 16"/>',
  ready: '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.3 2.3 4.8-4.8"/>',
  collected: '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m8.5 12.5 2.2 2.2 4.8-4.8"/>',
  cancelled: '<circle cx="12" cy="12" r="8.5"/><path d="m9 9 6 6m0-6-6 6"/>',
};
const updateOrderStatusControl = (select, status) => {
  const control = select.closest('[data-order-status-control]');
  if (!control) return;
  control.dataset.status = status;
  const icon = control.querySelector('[data-order-status-icon]');
  if (icon) icon.innerHTML = orderStatusIconPaths[status] || orderStatusIconPaths.received;
};
const orderNextStatuses = {
  received: ['processing', 'cancelled'],
  processing: ['ready', 'cancelled'],
  ready: ['collected', 'cancelled'],
  collected: [],
  cancelled: [],
};
if (orderSearchForm && orderRows && orderCount && orderSearchStatus && orderPagination) {
  const t = appT;
  const queryInput = orderSearchForm.querySelector('[name="q"]');
  const statusInput = orderSearchForm.querySelector('[name="status"]');
  const fromInput = orderSearchForm.querySelector('[name="from"]');
  const toInput = orderSearchForm.querySelector('[name="to"]');
  let pendingRequest;
  let searchTimer;
  let currentPage = Math.max(1, Number(new URLSearchParams(location.search).get('page')) || 1);

  const appendCell = (row, content, className, html = false) => {
    const cell = document.createElement('td');
    cell.className = className;
    if (html) cell.innerHTML = content;
    else cell.textContent = content;
    row.append(cell);
    return cell;
  };

  const renderOrders = (orders, page, total, totalPages) => {
    orderRows.replaceChildren();
    orders.forEach((order, index) => {
      const row = document.createElement('tr');
      appendCell(row, String((page - 1) * 10 + index + 1), 'px-5 py-4 text-zinc-500 dark:text-zinc-400');
      const orderCell = appendCell(row, '', 'px-5 py-4 font-semibold');
      const orderLink = document.createElement('a');
      orderLink.className = 'hover:text-primary';
      orderLink.href = `/orders/${Number(order.id)}`;
      orderLink.textContent = order.order_code;
      orderCell.append(orderLink);

      const customerCell = document.createElement('td');
      customerCell.className = 'px-5 py-4';
      const customerName = document.createElement('p');
      customerName.className = 'font-medium';
      customerName.textContent = order.customer_name;
      const customerPhone = document.createElement('p');
      customerPhone.className = 'text-xs text-zinc-500 dark:text-zinc-400';
      customerPhone.textContent = order.customer_phone;
      customerCell.append(customerName, customerPhone);
      row.append(customerCell);
      appendCell(row, order.received_display, 'whitespace-nowrap px-5 py-4');
      appendCell(row, order.pickup_display, 'whitespace-nowrap px-5 py-4');
      appendCell(row, `${Number(order.total_amount).toLocaleString('en-US', { maximumFractionDigits: 2 })} KHR`, 'whitespace-nowrap px-5 py-4 font-semibold');
      const paidAmount = Number(order.paid_amount || 0);
      const balanceAmount = Math.max(0, Number(order.total_amount || 0) - paidAmount);
      const formatMoney = (amount) => `${amount.toLocaleString('en-US', { maximumFractionDigits: 2 })} KHR`;
      appendCell(row, formatMoney(paidAmount), 'whitespace-nowrap px-5 py-4');
      const balanceCell = appendCell(row, '', 'whitespace-nowrap px-5 py-4');
      const balanceText = document.createElement('div');
      balanceText.className = 'font-semibold';
      balanceText.textContent = formatMoney(balanceAmount);
      const paymentBadge = document.createElement('span');
      paymentBadge.className = `mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${balanceAmount <= 0 ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' : paidAmount <= 0 ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'}`;
      paymentBadge.textContent = t(balanceAmount <= 0 ? 'Paid' : paidAmount <= 0 ? 'Unpaid' : 'Partial');
      balanceCell.append(balanceText, paymentBadge);

      const statusCell = document.createElement('td');
      statusCell.className = 'px-5 py-4';
      const form = document.createElement('form');
      form.method = 'post';
      form.action = `/orders/${Number(order.id)}/status`;
      form.dataset.orderStatusForm = '';
      form.className = 'w-fit';
      const csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = '_csrf_token';
      csrf.value = orderRows.dataset.csrfToken || '';
      const returnTo = document.createElement('input');
      returnTo.type = 'hidden';
      returnTo.name = '_return_to';
      returnTo.value = `${location.pathname}${location.search}`;
      const label = document.createElement('label');
      label.className = 'sr-only';
      label.htmlFor = `status-${Number(order.id)}`;
      label.textContent = t('Status for {order}', { order: order.order_code });
      const statusControl = document.createElement('span');
      statusControl.className = 'order-status-control';
      statusControl.dataset.orderStatusControl = '';
      statusControl.dataset.status = order.status;
      const statusIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      statusIcon.classList.add('order-status-icon');
      statusIcon.setAttribute('viewBox', '0 0 24 24');
      statusIcon.setAttribute('fill', 'none');
      statusIcon.setAttribute('stroke', 'currentColor');
      statusIcon.setAttribute('stroke-width', '1.8');
      statusIcon.setAttribute('stroke-linecap', 'round');
      statusIcon.setAttribute('stroke-linejoin', 'round');
      statusIcon.setAttribute('aria-hidden', 'true');
      statusIcon.dataset.orderStatusIcon = '';
      statusIcon.innerHTML = orderStatusIconPaths[order.status] || orderStatusIconPaths.received;
      const select = document.createElement('select');
      select.id = `status-${Number(order.id)}`;
      select.name = 'status';
      select.dataset.orderStatus = '';
      select.dataset.currentStatus = order.status;
      select.className = 'order-status-select';
      [order.status, ...(orderNextStatuses[order.status] || [])].forEach((status) => {
        const option = new Option(t(status[0].toUpperCase() + status.slice(1)), status, false, order.status === status);
        select.append(option);
      });
      select.disabled = ['collected', 'cancelled'].includes(order.status);
      const statusChevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      statusChevron.classList.add('order-status-chevron');
      statusChevron.setAttribute('viewBox', '0 0 20 20');
      statusChevron.setAttribute('fill', 'currentColor');
      statusChevron.setAttribute('aria-hidden', 'true');
      statusChevron.innerHTML = '<path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>';
      statusControl.append(statusIcon, select, statusChevron);
      form.append(csrf, returnTo, label, statusControl);
      statusCell.append(form);
      row.append(statusCell);

      const actionCell = appendCell(row, '', 'px-5 py-4 text-right');
      const actions = document.createElement('div');
      actions.className = 'flex justify-end gap-2';
      const printLink = document.createElement('a');
      const canPrintPaymentReceipt = ['ready', 'collected'].includes(order.status) && Number(order.paid_amount || 0) > 0;
      printLink.href = `/orders/${Number(order.id)}?print=payment&size=80`;
      printLink.setAttribute('aria-label', t('Print payment receipt for {order}', { order: order.order_code }));
      printLink.title = t(canPrintPaymentReceipt ? 'Print payment receipt' : Number(order.paid_amount || 0) <= 0 ? 'Available after a payment is recorded' : 'Available when order is ready');
      if (!canPrintPaymentReceipt) {
        printLink.setAttribute('aria-disabled', 'true');
        printLink.setAttribute('tabindex', '-1');
        printLink.addEventListener('click', (event) => event.preventDefault());
      }
      printLink.className = `inline-grid size-9 place-items-center rounded-lg border ${canPrintPaymentReceipt ? 'border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-300' : 'cursor-not-allowed border-zinc-200 text-zinc-300 opacity-50 dark:border-zinc-800 dark:text-zinc-600'}`;
      printLink.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/></svg>';
      const viewLink = document.createElement('a');
      viewLink.href = `/orders/${Number(order.id)}`;
      viewLink.setAttribute('aria-label', t('View order {order}', { order: order.order_code }));
      viewLink.title = t('View order');
      viewLink.className = 'inline-grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-300';
      viewLink.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>';
      if (!['collected', 'cancelled'].includes(order.status)) {
        const editLink = document.createElement('a');
        editLink.href = `/orders/${Number(order.id)}/edit`;
        editLink.setAttribute('aria-label', t('Edit order {order}', { order: order.order_code }));
        editLink.title = t('Edit order');
        editLink.className = 'inline-grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-300';
        editLink.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.25 5.25 4.5 4.5M4 20l4.2-.9L19 8.3a2.12 2.12 0 0 0-3-3L5.2 16.1 4 20Z"/></svg>';
        actions.append(editLink);
      }
      if (balanceAmount > 0 && order.status !== 'cancelled') {
        const paymentButton = document.createElement('button');
        paymentButton.type = 'button';
        paymentButton.dataset.recordPayment = '';
        paymentButton.dataset.orderId = String(Number(order.id));
        paymentButton.dataset.orderCode = order.order_code;
        paymentButton.dataset.balance = balanceAmount.toFixed(2);
        paymentButton.setAttribute('aria-label', t('Record payment for {order}', { order: order.order_code }));
        paymentButton.title = t('Record payment');
        paymentButton.className = 'inline-grid size-9 place-items-center rounded-lg border border-emerald-200 text-emerald-700 hover:border-emerald-500 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950';
        paymentButton.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18m-13 5h3"/></svg>';
        actions.append(paymentButton);
      }
      actions.append(printLink, viewLink);
      actionCell.append(actions);
      row.append(actionCell);
      orderRows.append(row);
    });
    if (orders.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 10;
      cell.className = 'px-5 py-12 text-center text-zinc-500 dark:text-zinc-400';
      cell.textContent = t('No orders found for these filters.');
      row.append(cell);
      orderRows.append(row);
    }
    const first = total === 0 ? 0 : ((page - 1) * 10 + 1);
    const last = Math.min(page * 10, total);
    orderCount.textContent = t('Showing {first}–{last} of {total} orders.', { first, last, total });
    currentPage = page;
    orderPagination.replaceChildren();
    const addPageLink = (targetPage, label) => {
      const link = document.createElement('a');
      const pageParams = new URLSearchParams({ q: queryInput.value.trim(), status: statusInput.value, from: fromInput.value, to: toInput.value, page: String(targetPage) });
      [...pageParams.keys()].forEach((key) => { if (!pageParams.get(key) || (key === 'page' && pageParams.get(key) === '1')) pageParams.delete(key); });
      link.href = `/orders${pageParams.size ? `?${pageParams}` : ''}`;
      link.dataset.orderPage = String(targetPage);
      link.className = 'rounded-md px-3 py-1.5 text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800';
      link.textContent = label;
      orderPagination.append(link);
    };
    if (page > 1) addPageLink(page - 1, t('Previous'));
    const pageLabel = document.createElement('span');
    pageLabel.className = 'px-2 text-xs text-zinc-500 dark:text-zinc-400';
    pageLabel.textContent = t('Page {page} of {pages}', { page, pages: totalPages });
    orderPagination.append(pageLabel);
    if (page < totalPages) addPageLink(page + 1, t('Next'));
  };

  const loadOrders = async (page = currentPage) => {
    pendingRequest?.abort();
    pendingRequest = new AbortController();
    const params = new URLSearchParams({ q: queryInput.value.trim(), status: statusInput.value, from: fromInput.value, to: toInput.value, page: String(page) });
    [...params.keys()].forEach((key) => { if (!params.get(key) || (key === 'page' && params.get(key) === '1')) params.delete(key); });
    history.replaceState(null, '', `/orders${params.size ? `?${params}` : ''}`);
    orderSearchStatus.textContent = t('Loading orders…');
    try {
      const response = await fetch(`/orders/search?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pendingRequest.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || t('Could not load orders.'));
      renderOrders(result.orders || [], result.page || page, result.total || 0, result.total_pages || 1);
      orderSearchStatus.textContent = t('{count} orders shown.', { count: result.orders?.length || 0 });
    } catch (error) {
      if (error.name === 'AbortError') return;
      orderSearchStatus.textContent = error.message || t('Could not load orders.');
    }
  };

  orderSearchForm.addEventListener('submit', (event) => { event.preventDefault(); loadOrders(1); });
  queryInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadOrders(1), 250);
  });
  [statusInput, fromInput, toInput].forEach((input) => input.addEventListener('change', () => loadOrders(1)));
  orderPagination.addEventListener('click', (event) => {
    const link = event.target.closest('[data-order-page]');
    if (!link) return;
    event.preventDefault();
    loadOrders(Number(link.dataset.orderPage) || 1);
  });
  mainContent?.addEventListener('orders:refresh', () => loadOrders());
}

mainContent?.addEventListener('change', async (event) => {
  const select = event.target.closest('[data-order-status]');
  const form = select?.closest('[data-order-status-form]');
  if (!select || !form) return;
  const message = document.querySelector('#order-search-status');
  const previousStatus = select.dataset.currentStatus;
  const selectedStatus = select.value;
  let cancellationReason = '';
  if (selectedStatus === 'cancelled') {
    const reason = await requestCancellationReason();
    if (!reason) {
      select.value = previousStatus || '';
      if (message) message.textContent = appT('Order cancellation was dismissed.');
      return;
    }
    cancellationReason = reason;
  }
  const formData = new FormData(form);
  if (selectedStatus === 'cancelled') formData.set('reason', cancellationReason.trim());
  select.disabled = true;
  if (message) message.textContent = appT('Updating order status…');
  try {
    const response = await fetch(form.action, { method: 'POST', body: formData, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Could not update order status.');
    select.dataset.currentStatus = result.status;
    select.replaceChildren(...[result.status, ...result.allowed_statuses].map((status) => new Option(status[0].toUpperCase() + status.slice(1), status, false, status === result.status)));
    select.value = result.status;
      updateOrderStatusControl(select, result.status);
    select.disabled = result.allowed_statuses.length === 0;
    if (message) message.textContent = appT('Order status changed to {status}.', { status: appT(result.status_label) });
    if (result.status === 'cancelled') {
      const orderCode = form.closest('tr')?.querySelector('td:nth-child(2) a')?.textContent.trim();
      showOrderActionNotice(`${orderCode ? `${orderCode}: ` : ''}Order cancelled. The reason was saved in order history.`);
    }
    mainContent?.dispatchEvent(new Event('orders:refresh')); 
  } catch (error) {
    select.value = previousStatus || selectedStatus;
    select.disabled = false;
    if (message) message.textContent = error.message || appT('Could not update order status.');
    showOrderActionNotice(error.message || 'Could not update order status.', 'error');
  }
});

mainContent?.addEventListener('click', (event) => {
  const paymentButton = event.target.closest('[data-record-payment]');
  if (paymentButton) {
    const dialog = document.querySelector('#record-payment-dialog');
    const form = dialog?.querySelector('[data-record-payment-form]');
    const amountInput = dialog?.querySelector('[data-payment-amount]');
    if (!dialog || !form || !amountInput || typeof dialog.showModal !== 'function') return;
    form.action = `/orders/${Number(paymentButton.dataset.orderId)}/payments`;
    amountInput.max = paymentButton.dataset.balance;
    amountInput.value = '';
    dialog.querySelector('[data-payment-order-code]').textContent = paymentButton.dataset.orderCode || '';
    dialog.querySelector('[data-payment-balance]').textContent = `${Number(paymentButton.dataset.balance).toLocaleString('en-US', { maximumFractionDigits: 2 })} KHR`;
    form.querySelector('[name="_return_to"]').value = `${location.pathname}${location.search}`;
    dialog.showModal();
    amountInput.focus();
    return;
  }

  const paymentCloseButton = event.target.closest('[data-payment-dialog-close]');
  if (paymentCloseButton) {
    paymentCloseButton.closest('dialog')?.close();
    return;
  }

  const openButton = event.target.closest('[data-refund-dialog-open]');
  if (openButton) {
    const dialog = document.getElementById(openButton.dataset.refundDialogOpen);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    dialog.showModal();
    dialog.querySelector('[name="amount"]')?.focus();
    return;
  }

  const closeButton = event.target.closest('[data-refund-dialog-close]');
  if (closeButton) closeButton.closest('dialog')?.close();
});
};
document.addEventListener('app:page-ready', initializeOrdersPage);
