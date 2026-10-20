window.addEventListener('load', () => {
  document.querySelector('[data-print-report]')?.addEventListener('click', () => window.print());
  window.setTimeout(() => window.print(), 250);
});
