const initializeServicesPage = () => {
document.querySelectorAll('[data-service-type]').forEach((select) => {
  const form = select.closest('form');
  const customField = form?.querySelector('[data-custom-service-field]');
  const customInput = form?.querySelector('[data-custom-service-name]');

  if (!customField || !customInput) return;

  const updateCustomField = () => {
    const isOther = select.value === 'Other';
    customField.classList.toggle('hidden', !isOther);
    customInput.disabled = !isOther;
    customInput.required = isOther;
  };

  select.addEventListener('change', updateCustomField);
  updateCustomField();
});

const searchForm = document.querySelector('#service-search-form');
const serviceRows = document.querySelector('#service-rows');
const resultCount = document.querySelector('#service-result-count');
const searchStatus = document.querySelector('#service-search-status');

if (searchForm && serviceRows && resultCount && searchStatus) {
  const searchInput = searchForm.querySelector('[name="q"]');
  const statusFilter = searchForm.querySelector('[name="status"]');
  let pendingRequest;
  let searchTimer;

  const makeIcon = (pathMarkup) => {
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-width', '1.8');
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('class', 'size-4');
    icon.innerHTML = pathMarkup;
    return icon;
  };

  const actionLink = (href, label, path) => {
    const link = document.createElement('a');
    link.href = href;
    link.setAttribute('aria-label', label);
    link.title = label;
    link.className = 'grid size-9 place-items-center rounded-lg border border-zinc-200 text-zinc-600 hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 dark:text-zinc-300';
    link.append(makeIcon(path));
    return link;
  };

  const renderServices = (services) => {
    serviceRows.replaceChildren();
    services.forEach((service, index) => {
      const active = Number(service.is_active) === 1;
      const row = document.createElement('tr');
      const cell = (text, className = 'px-5 py-4') => {
        const td = document.createElement('td');
        td.className = className;
        td.textContent = text;
        row.append(td);
        return td;
      };

      cell(String(index + 1), 'px-5 py-4 text-zinc-500 dark:text-zinc-400');
      const nameCell = document.createElement('td');
      nameCell.className = 'px-5 py-4';
      const nameBadge = document.createElement('span');
      nameBadge.className = 'inline-flex max-w-full rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200';
      nameBadge.textContent = service.name || '';
      nameCell.append(nameBadge);
      row.append(nameCell);
      cell(service.description || '—', 'max-w-sm truncate px-5 py-4 text-zinc-600 dark:text-zinc-300');
      cell(`${Number(service.price).toFixed(2)} KHR / ${service.billing_unit || 'piece'}`, 'whitespace-nowrap px-5 py-4 font-medium');

      const statusCell = document.createElement('td');
      statusCell.className = 'px-5 py-4';
      const badge = document.createElement('span');
      badge.className = `rounded-full px-2.5 py-1 text-xs font-semibold ${active ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'}`;
      badge.textContent = window.appTranslate(active ? 'Active' : 'Inactive');
      statusCell.append(badge);
      row.append(statusCell);

      cell(service.created_at_display || '', 'whitespace-nowrap px-5 py-4 text-zinc-600 dark:text-zinc-300');
      const actionsCell = document.createElement('td');
      actionsCell.className = 'px-5 py-4';
      const actions = document.createElement('div');
      actions.className = 'flex items-center justify-end gap-2';
      actions.append(actionLink(`/services/${Number(service.id)}`, window.appTranslate('View {name}', { name: service.name || window.appTranslate('service') }), '<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>'));

      if (serviceRows.dataset.canManage === '1') {
        actions.append(actionLink(`/services/${Number(service.id)}/edit`, window.appTranslate('Edit {name}', { name: service.name || window.appTranslate('service') }), '<path stroke-linecap="round" stroke-linejoin="round" d="m14 5 5 5M4 20l4.5-1 11-11a2.12 2.12 0 0 0-3-3l-11 11L4 20Z"/>'));
        if (Number(service.usage_count) === 0) {
          const deleteForm = document.createElement('form');
          deleteForm.method = 'post';
          deleteForm.action = `/services/${Number(service.id)}/delete-unused`;
          deleteForm.dataset.confirm = window.appTranslate('Permanently delete {name}? This service has no order history.', { name: service.name || window.appTranslate('service') });
          deleteForm.dataset.confirmTitle = window.appTranslate('Confirm service deletion');
          deleteForm.dataset.confirmAcceptLabel = window.appTranslate('Delete');
          const csrf = document.createElement('input');
          csrf.type = 'hidden';
          csrf.name = '_csrf_token';
          csrf.value = serviceRows.dataset.csrfToken || '';
          const deleteButton = document.createElement('button');
          deleteButton.type = 'submit';
          deleteButton.setAttribute('aria-label', window.appTranslate('Delete {name}', { name: service.name || window.appTranslate('service') }));
          deleteButton.title = window.appTranslate('Delete service');
          deleteButton.className = 'grid size-9 place-items-center rounded-lg border border-red-200 text-red-600 hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950';
          deleteButton.append(makeIcon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/>'));
          deleteForm.append(csrf, deleteButton);
          actions.append(deleteForm);
        }
        const form = document.createElement('form');
        form.method = 'post';
        form.action = `/services/${Number(service.id)}/status`;
        form.dataset.serviceStatusForm = '';
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_csrf_token';
        csrf.value = serviceRows.dataset.csrfToken || '';
        const nextStatus = document.createElement('input');
        nextStatus.type = 'hidden';
        nextStatus.name = 'is_active';
        nextStatus.value = active ? '0' : '1';
        const button = document.createElement('button');
        button.type = 'submit';
        button.setAttribute('aria-label', window.appTranslate(active ? 'Deactivate {name}' : 'Activate {name}', { name: service.name || window.appTranslate('service') }));
        button.title = window.appTranslate(active ? 'Deactivate service' : 'Activate service');
        button.className = `grid size-9 place-items-center rounded-lg border border-zinc-200 focus:outline-none focus:ring-2 focus:ring-primary dark:border-zinc-700 ${active ? 'text-red-600 hover:border-red-300 hover:bg-red-50 dark:hover:border-red-900 dark:hover:bg-red-950' : 'text-green-700 hover:border-green-300 hover:bg-green-50 dark:text-green-300 dark:hover:border-green-900 dark:hover:bg-green-950'}`;
        button.append(makeIcon(active
          ? '<rect x="4" y="10" width="16" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 0 1 8 0v3"/>'
          : '<rect x="4" y="10" width="16" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 0 1 7.5-2"/>'));
        form.append(csrf, nextStatus, button);
        actions.append(form);
      }
      actionsCell.append(actions);
      row.append(actionsCell);
      serviceRows.append(row);
    });

    if (services.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 7;
      cell.className = 'px-5 py-12 text-center text-zinc-500 dark:text-zinc-400';
      cell.textContent = window.appTranslate('No services found for these filters.');
      row.append(cell);
      serviceRows.append(row);
    }

    const query = searchInput.value.trim();
    const status = statusFilter.value;
    resultCount.textContent = window.appTranslate('Showing {count} services{status}{matching}.', {
      count: services.length,
      status: status !== 'all' ? window.appTranslate(' with {status} status', { status: window.appTranslate(status[0].toUpperCase() + status.slice(1)) }) : '',
      matching: query ? window.appTranslate(' matching your search.') : '',
    });
  };

  const loadServices = async () => {
    pendingRequest?.abort();
    pendingRequest = new AbortController();
    const params = new URLSearchParams({ q: searchInput.value.trim(), status: statusFilter.value });
    const pageParams = new URLSearchParams(params);
    [...pageParams.keys()].forEach((key) => { if (pageParams.get(key) === '') pageParams.delete(key); });
    history.replaceState(null, '', `/services${pageParams.size ? `?${pageParams}` : ''}`);
    searchStatus.textContent = window.appTranslate('Loading services…');

    try {
      const response = await fetch(`/services/search?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pendingRequest.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || window.appTranslate('Could not load services.'));
      renderServices(result.services || []);
      searchStatus.textContent = window.appTranslate('{count} services shown.', { count: result.services?.length || 0 });
    } catch (error) {
      if (error.name === 'AbortError') return;
      searchStatus.textContent = error.message || window.appTranslate('Could not load services.');
    }
  };

  searchForm.addEventListener('submit', (event) => { event.preventDefault(); loadServices(); });
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadServices, 250);
  });
  statusFilter.addEventListener('change', loadServices);

  serviceRows.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-service-status-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('button');
    if (button) button.disabled = true;
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || window.appTranslate('Could not update service status.'));
      searchStatus.textContent = window.appTranslate(result.is_active ? 'Service activated.' : 'Service deactivated.');
      await loadServices();
    } catch (error) {
      searchStatus.textContent = error.message || window.appTranslate('Could not update service status.');
      if (button) button.disabled = false;
    }
  });
}
};
document.addEventListener('app:page-ready', initializeServicesPage);
