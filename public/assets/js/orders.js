const receiptFormat = document.querySelector('[data-receipt-format]');

document.querySelectorAll('[data-print-document]').forEach((button) => {
  button.addEventListener('click', () => {
    document.body.dataset.printType = button.dataset.printDocument || 'ticket';
    document.body.dataset.printSize = receiptFormat?.value || '80';
    window.print();
  });
});

window.addEventListener('afterprint', () => {
  delete document.body.dataset.printType;
  delete document.body.dataset.printSize;
});

const printParams = new URLSearchParams(location.search);
if (['ticket', 'payment'].includes(printParams.get('print'))) {
  document.body.dataset.printType = printParams.get('print');
  document.body.dataset.printSize = ['58', 'a4'].includes(printParams.get('size')) ? printParams.get('size') : '80';
  window.addEventListener('load', () => window.setTimeout(() => window.print(), 150));
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
  const itemCountOutput = form.querySelector('[data-order-item-count]');
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
    rows.querySelectorAll('[data-order-item]').forEach((row) => {
      total += updateRow(row);
      if (row.querySelector('[data-service-select]')?.value) itemCount += 1;
    });
    totalOutput.textContent = money(total);
    if (subtotalOutput) subtotalOutput.textContent = money(total);
    if (itemCountOutput) itemCountOutput.textContent = String(itemCount);
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
    const wrapper = document.createElement('div');
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
  document.addEventListener('click', (event) => {
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
const orderStatusClasses = {
  received: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
  processing: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
  ready: 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300',
  collected: 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
  cancelled: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};
const orderStatusBaseClasses = 'rounded-lg border px-2 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary/30';

if (orderSearchForm && orderRows && orderCount && orderSearchStatus && orderPagination) {
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
      appendCell(row, `${Number(order.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} KHR`, 'whitespace-nowrap px-5 py-4 font-semibold');
      const paidAmount = Number(order.paid_amount || 0);
      const balanceAmount = Math.max(0, Number(order.total_amount || 0) - paidAmount);
      const formatMoney = (amount) => `${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} KHR`;
      appendCell(row, formatMoney(paidAmount), 'whitespace-nowrap px-5 py-4');
      const balanceCell = appendCell(row, '', 'whitespace-nowrap px-5 py-4');
      const balanceText = document.createElement('div');
      balanceText.className = 'font-semibold';
      balanceText.textContent = formatMoney(balanceAmount);
      const paymentBadge = document.createElement('span');
      paymentBadge.className = `mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${balanceAmount <= 0 ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' : paidAmount <= 0 ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'}`;
      paymentBadge.textContent = balanceAmount <= 0 ? 'Paid' : paidAmount <= 0 ? 'Unpaid' : 'Partial';
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
      label.textContent = `Status for ${order.order_code}`;
      const select = document.createElement('select');
      select.id = `status-${Number(order.id)}`;
      select.name = 'status';
      select.dataset.orderStatus = '';
      select.dataset.currentStatus = order.status;
      select.className = `${orderStatusBaseClasses} ${orderStatusClasses[order.status] || orderStatusClasses.received}`;
      ['received', 'processing', 'ready', 'collected', 'cancelled'].forEach((status) => {
        const option = new Option(status[0].toUpperCase() + status.slice(1), status, false, order.status === status);
        select.append(option);
      });
      select.disabled = ['collected', 'cancelled'].includes(order.status);
      form.append(csrf, returnTo, label, select);
      statusCell.append(form);
      row.append(statusCell);

      const actionCell = appendCell(row, '', 'px-5 py-4 text-right');
      const actions = document.createElement('div');
      actions.className = 'flex justify-end gap-2';
      const printLink = document.createElement('a');
      const canPrintPaymentReceipt = ['ready', 'collected'].includes(order.status) && Number(order.paid_amount || 0) > 0;
      printLink.href = `/orders/${Number(order.id)}?print=payment&size=80`;
      printLink.setAttribute('aria-label', `Print payment receipt for ${order.order_code}`);
      printLink.title = canPrintPaymentReceipt ? 'Print payment receipt' : Number(order.paid_amount || 0) <= 0 ? 'Available after a payment is recorded' : 'Available when order is ready';
      if (!canPrintPaymentReceipt) {
        printLink.setAttribute('aria-disabled', 'true');
        printLink.setAttribute('tabindex', '-1');
        printLink.addEventListener('click', (event) => event.preventDefault());
      }
      printLink.className = `inline-grid size-9 place-items-center rounded-lg border ${canPrintPaymentReceipt ? 'border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-300' : 'cursor-not-allowed border-zinc-200 text-zinc-300 opacity-50 dark:border-zinc-800 dark:text-zinc-600'}`;
      printLink.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/></svg>';
      const viewLink = document.createElement('a');
      viewLink.href = `/orders/${Number(order.id)}`;
      viewLink.setAttribute('aria-label', `View order ${order.order_code}`);
      viewLink.title = 'View order';
      viewLink.className = 'inline-grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-300';
      viewLink.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>';
      if (!['collected', 'cancelled'].includes(order.status)) {
        const editLink = document.createElement('a');
        editLink.href = `/orders/${Number(order.id)}/edit`;
        editLink.setAttribute('aria-label', `Edit order ${order.order_code}`);
        editLink.title = 'Edit order';
        editLink.className = 'inline-grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-300';
        editLink.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.25 5.25 4.5 4.5M4 20l4.2-.9L19 8.3a2.12 2.12 0 0 0-3-3L5.2 16.1 4 20Z"/></svg>';
        actions.append(editLink);
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
      cell.textContent = 'No orders found for these filters.';
      row.append(cell);
      orderRows.append(row);
    }
    const first = total === 0 ? 0 : ((page - 1) * 10 + 1);
    const last = Math.min(page * 10, total);
    orderCount.textContent = `Showing ${first}–${last} of ${total} orders.`;
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
    if (page > 1) addPageLink(page - 1, 'Previous');
    const pageLabel = document.createElement('span');
    pageLabel.className = 'px-2 text-xs text-zinc-500 dark:text-zinc-400';
    pageLabel.textContent = `Page ${page} of ${totalPages}`;
    orderPagination.append(pageLabel);
    if (page < totalPages) addPageLink(page + 1, 'Next');
  };

  const loadOrders = async (page = currentPage) => {
    pendingRequest?.abort();
    pendingRequest = new AbortController();
    const params = new URLSearchParams({ q: queryInput.value.trim(), status: statusInput.value, from: fromInput.value, to: toInput.value, page: String(page) });
    [...params.keys()].forEach((key) => { if (!params.get(key) || (key === 'page' && params.get(key) === '1')) params.delete(key); });
    history.replaceState(null, '', `/orders${params.size ? `?${params}` : ''}`);
    orderSearchStatus.textContent = 'Loading orders…';
    try {
      const response = await fetch(`/orders/search?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pendingRequest.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Could not load orders.');
      renderOrders(result.orders || [], result.page || page, result.total || 0, result.total_pages || 1);
      orderSearchStatus.textContent = `${result.orders?.length || 0} orders shown.`;
    } catch (error) {
      if (error.name === 'AbortError') return;
      orderSearchStatus.textContent = error.message || 'Could not load orders.';
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
  window.addEventListener('orders:refresh', () => loadOrders());
}

document.addEventListener('change', async (event) => {
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
      if (message) message.textContent = 'Order cancellation was dismissed.';
      return;
    }
    cancellationReason = reason;
  }
  const formData = new FormData(form);
  if (selectedStatus === 'cancelled') formData.set('reason', cancellationReason.trim());
  select.disabled = true;
  if (message) message.textContent = 'Updating order status…';
  try {
    const response = await fetch(form.action, { method: 'POST', body: formData, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Could not update order status.');
    select.dataset.currentStatus = result.status;
    select.value = result.status;
    select.className = `${orderStatusBaseClasses} ${orderStatusClasses[result.status] || orderStatusClasses.received}`;
    select.disabled = result.allowed_statuses.length === 0;
    if (message) message.textContent = `Order status changed to ${result.status_label}.`;
    if (result.status === 'cancelled') {
      const orderCode = form.closest('tr')?.querySelector('td:nth-child(2) a')?.textContent.trim();
      showOrderActionNotice(`${orderCode ? `${orderCode}: ` : ''}Order cancelled. The reason was saved in order history.`);
    }
    window.dispatchEvent(new Event('orders:refresh'));
  } catch (error) {
    select.value = previousStatus || selectedStatus;
    select.disabled = false;
    if (message) message.textContent = error.message || 'Could not update order status.';
    showOrderActionNotice(error.message || 'Could not update order status.', 'error');
  }
});
