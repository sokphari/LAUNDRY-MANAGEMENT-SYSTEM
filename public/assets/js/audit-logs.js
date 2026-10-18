const auditForm = document.querySelector('#audit-search-form');
const auditRows = document.querySelector('#audit-log-rows');
const auditPagination = document.querySelector('#audit-pagination');
const auditCount = document.querySelector('#audit-result-count');
const auditStatus = document.querySelector('#audit-status');

if (auditForm && auditRows && auditPagination && auditCount) {
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
  const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) {
      if (value.length === 0) return 'None';
      return value.map((item) => {
        if (item && typeof item === 'object') return Object.entries(item).map(([key, part]) => `${humanize(key)}: ${formatValue(part)}`).join(' · ');
        return formatValue(item);
      }).join('\n');
    }
    if (typeof value === 'object') return Object.entries(value).map(([key, part]) => `${humanize(key)}: ${formatValue(part)}`).join(' · ');
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
      return { reason, rows: keys.map((key) => [humanize(key), formatValue(before[key]), formatValue(after[key])]) };
    }
    if ('old_status' in details || 'new_status' in details) {
      return { reason, rows: [['Status', formatValue(details.old_status), formatValue(details.new_status)]] };
    }
    if ('original_amount' in details || 'replacement_amount' in details) {
      const hasReplacement = details.replacement_amount !== null && details.replacement_amount !== undefined;
      const rows = [['Payment amount', formatValue(details.original_amount), hasReplacement ? formatValue(details.replacement_amount) : 'Voided (no replacement)']];
      if (details.original_method !== undefined || details.replacement_method !== undefined) rows.push(['Payment method', formatValue(details.original_method), hasReplacement ? formatValue(details.replacement_method) : 'Voided']);
      return { reason, rows };
    }
    const rows = Object.entries(details).filter(([key]) => key !== 'reason').map(([key, value]) => [humanize(key), '—', formatValue(value)]);
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
      term.textContent = label;
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
      heading.textContent = 'Change comparison';
      const scroller = document.createElement('div');
      scroller.className = 'overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800';
      const table = document.createElement('table');
      table.className = 'w-full min-w-[520px] text-left text-sm';
      const thead = document.createElement('thead');
      thead.className = 'bg-white/70 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400';
      const headRow = document.createElement('tr');
      ['Field', 'Before', 'After'].forEach((label) => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-3 py-2 font-semibold';
        th.textContent = label;
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
          cell.className = 'whitespace-pre-line px-3 py-2 align-top';
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
      label.textContent = 'Reason: ';
      reasonBox.append(label, document.createTextNode(formatValue(reason)));
      section.append(reasonBox);
    }
    if (rows.length === 0 && (reason === null || reason === '')) {
      const empty = document.createElement('p');
      empty.className = 'text-sm text-zinc-600 dark:text-zinc-300';
      empty.textContent = 'No value changes were recorded for this event.';
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
    cell.className = 'px-4 pb-4';
    const panel = document.createElement('div');
    panel.dataset.auditPanel = '';
    panel.className = 'rounded-xl border border-violet-200 bg-violet-50/70 p-4 text-zinc-900 dark:border-violet-900 dark:bg-zinc-950 dark:text-zinc-100';
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
    button.dataset.auditModule = humanize(log.entity_type || '');
    button.dataset.auditAction = log.action_label || humanize(log.action || '');
    button.dataset.auditRecord = `${humanize(log.entity_type || 'record')}${log.entity_id ? ` #${log.entity_id}` : ''}`;
    button.dataset.auditData = JSON.stringify(log.details_data && typeof log.details_data === 'object' ? log.details_data : {});
    button.textContent = 'View Change Details';
    return detailRow;
  };

  const renderLogs = (logs) => {
    expandedButton = null;
    auditRows.replaceChildren();
    if (logs.length === 0) {
      const row = document.createElement('tr');
      const cell = appendTextCell(row, 'No audit activity found.', 'px-5 py-12 text-center text-zinc-500 dark:text-zinc-400');
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
      badge.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${log.action_class || 'bg-zinc-800 text-zinc-300'}`;
      badge.textContent = log.action_label || log.action;
      actionCell.append(badge);
      row.append(actionCell);

      const recordCell = document.createElement('td');
      recordCell.className = 'px-5 py-4';
      const linkedOrderId = Number(log.linked_order_id || 0);
      if (linkedOrderId > 0) {
        const link = document.createElement('a');
        link.href = `/orders/${linkedOrderId}`;
        link.className = 'font-medium text-primary hover:underline';
        link.textContent = log.entity_type === 'payment'
          ? `Order #${linkedOrderId} · Payment #${log.entity_id}`
          : `${(log.entity_type || 'record')[0].toUpperCase()}${(log.entity_type || 'record').slice(1)} #${log.entity_id}`;
        recordCell.append(link);
      } else {
        recordCell.textContent = `${(log.entity_type || 'record')[0].toUpperCase()}${(log.entity_type || 'record').slice(1)}${log.entity_id ? ` #${log.entity_id}` : ''}`;
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
      expandedButton.textContent = 'View Change Details';
      const previousRow = document.getElementById(expandedButton.getAttribute('aria-controls'));
      if (previousRow) previousRow.hidden = true;
    }
    const detailsRow = document.getElementById(button.getAttribute('aria-controls'));
    if (!detailsRow) return;
    if (isOpen) {
      button.setAttribute('aria-expanded', 'false');
      button.textContent = 'View Change Details';
      detailsRow.hidden = true;
      expandedButton = null;
      return;
    }
    button.setAttribute('aria-expanded', 'true');
    button.textContent = 'Hide Change Details';
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
    if (page > 1) auditPagination.append(pageLink('Previous', page - 1));
    const label = document.createElement('span');
    label.className = 'px-2 text-xs text-zinc-500 dark:text-zinc-400';
    label.dataset.auditPageLabel = '';
    label.textContent = `Page ${page} of ${totalPages}`;
    auditPagination.append(label);
    if (page < totalPages) auditPagination.append(pageLink('Next', page + 1));
    const first = total === 0 ? 0 : ((page - 1) * 10 + 1);
    const last = Math.min(page * 10, total);
    auditCount.textContent = `Showing ${first}–${last} of ${total} events`;
  };

  const loadAuditLogs = async (page = 1, historyMode = 'replace') => {
    pendingRequest?.abort();
    pendingRequest = new AbortController();
    if (auditStatus) auditStatus.textContent = 'Loading audit activity…';
    const params = currentParams(page);
    const url = `/audit-logs?${params}`;
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pendingRequest.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Could not load audit activity.');
      renderLogs(result.logs || []);
      renderPagination(result.page || page, result.total_pages || 1, result.total || 0);
      if (historyMode !== 'none') history[`${historyMode}State`]({}, '', url);
      if (auditStatus) auditStatus.textContent = 'Audit activity updated.';
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (auditStatus) auditStatus.textContent = error.message || 'Could not load audit activity.';
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
