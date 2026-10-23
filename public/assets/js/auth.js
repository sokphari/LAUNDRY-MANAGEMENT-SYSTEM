document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!(input instanceof HTMLInputElement)) return;

    const showPassword = input.type === 'password';
    input.type = showPassword ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(showPassword));
    button.setAttribute('aria-label', showPassword ? button.dataset.hideLabel : button.dataset.showLabel);
    button.querySelector('[data-password-show-icon]')?.classList.toggle('hidden', showPassword);
    button.querySelector('[data-password-hide-icon]')?.classList.toggle('hidden', !showPassword);
  });
});

if (document.querySelector('[data-login-success]')) {
  window.setTimeout(() => window.location.replace('/dashboard'), 1600);
}
