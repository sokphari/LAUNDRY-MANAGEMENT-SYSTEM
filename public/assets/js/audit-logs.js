const auditForm = document.querySelector('#audit-search-form');
const auditRows = document.querySelector('#audit-log-rows');
const auditPagination = document.querySelector('#audit-pagination');
const auditCount = document.querySelector('#audit-result-count');
const auditStatus = document.querySelector('#audit-status');

if (auditForm && auditRows && auditPagination && auditCount) {
  const t = (message, values = {}) => window.appTranslate(message, values);
  const searchInput = auditForm.querySelector('[name="q"]');
  const filterInputs = [...auditForm.querySelectorAll('select, input[type="date"]')];
  const rangeInput = auditForm.querySelector('[name="range"]');
  const customRangeFields = [...auditForm.querySelectorAll('[data-audit-custom-range]')];
  let pendingRequest;
  let searchTimer;
  let expandedButton = null;

  const currentParams = (page) => {
    const params = new URLSearchParams({ q: searchInput?.value.trim() || '', page: String(page) });
    filterInputs.forEach((input) => {
      if (input.value && (input.type !== 'date' || rangeInput?.value === 'custom')) params.set(input.name, input.value);
    });
    return params;
  };

  const toggleCustomDates = () => customRangeFields.forEach((field) => field.classList.toggle('hidden', rangeInput?.value !== 'custom'));

  const appendTextCell = (row, value, className) => {
    const cell = document.createElement('td');
    cell.className = className;
    cell.textContent = value;
    row.append(cell);
    return cell;
  };

  const humanize = (value) => String(value).replaceAll('_', ' ').replaceAll('.', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
  const fieldLabel = (value) => t(humanize(value));
  const formatFieldValue = (field, value) => {
    if (/(^|\.)(status|method|payment_method|refund_method)$/.test(field) && typeof value === 'string') return t(value[0].toUpperCase() + value.slice(1));
    const isMoney = /(price|amount|total|subtotal|deposit|refundable)$/.test(field);
    const formatted = isMoney && value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))
      ? new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value))
      : formatValue(value);
    return isMoney && value !== null && value !== undefined && value !== '' ? `${formatted} KHR` : formatted;
  };
  const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return t(value ? 'Yes' : 'No');
    if (Array.isArray(value)) {
      if (value.length === 0) return 'None';
      return value.map((item) => {
        if (item && typeof item === 'object') return Object.entries(item).map(([key, part]) => `${fieldLabel(key)}: ${formatValue(part)}`).join(' · ');
        return formatValue(item);
      }).join('\n');
    }
    if (typeof value === 'object') return Object.entries(value).map(([key, part]) => `${fieldLabel(key)}: ${formatValue(part)}`).join(' · ');
    return String(value);
  };

  const flatten = (value, prefix = '', result = {}) => {
    Object.entries(value && typeof value === 'object' && !Array.isArray(value) ? value : {}).forEach(([key, part]) => {
      const path = prefix ? `${prefix}.${key}` : key;
      if (part && typeof part === 'object' && !Array.isArray(part)) flatten(part, path, result);
      else result[path] = part;
    });
    return result;
  };

  const changeRows = (details) => {
    const reason = details.reason ?? null;
    if (details.before || details.after) {
      const before = flatten(details.before || {});
      const after = flatten(details.after || {});
      const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])];
      const rows = keys.map((key) => [fieldLabel(key), formatFieldValue(key, before[key]), formatFieldValue(key, after[key])]);
      if (details.amount !== undefined) rows.push(['This refund', '—', formatFieldValue('amount', details.amount)]);
      if (details.payment_method !== undefined) rows.push(['Original payment method', '—', formatValue(details.payment_method)]);
      if (details.refund_method !== undefined) rows.push(['Refund method', '—', formatValue(details.refund_method)]);
      return { reason, rows };
    }
    if ('old_status' in details || 'new_status' in details) {
      return { reason, rows: [['Status', formatValue(details.old_status), formatValue(details.new_status)]] };
    }
    if ('original_amount' in details || 'replacement_amount' in details) {
      const hasReplacement = details.replacement_amount !== null && details.replacement_amount !== undefined;
      const rows = [['Payment amount', formatFieldValue('original_amount', details.original_amount), hasReplacement ? formatFieldValue('replacement_amount', details.replacement_amount) : 'Voided (no replacement)']];
      if (details.original_method !== undefined || details.replacement_method !== undefined) rows.push(['Payment method', formatValue(details.original_method), hasReplacement ? formatValue(details.replacement_method) : 'Voided']);
      return { reason, rows };
    }
    const rows = Object.entries(details).filter(([key]) => key !== 'reason').map(([key, value]) => [fieldLabel(key), '—', formatFieldValue(key, value)]);
    return { reason, rows };
  };

  const renderDetailPanel = (button, panel) => {
    let details = {};
    try { details = JSON.parse(button.dataset.auditData || '{}') || {}; } catch { details = {}; }
    const section = document.createElement('section');
    section.setAttribute('aria-labelledby', button.id);
    section.className = 'space-y-4';

    const metadata = document.createElement('dl');
    metadata.className = 'grid gap-3 sm:grid-cols-2 lg:grid-cols-5';
    [['Actor', button.dataset.auditActor], ['Timestamp', button.dataset.auditTime], ['Module', button.dataset.auditModule], ['Action', button.dataset.auditAction], ['Record', button.dataset.auditRecord]].forEach(([label, value]) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'min-w-0';
      const term = document.createElement('dt');
      term.className = 'text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
      term.textContent = t(label);
      const description = document.createElement('dd');
      description.className = 'mt-1 break-words text-sm font-medium';
      description.textContent = value || '—';
      wrapper.append(term, description);
      metadata.append(wrapper);
    });
    section.append(metadata);

    const { reason, rows } = changeRows(details);
    if (rows.length > 0) {
      const heading = document.createElement('h3');
      heading.className = 'text-sm font-semibold';
      heading.textContent = t('Change comparison');
      const scroller = document.createElement('div');
      scroller.className = 'overflow-x-auto overscroll-x-contain rounded-lg border border-zinc-200 dark:border-zinc-800';
      const table = document.createElement('table');
      table.className = 'w-full min-w-[480px] text-left text-sm';
      const thead = document.createElement('thead');
      thead.className = 'bg-white/70 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400';
      const headRow = document.createElement('tr');
      ['Field', 'Before', 'After'].forEach((label) => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-3 py-2 font-semibold';
        th.textContent = t(label);
        headRow.append(th);
      });
      thead.append(headRow);
      const tbody = document.createElement('tbody');
      tbody.className = 'divide-y divide-zinc-200 dark:divide-zinc-800';
      rows.forEach(([field, before, after]) => {
        const row = document.createElement('tr');
        [field, before, after].forEach((value, index) => {
          const cell = document.createElement(index === 0 ? 'th' : 'td');
          if (index === 0) cell.scope = 'row';
          cell.className = 'whitespace-pre-line break-words px-3 py-2 align-top';
          cell.textContent = value;
          row.append(cell);
        });
        tbody.append(row);
      });
      table.append(thead, tbody);
      scroller.append(table);
      section.append(heading, scroller);
    }
    if (reason !== null && reason !== '') {
      const reasonBox = document.createElement('p');
      reasonBox.className = 'rounded-lg border border-violet-200 bg-white/70 p-3 text-sm dark:border-violet-900 dark:bg-zinc-900';
      const label = document.createElement('strong');
      label.textContent = t('Reason: ');
      reasonBox.append(label, document.createTextNode(formatValue(reason)));
      section.append(reasonBox);
    }
    if (rows.length === 0 && (reason === null || reason === '')) {
      const empty = document.createElement('p');
      empty.className = 'text-sm text-zinc-600 dark:text-zinc-300';
      empty.textContent = t('No value changes were recorded for this event.');
      section.append(empty);
    }
    panel.replaceChildren(section);
  };

  const makeDetailRows = (log, button) => {
    const detailRow = document.createElement('tr');
    detailRow.id = `audit-details-${log.id}`;
    detailRow.hidden = true;
    const cell = document.createElement('td');
    cell.colSpan = 5;
    cell.className = 'px-2 pb-3 sm:px-4 sm:pb-4';
    const panel = document.createElement('div');
    panel.dataset.auditPanel = '';
    panel.className = 'rounded-xl border border-violet-200 bg-violet-50/70 p-3 text-zinc-900 dark:border-violet-900 dark:bg-zinc-950 dark:text-zinc-100 sm:p-4';
    cell.append(panel);
    detailRow.append(cell);
    button.id = `audit-toggle-${log.id}`;
    button.type = 'button';
    button.className = 'mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
    button.dataset.auditToggle = '';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', detailRow.id);
    button.dataset.auditId = String(log.id);
    button.dataset.auditActor = log.username || 'Deleted user';
    button.dataset.auditTime = log.created_display || '';
    button.dataset.auditModule = t(humanize(log.entity_type || ''));
    button.dataset.auditAction = t(log.action_label || humanize(log.action || ''));
    button.dataset.auditRecord = `${t(humanize(log.entity_type || 'record'))}${log.entity_id ? ` #${log.entity_id}` : ''}`;
    button.dataset.auditData = JSON.stringify(log.details_data && typeof log.details_data === 'object' ? log.details_data : {});
    button.textContent = t('View Change Details');
    return detailRow;
  };

  const renderLogs = (logs) => {
    expandedButton = null;
    auditRows.replaceChildren();
    if (logs.length === 0) {
      const row = document.createElement('tr');
      const cell = appendTextCell(row, t('No audit activity found.'), 'px-5 py-12 text-center text-zinc-500 dark:text-zinc-400');
      cell.colSpan = 5;
      auditRows.append(row);
      return;
    }

    logs.forEach((log) => {
      const row = document.createElement('tr');
      row.className = 'align-top';
      appendTextCell(row, log.created_display || '', 'whitespace-nowrap px-5 py-4 text-zinc-600 dark:text-zinc-300');
      appendTextCell(row, log.username || 'Deleted user', 'px-5 py-4');

      const actionCell = document.createElement('td');
      actionCell.className = 'px-5 py-4';
      const badge = document.createElement('span');
      badge.className = `inline-flex rounded-full px-3 py-1.5 text-xs font-semibold leading-4 ${log.action_class || 'bg-zinc-800 text-zinc-300'}`;
      badge.textContent = t(log.action_label || log.action);
      actionCell.append(badge);
      row.append(actionCell);

      const recordCell = document.createElement('td');
      recordCell.className = 'px-5 py-4';
      const linkedOrderId = Number(log.linked_order_id || 0);
      if (linkedOrderId > 0) {
        const link = document.createElement('a');
        link.href = `/orders/${linkedOrderId}`;
        link.className = 'font-medium text-primary hover:underline';
        link.textContent = ['payment', 'refund'].includes(log.entity_type)
          ? `${t('Order')} #${linkedOrderId} · ${t(humanize(log.entity_type))} #${log.entity_id}`
          : `${t(humanize(log.entity_type || 'record'))} #${log.entity_id}`;
        recordCell.append(link);
      } else {
        recordCell.textContent = `${t(humanize(log.entity_type || 'record'))}${log.entity_id ? ` #${log.entity_id}` : ''}`;
      }
      row.append(recordCell);

      const detailsCell = document.createElement('td');
      detailsCell.className = 'max-w-xl px-5 py-4';
      const summary = document.createElement('p');
      summary.className = 'font-medium';
      summary.textContent = log.summary || '';
      detailsCell.append(summary);
      let detailRow = null;
      const toggle = document.createElement('button');
      detailRow = makeDetailRows(log, toggle);
      detailsCell.append(toggle);
      row.append(detailsCell);
      auditRows.append(row);
      if (detailRow) auditRows.append(detailRow);
    });
  };

  auditRows.addEventListener('click', (event) => {
    const button = event.target.closest('[data-audit-toggle]');
    if (!button) return;
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    if (expandedButton && expandedButton !== button) {
      expandedButton.setAttribute('aria-expanded', 'false');
      expandedButton.textContent = t('View Change Details');
      const previousRow = document.getElementById(expandedButton.getAttribute('aria-controls'));
      if (previousRow) previousRow.hidden = true;
    }
    const detailsRow = document.getElementById(button.getAttribute('aria-controls'));
    if (!detailsRow) return;
    if (isOpen) {
      button.setAttribute('aria-expanded', 'false');
      button.textContent = t('View Change Details');
      detailsRow.hidden = true;
      expandedButton = null;
      return;
    }
    button.setAttribute('aria-expanded', 'true');
    button.textContent = t('Hide Change Details');
    detailsRow.hidden = false;
    renderDetailPanel(button, detailsRow.querySelector('[data-audit-panel]'));
    expandedButton = button;
  });

  const pageLink = (label, page) => {
    const link = document.createElement('a');
    link.href = `/audit-logs?${currentParams(page)}`;
    link.dataset.auditPage = String(page);
    link.className = 'rounded-md px-3 py-1.5 text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800';
    link.textContent = label;
    return link;
  };

  const renderPagination = (page, totalPages, total) => {
    auditPagination.replaceChildren();
    if (page > 1) auditPagination.append(pageLink(t('Previous'), page - 1));
    const label = document.createElement('span');
    label.className = 'px-2 text-xs text-zinc-500 dark:text-zinc-400';
    label.dataset.auditPageLabel = '';
    label.textContent = t('Page {page} of {pages}', { page, pages: totalPages });
    auditPagination.append(label);
    if (page < totalPages) auditPagination.append(pageLink(t('Next'), page + 1));
    const first = total === 0 ? 0 : ((page - 1) * 10 + 1);
    const last = Math.min(page * 10, total);
    auditCount.textContent = t('Showing {first}–{last} of {total} events', { first, last, total });
  };

  const loadAuditLogs = async (page = 1, historyMode = 'replace') => {
    pendingRequest?.abort();
    pendingRequest = new AbortController();
    if (auditStatus) auditStatus.textContent = t('Loading audit activity…');
    const params = currentParams(page);
    const url = `/audit-logs?${params}`;
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pendingRequest.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || t('Could not load audit activity.'));
      renderLogs(result.logs || []);
      renderPagination(result.page || page, result.total_pages || 1, result.total || 0);
      if (historyMode !== 'none') history[`${historyMode}State`]({}, '', url);
      if (auditStatus) auditStatus.textContent = t('Audit activity updated.');
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (auditStatus) auditStatus.textContent = error.message || t('Could not load audit activity.');
    }
  };

  auditForm.addEventListener('submit', (event) => {
    event.preventDefault();
    loadAuditLogs(1);
  });
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadAuditLogs(1), 250);
  });
  filterInputs.forEach((input) => input.addEventListener('change', () => {
    toggleCustomDates();
    loadAuditLogs(1);
  }));
  auditPagination.addEventListener('click', (event) => {
    const link = event.target.closest('[data-audit-page]');
    if (!link) return;
    event.preventDefault();
    loadAuditLogs(Number(link.dataset.auditPage) || 1, 'push');
  });
  window.addEventListener('popstate', () => {
    const params = new URLSearchParams(location.search);
    if (searchInput) searchInput.value = params.get('q') || '';
    filterInputs.forEach((input) => { input.value = params.get(input.name) || ''; });
    toggleCustomDates();
    loadAuditLogs(Math.max(1, Number(params.get('page')) || 1), 'none');
  });
  toggleCustomDates();
}
