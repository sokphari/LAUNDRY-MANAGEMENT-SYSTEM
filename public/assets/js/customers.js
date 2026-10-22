const initializeCustomersPage = () => {
(() => {
  const form = document.getElementById('customer-search-form');
  const input = document.getElementById('customer-search');
  const rows = document.getElementById('customer-rows');
  const status = document.getElementById('customer-search-status');
  const resultCount = document.getElementById('customer-result-count');
  const pagination = document.getElementById('customer-pagination');

  if (!form || !input || !rows || !status) return;

  let translations = {};
  try {
    translations = JSON.parse(rows.dataset.i18n || '{}');
  } catch {}
  const translate = (key, values = {}) => Object.entries(values).reduce(
    (message, [name, value]) => message.replaceAll(`{${name}}`, String(value)),
    translations[key] || key,
  );

  let debounceTimer;
  let activeRequest;
  const svgNamespace = 'http://www.w3.org/2000/svg';
  const iconPaths = {
    view: 'M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z',
    edit: 'm14 5 5 5M4 20l4.5-1 11-11a2.12 2.12 0 0 0-3-3l-11 11L4 20Z',
    delete: 'M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3',
  };

  const makeIcon = (name) => {
    const svg = document.createElementNS(svgNamespace, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'size-4');

    if (name === 'view') {
      const eye = document.createElementNS(svgNamespace, 'path');
      eye.setAttribute('d', iconPaths.view);
      const pupil = document.createElementNS(svgNamespace, 'circle');
      pupil.setAttribute('cx', '12');
      pupil.setAttribute('cy', '12');
      pupil.setAttribute('r', '2.5');
      svg.append(eye, pupil);
    } else {
      const path = document.createElementNS(svgNamespace, 'path');
      path.setAttribute('d', iconPaths[name]);
      svg.append(path);
    }
    return svg;
  };

  const makeActionLink = (id, customerName, action, label, colorClass) => {
    const link = document.createElement('a');
    link.href = `/customers/${encodeURIComponent(id)}${action === 'edit' ? '/edit' : ''}`;
    link.className = `grid size-9 place-items-center rounded-lg border border-zinc-200 ${colorClass} focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700`;
    link.setAttribute('aria-label', `${label} ${customerName}`);
    link.title = label;
    link.append(makeIcon(action));
    return link;
  };

  const makeCell = (text, className = 'px-5 py-4') => {
    const cell = document.createElement('td');
    cell.className = className;
    cell.textContent = text || '—';
    return cell;
  };

  const makeBadgeCell = (text, badgeClass, cellClass = 'px-5 py-4') => {
    const cell = document.createElement('td');
    cell.className = cellClass;
    const badge = document.createElement('span');
    badge.className = badgeClass;
    badge.textContent = text || '—';
    cell.append(badge);
    return cell;
  };

  const showEmptyRow = (message) => {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 6;
    cell.className = 'px-5 py-12 text-center text-zinc-500 dark:text-zinc-400';
    cell.textContent = message;
    row.append(cell);
    rows.replaceChildren(row);
  };

  const makePageLink = (label, page, currentPage, disabled = false, ariaLabel = '') => {
    const link = document.createElement('a');
    link.href = `/customers?${new URLSearchParams({ q: input.value.trim(), page: String(page) })}`;
    link.dataset.customerPage = String(page);
    link.className = `rounded-md border px-3 py-1.5 text-sm ${page === currentPage ? 'border-primary bg-violet-50 font-semibold text-primary dark:bg-violet-950/50' : 'border-zinc-200 hover:border-primary hover:text-primary dark:border-zinc-700'}${disabled ? ' pointer-events-none opacity-50' : ''}`;
    link.textContent = label;
    if (ariaLabel) link.setAttribute('aria-label', ariaLabel);
    if (disabled) {
      link.setAttribute('aria-disabled', 'true');
      link.tabIndex = -1;
    }
    if (page === currentPage && !ariaLabel) link.setAttribute('aria-current', 'page');
    return link;
  };

  const renderPagination = (page, totalPages) => {
    if (!pagination) return;
    pagination.replaceChildren();
    pagination.append(makePageLink(translations.previous || 'Previous', Math.max(1, page - 1), page, page <= 1, translations.previousPage || 'Previous page'));

    const first = Math.max(1, page - 2);
    const last = Math.min(totalPages, page + 2);
    if (first > 1) {
      pagination.append(makePageLink('1', 1, page));
      if (first > 2) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'px-1 text-zinc-500';
        ellipsis.textContent = '…';
        ellipsis.setAttribute('aria-hidden', 'true');
        pagination.append(ellipsis);
      }
    }
    for (let number = first; number <= last; number += 1) {
      pagination.append(makePageLink(String(number), number, page));
    }
    if (last < totalPages) {
      if (last < totalPages - 1) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'px-1 text-zinc-500';
        ellipsis.textContent = '…';
        ellipsis.setAttribute('aria-hidden', 'true');
        pagination.append(ellipsis);
      }
      pagination.append(makePageLink(String(totalPages), totalPages, page));
    }
    pagination.append(makePageLink(translations.next || 'Next', Math.min(totalPages, page + 1), page, page >= totalPages, translations.nextPage || 'Next page'));
  };

  const renderCustomers = (customers, query, page, total, totalPages) => {
    rows.replaceChildren();

    if (customers.length === 0) {
      showEmptyRow(query ? translate('noSearchResults') : translate('noCustomers'));
    } else {
      customers.forEach((customer, index) => {
        const id = String(customer.id ?? '');
        if (!/^\d+$/.test(id)) return;
        const name = String(customer.name ?? '');
        const row = document.createElement('tr');
        row.append(makeCell(String((page - 1) * 10 + index + 1), 'px-5 py-4 text-zinc-500 dark:text-zinc-400'));
        row.append(makeBadgeCell(name, 'inline-flex max-w-full rounded-full bg-violet-100 px-3 py-1.5 font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200'));
        row.append(makeCell(String(customer.phone ?? ''), 'px-5 py-4 whitespace-nowrap'));
        row.append(makeBadgeCell(String(customer.address ?? '') || '—', 'inline-block max-w-full truncate rounded-lg bg-sky-50 px-3 py-1.5 text-sky-900 dark:bg-sky-950/60 dark:text-sky-100', 'max-w-sm px-5 py-4'));
        const createdAt = new Date(String(customer.created_at ?? '').replace(' ', 'T'));
        const formattedCreatedAt = Number.isNaN(createdAt.getTime())
          ? String(customer.created_at ?? '')
          : new Intl.DateTimeFormat(document.documentElement.lang === 'km' ? 'km-KH' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(createdAt);
        row.append(makeCell(formattedCreatedAt, 'px-5 py-4 whitespace-nowrap text-zinc-600 dark:text-zinc-300'));

        const actionCell = document.createElement('td');
        actionCell.className = 'px-5 py-4';
        const actionGroup = document.createElement('div');
        actionGroup.className = 'flex items-center justify-end gap-2';
        actionGroup.append(makeActionLink(id, name, 'view', translations.view || 'View customer', 'text-zinc-600 hover:border-primary hover:text-primary dark:text-zinc-300'));
        actionGroup.append(makeActionLink(id, name, 'edit', translations.edit || 'Edit customer', 'text-zinc-600 hover:border-primary hover:text-primary dark:text-zinc-300'));

        const deleteForm = document.createElement('form');
        deleteForm.method = 'post';
        deleteForm.action = `/customers/${encodeURIComponent(id)}/delete`;
        deleteForm.dataset.confirm = translate('deactivate', { name: name || 'customer' });
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_csrf_token';
        csrf.value = rows.dataset.csrfToken || '';
        const deleteButton = document.createElement('button');
        deleteButton.type = 'submit';
        deleteButton.className = 'grid size-9 place-items-center rounded-lg border border-zinc-200 text-red-600 hover:border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-zinc-700 dark:hover:border-red-900 dark:hover:bg-red-950';
        deleteButton.setAttribute('aria-label', `${translations.delete || 'Delete customer'} ${name}`);
        deleteButton.title = translations.delete || 'Delete customer';
        deleteButton.append(makeIcon('delete'));
        deleteForm.append(csrf, deleteButton);
        actionGroup.append(deleteForm);
        actionCell.append(actionGroup);
        row.append(actionCell);
        rows.append(row);
      });
    }

    if (resultCount) {
      const first = total > 0 ? ((page - 1) * 10) + 1 : 0;
      const last = Math.min(page * 10, total);
      resultCount.textContent = translate(total === 1 ? 'resultCountOne' : 'resultCountMany', { first, last, total, matching: query ? (translations.matching || ' matching your search') : '' });
    }
    renderPagination(page, totalPages);
    status.textContent = translate('found', { total, page, pages: totalPages });
  };

  const search = async (page = 1) => {
    const query = input.value.trim();
    activeRequest?.abort();
    activeRequest = new AbortController();

    const url = new URL('/customers/search', window.location.origin);
    if (query) url.searchParams.set('q', query);
    url.searchParams.set('page', String(page));

    status.textContent = translate('searching');
    try {
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: activeRequest.signal,
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || translate('searchFailed'));
      renderCustomers(
        Array.isArray(result.customers) ? result.customers : [],
        query,
        Number(result.page) || 1,
        Number(result.total) || 0,
        Number(result.total_pages) || 1,
      );

      const pageUrl = new URL(window.location.href);
      if (query) pageUrl.searchParams.set('q', query);
      else pageUrl.searchParams.delete('q');
      if (Number(result.page) > 1) pageUrl.searchParams.set('page', String(result.page));
      else pageUrl.searchParams.delete('page');
      window.history.replaceState({}, '', pageUrl);
    } catch (error) {
      if (error.name === 'AbortError') return;
      status.textContent = error.message || translate('searchFailed');
      showEmptyRow(translate('loadFailed'));
    }
  };

  input.addEventListener('input', () => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => search(1), 250);
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    window.clearTimeout(debounceTimer);
    search(1);
  });

  pagination?.addEventListener('click', (event) => {
    const link = event.target.closest('[data-customer-page]');
    if (!link || link.getAttribute('aria-disabled') === 'true') return;
    event.preventDefault();
    search(Number(link.dataset.customerPage) || 1);
  });
})();
};
document.addEventListener('app:page-ready', initializeCustomersPage);
