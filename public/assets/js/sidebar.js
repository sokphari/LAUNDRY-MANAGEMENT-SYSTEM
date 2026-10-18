document.querySelectorAll('.js-module-menu-toggle').forEach((button) => {
  button.addEventListener('click', () => {
    const submenu = document.getElementById(button.getAttribute('aria-controls'));
    const chevron = button.querySelector('svg');

    if (!submenu) return;

    const opening = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(opening));
    chevron?.classList.toggle('rotate-180', opening);

    if (opening) {
      submenu.hidden = false;
      requestAnimationFrame(() => submenu.classList.add('customer-submenu-open'));
    } else {
      submenu.classList.remove('customer-submenu-open');
      submenu.addEventListener('transitionend', () => {
        if (button.getAttribute('aria-expanded') === 'false') submenu.hidden = true;
      }, { once: true });
    }
  });
});
