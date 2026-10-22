const initializeReportsPage = () => {
  const form = document.getElementById('reports-filter');
  const range = document.getElementById('reports-range');
  if (!form || !range) return;

  document.querySelectorAll('[data-report-responsive]').forEach((table) => {
    const labels = [...table.tHead.rows[0].cells].map((cell) => cell.textContent.trim());
    table.tBodies[0]?.querySelectorAll('tr').forEach((row) => [...row.cells].forEach((cell, index) => {
      if (!cell.hasAttribute('colspan')) cell.dataset.label = labels[index] || '';
    }));
  });

  const boxes = [...document.querySelectorAll('[data-report-custom]')];
  const toggleCustomRange = () => boxes.forEach((element) => element.classList.toggle('hidden', range.value !== 'custom'));
  range.addEventListener('change', () => {
    toggleCustomRange();
    if (range.value !== 'custom') form.requestSubmit();
  });
  form.addEventListener('change', (event) => {
    if (event.target.matches('input[type=date]') && document.getElementById('reports-from').value && document.getElementById('reports-to').value) form.requestSubmit();
  });
};

document.addEventListener('app:page-ready', initializeReportsPage);
