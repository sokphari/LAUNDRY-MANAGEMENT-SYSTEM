(() => {
  const dialog = document.querySelector('#app-confirm-dialog');
  const message = dialog?.querySelector('[data-confirm-message]');
  const cancelButton = dialog?.querySelector('[data-confirm-cancel]');
  const acceptButton = dialog?.querySelector('[data-confirm-accept]');
  let pendingForm = null;

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (!form) return;

    if (form.dataset.confirmApproved === 'true') {
      delete form.dataset.confirmApproved;
      return;
    }

    if (!dialog || typeof dialog.showModal !== 'function' || !message) {
      if (!window.confirm(form.dataset.confirm || 'Are you sure?')) event.preventDefault();
      return;
    }

    event.preventDefault();
    pendingForm = form;
    message.textContent = form.dataset.confirm || '';
    dialog.showModal();
    cancelButton?.focus();
  });

  const closeDialog = () => {
    pendingForm = null;
    if (dialog?.open) dialog.close();
  };

  cancelButton?.addEventListener('click', closeDialog);
  dialog?.addEventListener('cancel', () => { pendingForm = null; });
  dialog?.addEventListener('click', (event) => {
    if (event.target === dialog) closeDialog();
  });
  acceptButton?.addEventListener('click', () => {
    const form = pendingForm;
    pendingForm = null;
    if (!form?.isConnected) {
      closeDialog();
      return;
    }
    dialog.close();
    form.dataset.confirmApproved = 'true';
    form.requestSubmit();
  });
})();
