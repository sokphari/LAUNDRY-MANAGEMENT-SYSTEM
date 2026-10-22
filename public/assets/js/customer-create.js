const initializeCustomerCreatePage = () => {
  const form = document.querySelector('form[data-customer-create]');
  const status = document.querySelector('#customer-create-status');
  if (!form || !status) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('[type="submit"]');
    if (submitButton?.disabled) return;

    if (submitButton) submitButton.disabled = true;
    status.className = 'mb-5 rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-100';
    status.textContent = window.appTranslate('Saving customer…');
    status.hidden = false;

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const result = await response.json();
      if (!response.ok) {
        const firstInvalidField = Object.keys(result.errors || {})[0];
        throw Object.assign(new Error(result.message || 'Could not save the customer.'), { field: firstInvalidField });
      }

      form.reset();
      status.className = 'mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100';
      status.textContent = window.appTranslate(result.message || 'Customer added successfully.');
      form.querySelector('[name="name"]')?.focus();
    } catch (error) {
      status.className = 'mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100';
      status.textContent = window.appTranslate(error.message || 'Could not save the customer. Please try again.');
      if (error.field) form.querySelector(`[name="${CSS.escape(error.field)}"]`)?.focus();
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
};
document.addEventListener('app:page-ready', initializeCustomerCreatePage);
