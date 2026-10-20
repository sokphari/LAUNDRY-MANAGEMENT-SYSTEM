const dashboardRange = document.querySelector('[data-dashboard-range]');
const dashboardCustomRangeFields = document.querySelectorAll('[data-dashboard-custom-range]');
const dashboardForm = document.querySelector('#dashboard-filter-form');
const dashboardStatus = document.querySelector('#dashboard-filter-status');
const dashboardReset = document.querySelector('[data-dashboard-reset]');
const dashboardClock = document.querySelector('#dashboard-live-clock');
const dashboardNamespace = 'http://www.w3.org/2000/svg';
let dashboardRequest;

const updateDashboardCustomRange = () => {
  const isCustom = dashboardRange?.value === 'custom';
  dashboardCustomRangeFields.forEach((field) => {
    field.classList.toggle('hidden', !isCustom);
    field.querySelectorAll('input').forEach((input) => {
      input.disabled = !isCustom;
    });
  });
};

const setSvgAttributes = (element, attributes) => {
  Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, String(value)));
  return element;
};

const makeSvgElement = (name, attributes = {}, text = '') => {
  const element = setSvgAttributes(document.createElementNS(dashboardNamespace, name), attributes);
  if (text) element.textContent = text;
  return element;
};

const formatAxisValue = (value) => {
  const absolute = Math.abs(value);
  if (absolute >= 1000000) return `${(value / 1000000).toFixed(1)}M`;
  if (absolute >= 1000) return `${Math.round(value / 1000)}K`;
  return Math.round(value).toLocaleString('en-US');
};

