document.addEventListener('submit', (event) => {
  const form = event.target.closest('form[data-confirm]');
  if (!form) return;

  if (!window.confirm(form.dataset.confirm || 'Are you sure?')) {
    event.preventDefault();
  }
});
