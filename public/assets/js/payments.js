const initializePaymentsPage = () => {
const paymentsForm = document.querySelector('#payments-search-form');
const paymentRows = document.querySelector('#payment-transaction-rows');
const paymentResultCount = document.querySelector('#payments-result-count');
const paymentPagination = document.querySelector('#payments-pagination');
const paymentSearchStatus = document.querySelector('#payments-search-status');
  const exportLinks = [...document.querySelectorAll('[data-payments-export]')];

if (paymentsForm && paymentRows && paymentResultCount && paymentPagination && paymentSearchStatus) {
  const t = (message, values = {}) => window.appTranslate(message, values);
  const queryInput = paymentsForm.querySelector('[name="q"]');
  const typeInput = paymentsForm.querySelector('[name="type"]');
  const statusInput = paymentsForm.querySelector('[name="status"]');
  const methodInput = paymentsForm.querySelector('[name="method"]');
  const staffInput = paymentsForm.querySelector('[name="staff"]');
  const rangeInput = paymentsForm.querySelector('[name="range"]');
  const customRangeFields = [...paymentsForm.querySelectorAll('[data-payments-custom-range]')];
  const fromInput = paymentsForm.querySelector('[name="from"]');
  const toInput = paymentsForm.querySelector('[name="to"]');
  const totalIn = document.querySelector('#payments-total-in');
  const totalRefunds = document.querySelector('#payments-total-refunds');
  const totalPending = document.querySelector('#payments-total-pending');
  const totalNet = document.querySelector('#payments-total-net');
  const reconciliationCells = {};
  ['cash', 'khqr', 'total'].forEach((method) => {
    reconciliationCells[method] = {
      payments: document.querySelector(`#recon-${method}-payments`),
      refunds: document.querySelector(`#recon-${method}-refunds`),
      net: document.querySelector(`#recon-${method}-net`),
    };
  });
  let currentPage = Math.max(1, Number(new URLSearchParams(location.search).get('page')) || 1);
  let pendingRequest;
  let searchTimer;

  const formatMoney = (amount) => `${Number(amount || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })} KHR`;
  const buildParams = (page) => {
    const params = new URLSearchParams({ q: queryInput.value.trim(), page: String(page) });
    [typeInput, statusInput, methodInput, staffInput, rangeInput, fromInput, toInput].forEach((input) => {
      if (input.value && (!['from', 'to'].includes(input.name) || rangeInput.value === 'custom')) params.set(input.name, input.value);
    });
    return params;
  };
  const updateExportLinks = () => {
    const params = buildParams(1);
    params.delete('page');
    exportLinks.forEach((link) => {
      params.set('format', link.dataset.paymentsExport || 'pdf');
      link.href = `/payments/export?${params}`;
    });
  };
  const toggleCustomDates = () => customRangeFields.forEach((field) => field.classList.toggle('hidden', rangeInput.value !== 'custom'));
  const makeCell = (row, className = '') => {
    const cell = document.createElement('td');
    cell.className = className;
    row.append(cell);
    return cell;
  };

  const renderTransactions = (transactions) => {
    paymentRows.replaceChildren();
    transactions.forEach((transaction) => {
      const isRefund = transaction.transaction_type === 'refund';
      const isPending = transaction.transaction_type === 'pending';
      const amount = Number(transaction.amount || 0);
      const status = String(transaction.transaction_status || 'recorded');
      const row = document.createElement('tr');

      const dateCell = makeCell(row, 'whitespace-nowrap px-5 py-4 text-zinc-600 dark:text-zinc-300');
      dateCell.textContent = transaction.occurred_display || '';

      const typeCell = makeCell(row, 'px-5 py-4');
      const typeBadge = document.createElement('span');
      typeBadge.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${isPending ? 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950 dark:text-yellow-200' : isRefund ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'}`;
      typeBadge.textContent = t(isPending ? 'Pending balance' : isRefund ? 'Refund' : 'Payment');
      const reference = document.createElement('p');
      reference.className = 'mt-1 text-xs text-zinc-500 dark:text-zinc-400';
      reference.textContent = isPending ? t('Outstanding order balance') : isRefund ? `${t('Refund')} #${transaction.transaction_id} · ${t('Payment')} #${transaction.payment_id}` : `${t('Payment')} #${transaction.payment_id}`;
      typeCell.append(typeBadge, reference);

      const orderCell = makeCell(row, 'px-5 py-4');
      const orderLink = document.createElement('a');
      orderLink.href = `/orders/${Number(transaction.order_id)}`;
      orderLink.className = 'font-semibold text-primary hover:underline';
      orderLink.textContent = transaction.order_code || '';
      const customer = document.createElement('p');
      customer.className = 'mt-1 font-medium';
      customer.textContent = transaction.customer_name || '';
      const phone = document.createElement('p');
      phone.className = 'text-xs text-zinc-500 dark:text-zinc-400';
      phone.textContent = transaction.customer_phone || '';
      orderCell.append(orderLink, customer, phone);

      const methodCell = makeCell(row, 'px-5 py-4');
      methodCell.textContent = isPending ? '—' : String(transaction.method).toUpperCase() === 'KHQR' ? 'KHQR' : t('Cash');

      const amountCell = makeCell(row, `whitespace-nowrap px-5 py-4 text-right font-semibold ${isRefund ? 'text-red-700 dark:text-red-300' : ''}`);
      amountCell.textContent = `${isRefund ? '−' : ''}${formatMoney(amount)}`;

      const statusCell = makeCell(row, 'max-w-xs px-5 py-4');
      const statusText = document.createElement('span');
      statusText.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${status === 'pending' ? 'bg-yellow-500 text-black' : status === 'recorded' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
      statusText.textContent = t(status[0].toUpperCase() + status.slice(1));
      statusCell.append(statusText);
      const reasonText = transaction.reason || transaction.void_reason;
      if (reasonText) {
        const reason = document.createElement('p');
        reason.className = 'mt-1 break-words text-xs text-zinc-500 dark:text-zinc-400';
        reason.textContent = reasonText;
        statusCell.append(reason);
      }

      const staffCell = makeCell(row, 'px-5 py-4');
      staffCell.textContent = transaction.username || '';

      const actionCell = makeCell(row, 'px-5 py-4');
      const actions = document.createElement('div');
      actions.className = 'flex justify-end gap-2';
      const orderId = Number(transaction.order_id);
      const paymentId = Number(transaction.payment_id || 0);
      const isPayment = transaction.transaction_type === 'payment' && paymentId > 0;
      const viewLink = document.createElement('a');
      const returnUrl = `${location.pathname}${location.search}`;
      viewLink.href = `/orders/${orderId}${isPayment ? `?payment_id=${paymentId}&return=${encodeURIComponent(returnUrl)}` : ''}`;
      viewLink.title = t(isPayment ? 'View payment details' : 'View order');
      viewLink.className = 'rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:border-primary hover:text-primary dark:border-zinc-700 dark:text-zinc-200';
      viewLink.textContent = t('View');
      actions.append(viewLink);
      if (isPayment && status === 'recorded') {
        const printLink = document.createElement('a');
        printLink.href = `/orders/${orderId}?print=payment&payment_id=${paymentId}&size=80`;
        printLink.title = t('Print receipt for payment #{payment}', { payment: paymentId });
        printLink.className = 'rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-hover';
        printLink.textContent = t('Print Receipt');
        printLink.className = 'whitespace-nowrap rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-hover';
        actions.append(printLink);
      } else if (isPayment) {
        const correctedLabel = document.createElement('span');
        correctedLabel.className = 'px-2 py-1.5 text-xs text-zinc-400';
        correctedLabel.title = t('Corrected payments cannot be printed');
        correctedLabel.textContent = '—';
        actions.append(correctedLabel);
      }
      actionCell.append(actions);
      paymentRows.append(row);
    });

    if (transactions.length === 0) {
      const row = document.createElement('tr');
      const cell = makeCell(row, 'px-5 py-12 text-center text-zinc-500 dark:text-zinc-400');
      cell.colSpan = 8;
      cell.textContent = t('No payment transactions found.');
      paymentRows.append(row);
    }
  };

  const renderPagination = (page, totalPages) => {
    paymentPagination.replaceChildren();
    const addLink = (nextPage, label) => {
      const link = document.createElement('a');
      const params = buildParams(nextPage);
      [...params.keys()].forEach((key) => { if (!params.get(key) || (key === 'page' && params.get(key) === '1')) params.delete(key); });
      link.href = `/payments${params.size ? `?${params}` : ''}`;
      link.dataset.paymentPage = String(nextPage);
      link.className = 'rounded-md px-3 py-1.5 text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800';
      link.textContent = label;
      paymentPagination.append(link);
    };
    if (page > 1) addLink(page - 1, t('Previous'));
    const label = document.createElement('span');
    label.className = 'px-2 text-xs text-zinc-500 dark:text-zinc-400';
    label.textContent = t('Page {page} of {pages}', { page, pages: totalPages });
    paymentPagination.append(label);
    if (page < totalPages) addLink(page + 1, t('Next'));
  };

  const loadTransactions = async (page = currentPage) => {
    pendingRequest?.abort();
    pendingRequest = new AbortController();
    const params = buildParams(page);
    [...params.keys()].forEach((key) => { if (!params.get(key) || (key === 'page' && params.get(key) === '1')) params.delete(key); });
    history.replaceState(null, '', `/payments${params.size ? `?${params}` : ''}`);
    updateExportLinks();
    paymentSearchStatus.textContent = t('Loading payment transactions…');
    try {
      const response = await fetch(`/payments/search?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pendingRequest.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || t('Could not load payment transactions.'));
      renderTransactions(result.transactions || []);
      const total = Number(result.total || 0);
      const first = total === 0 ? 0 : ((result.page - 1) * 10 + 1);
      const last = Math.min(result.page * 10, total);
      paymentResultCount.textContent = t('Showing {first}–{last} of {total} transactions.', { first, last, total });
      renderPagination(result.page || page, result.total_pages || 1);
    totalIn.textContent = formatMoney(result.summary?.payments);
      totalRefunds.textContent = formatMoney(result.summary?.refunds);
      totalPending.textContent = formatMoney(result.summary?.pending);
      totalNet.textContent = formatMoney(result.summary?.net);
      Object.entries(reconciliationCells).forEach(([method, cells]) => {
        ['payments', 'refunds', 'net'].forEach((key) => {
          if (cells[key]) cells[key].textContent = formatMoney(result.reconciliation?.[method]?.[key]);
        });
      });
      currentPage = result.page || page;
      paymentSearchStatus.textContent = t('{count} transactions shown.', { count: result.transactions?.length || 0 });
    } catch (error) {
      if (error.name === 'AbortError') return;
      paymentSearchStatus.textContent = error.message || t('Could not load payment transactions.');
    }
  };

  paymentsForm.addEventListener('submit', (event) => { event.preventDefault(); loadTransactions(1); });
  queryInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadTransactions(1), 250);
  });
  rangeInput.addEventListener('change', () => { toggleCustomDates(); loadTransactions(1); });
  [typeInput, statusInput, methodInput, staffInput, fromInput, toInput].forEach((input) => input.addEventListener('change', () => loadTransactions(1)));
  paymentPagination.addEventListener('click', (event) => {
    const link = event.target.closest('[data-payment-page]');
    if (!link) return;
    event.preventDefault();
    loadTransactions(Number(link.dataset.paymentPage) || 1);
  });
  toggleCustomDates();
  updateExportLinks();
  loadTransactions(currentPage);
}
};
document.addEventListener('app:page-ready', initializePaymentsPage);