const renderRevenueChart = (monthlyData, year) => {
  const chart = document.querySelector('#dashboard-revenue-chart');
  if (!chart) return;
  chart.replaceChildren();

  const amounts = monthlyData.map((month) => Number(month.amount) || 0);
  const minimum = Math.min(0, ...amounts);
  let maximum = Math.max(0, ...amounts);
  if (maximum === minimum) maximum = maximum === 0 ? 1000 : minimum + 1;
  const range = maximum - minimum;
  const zeroY = 210 - ((0 - minimum) / range * 165);
  const points = monthlyData.map((month, index) => ({
    x: 66 + (index * 46),
    y: 210 - (((Number(month.amount) || 0) - minimum) / range * 165),
    month: String(month.month || ''),
    amount: Number(month.amount) || 0,
  }));

  const defs = makeSvgElement('defs');
  const gradient = makeSvgElement('linearGradient', { id: 'dashboard-revenue-fill', x1: 0, x2: 0, y1: 0, y2: 1 });
  gradient.append(
    makeSvgElement('stop', { offset: '0%', 'stop-color': '#7C3AED', 'stop-opacity': 0.22 }),
    makeSvgElement('stop', { offset: '100%', 'stop-color': '#7C3AED', 'stop-opacity': 0 }),
  );
  defs.append(gradient);
  chart.append(defs);

  const grid = makeSvgElement('g', { stroke: 'currentColor', class: 'text-zinc-100 dark:text-zinc-800', 'stroke-width': 1 });
  const labels = makeSvgElement('g', { fill: 'currentColor', class: 'text-zinc-400', 'font-size': 11, 'text-anchor': 'end' });
  [0, 1, 2, 3].forEach((index) => {
    const y = 45 + (index * 55);
    const value = maximum - (range * index / 3);
    grid.append(makeSvgElement('line', { x1: 58, y1: y, x2: 620, y2: y }));
    labels.append(makeSvgElement('text', { x: 50, y: y + 4 }, formatAxisValue(value)));
  });
  grid.append(makeSvgElement('line', { x1: 58, y1: zeroY, x2: 620, y2: zeroY, 'stroke-dasharray': '3 3' }));
  chart.append(grid, labels);

  if (points.length > 0) {
    const pointString = points.map((point) => `${point.x},${point.y}`).join(' ');
    const areaPath = `M ${points[0].x} ${zeroY} L ${pointString.replaceAll(' ', ' L ')} L ${points.at(-1).x} ${zeroY} Z`;
    chart.append(makeSvgElement('path', { d: areaPath, fill: 'url(#dashboard-revenue-fill)' }));
    chart.append(makeSvgElement('polyline', { points: pointString, fill: 'none', stroke: '#7C3AED', 'stroke-width': 3, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
    points.forEach((point) => {
      const circle = makeSvgElement('circle', { cx: point.x, cy: point.y, r: 4, fill: '#fff', stroke: '#7C3AED', 'stroke-width': 3 });
      circle.append(makeSvgElement('title', {}, `${point.month} ${year}: ${Math.round(point.amount).toLocaleString('en-US')} KHR net payments`));
      chart.append(circle, makeSvgElement('text', { x: point.x, y: 238, fill: 'currentColor', class: 'text-zinc-500', 'font-size': 11, 'text-anchor': 'middle' }, point.month));
    });
  }
  chart.setAttribute('aria-label', `Line chart showing monthly net payments for ${year}`);
};

const renderOrderStatus = (statuses, rangeLabel) => {
  const chart = document.querySelector('#dashboard-status-chart');
  const totalElement = document.querySelector('#dashboard-status-total');
  const legend = document.querySelector('#dashboard-status-legend');
  if (!chart || !totalElement || !legend) return;
  chart.replaceChildren();
  const total = statuses.reduce((sum, status) => sum + (Number(status.value) || 0), 0);
  const circumference = 2 * Math.PI * 36;
  chart.append(makeSvgElement('circle', { cx: 70, cy: 70, r: 36, fill: 'none', stroke: 'currentColor', class: 'text-zinc-100 dark:text-zinc-800', 'stroke-width': 14 }));
  let offset = 0;
  statuses.forEach((status) => {
    const value = Number(status.value) || 0;
    const length = total > 0 ? value / total * circumference : 0;
    chart.append(makeSvgElement('circle', {
      cx: 70, cy: 70, r: 36, fill: 'none', stroke: status.color,
      'stroke-width': 14, 'stroke-dasharray': `${length} ${circumference - length}`, 'stroke-dashoffset': -offset,
    }));
    offset += length;
  });
  chart.setAttribute('aria-label', `Pie chart showing ${total} orders by status`);
  totalElement.textContent = total.toLocaleString('en-US');
  document.querySelector('#dashboard-status-range').textContent = `Orders received: ${rangeLabel}`;

  legend.replaceChildren();
  statuses.forEach((status) => {
    const row = document.createElement('li');
    row.className = 'flex items-center justify-between gap-3 text-sm';
    const label = document.createElement('span');
    label.className = 'flex items-center gap-2 text-zinc-600 dark:text-zinc-300';
    label.append(makeSvgElement('svg', { class: 'size-2.5 shrink-0', viewBox: '0 0 10 10', 'aria-hidden': 'true' }, ''));
    const dot = label.firstElementChild;
    dot.append(makeSvgElement('circle', { cx: 5, cy: 5, r: 5, fill: status.color }));
    label.append(document.createTextNode(status.label));
    const value = document.createElement('span');
    value.className = 'font-semibold';
    value.textContent = (Number(status.value) || 0).toLocaleString('en-US');
    row.append(label, value);
    legend.append(row);
  });
};

const renderDashboard = (data) => {
  data.stats.forEach((stat, index) => {
    const card = document.querySelector(`[data-dashboard-stat="${index}"]`);
    if (!card) return;
    card.querySelector('[data-dashboard-stat-label]').textContent = stat.label;
    card.querySelector('[data-dashboard-stat-value]').textContent = stat.value;
    card.querySelector('[data-dashboard-stat-note]').textContent = stat.note;
  });
  renderRevenueChart(data.yearlyRevenue, data.selectedYear);
  renderOrderStatus(data.orderStatuses, data.rangeLabel);
  document.querySelectorAll('[data-dashboard-custom-range] input').forEach((input) => {
    if (input.name === 'from') input.value = data.fromDate || '';
    if (input.name === 'to') input.value = data.toDate || '';
  });
};

const loadDashboard = async () => {
  if (!dashboardForm) return;
  dashboardRequest?.abort();
  dashboardRequest = new AbortController();
  const params = new URLSearchParams(new FormData(dashboardForm));
  let url = `${dashboardForm.action}?${params.toString()}`;
  dashboardStatus.textContent = 'Updating dashboard…';
  dashboardForm.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      signal: dashboardRequest.signal,
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Could not update dashboard.');
    renderDashboard(data);
    if (params.get('range') === 'custom') {
      if (data.fromDate) params.set('from', data.fromDate);
      if (data.toDate) params.set('to', data.toDate);
      url = `${dashboardForm.action}?${params.toString()}`;
    }
    history.replaceState(null, '', url);
    dashboardStatus.textContent = 'Dashboard updated.';
  } catch (error) {
    if (error.name === 'AbortError') return;
    dashboardStatus.textContent = error.message || 'Could not update dashboard.';
  } finally {
    dashboardForm.removeAttribute('aria-busy');
  }
};

dashboardForm?.addEventListener('submit', (event) => {
  event.preventDefault();
  loadDashboard();
});
dashboardRange?.addEventListener('change', () => {
  updateDashboardCustomRange();
  if (dashboardRange.value !== 'custom') loadDashboard();
});
dashboardForm?.querySelector('[name="year"]')?.addEventListener('change', loadDashboard);
dashboardForm?.querySelectorAll('[data-dashboard-custom-range] input').forEach((input) => {
  input.addEventListener('change', () => {
    const from = dashboardForm.querySelector('[name="from"]');
    const to = dashboardForm.querySelector('[name="to"]');
    if (from.value && to.value) loadDashboard();
  });
});
dashboardReset?.addEventListener('click', () => {
  if (!dashboardForm || !dashboardRange) return;
  dashboardRange.value = 'today';
  dashboardForm.querySelector('[name="year"]').value = dashboardForm.dataset.currentYear || '';
  dashboardForm.querySelector('[name="from"]').value = '';
  dashboardForm.querySelector('[name="to"]').value = '';
  updateDashboardCustomRange();
  loadDashboard();
});

const updateDashboardClock = () => {
  if (!dashboardClock) return;
  const timezone = dashboardClock.dataset.timezone || 'Asia/Phnom_Penh';
  const now = new Date();
  const date = new Intl.DateTimeFormat('en-US', { timeZone: timezone, month: 'short', day: 'numeric', year: 'numeric' }).format(now);
  const time = new Intl.DateTimeFormat('en-GB', { timeZone: timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(now);
  dashboardClock.textContent = `${date} · ${time}`;
  dashboardClock.dateTime = now.toISOString();
};
updateDashboardClock();
window.setInterval(updateDashboardClock, 1000);
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) updateDashboardClock();
});

updateDashboardCustomRange();
