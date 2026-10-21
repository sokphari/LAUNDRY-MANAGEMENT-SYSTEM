const sidebar = document.querySelector('#app-sidebar');
const sidebarToggle = document.querySelector('#sidebar-toggle');
const sidebarBackdrop = document.querySelector('#sidebar-backdrop');
const desktopSidebarQuery = window.matchMedia('(min-width: 768px)');
const sidebarStorageKey = 'laundry-sidebar-collapsed';
const sidebarText = document.documentElement.lang === 'km'
  ? { open: 'បើកម៉ឺនុយ', close: 'បិទម៉ឺនុយ', collapse: 'បង្រួមម៉ឺនុយ', expand: 'ពង្រីកម៉ឺនុយ' }
  : { open: 'Open navigation', close: 'Close navigation', collapse: 'Collapse navigation', expand: 'Expand navigation' };

let sidebarCollapsed = false;
try {
  sidebarCollapsed = localStorage.getItem(sidebarStorageKey) === 'true';
} catch {}

const syncSidebar = () => {
  const isDesktop = desktopSidebarQuery.matches;
  const mobileOpen = sidebar?.classList.contains('sidebar-open') || false;
  document.body.classList.toggle('sidebar-collapsed', isDesktop && sidebarCollapsed);
  if (sidebar) {
    sidebar.inert = !isDesktop && !mobileOpen;
    sidebar.setAttribute('aria-hidden', String(!isDesktop && !mobileOpen));
  }
  sidebarToggle?.setAttribute('aria-expanded', String(isDesktop ? !sidebarCollapsed : mobileOpen));
  const label = isDesktop
    ? (sidebarCollapsed ? sidebarText.expand : sidebarText.collapse)
    : (mobileOpen ? sidebarText.close : sidebarText.open);
  sidebarToggle?.setAttribute('aria-label', label);
  sidebarToggle?.setAttribute('title', label);
  sidebarBackdrop?.classList.toggle('hidden', isDesktop || !mobileOpen);
};

sidebarToggle?.addEventListener('click', () => {
  if (desktopSidebarQuery.matches) {
    sidebarCollapsed = !sidebarCollapsed;
    try {
      localStorage.setItem(sidebarStorageKey, String(sidebarCollapsed));
    } catch {}
  } else {
    sidebar?.classList.toggle('sidebar-open');
    document.body.classList.toggle('overflow-hidden', sidebar?.classList.contains('sidebar-open') || false);
  }
  syncSidebar();
});

const closeMobileSidebar = () => {
  if (desktopSidebarQuery.matches) return;
  sidebar?.classList.remove('sidebar-open');
  document.body.classList.remove('overflow-hidden');
  syncSidebar();
};

sidebarBackdrop?.addEventListener('click', closeMobileSidebar);
sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobileSidebar));
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeMobileSidebar();
});
desktopSidebarQuery.addEventListener('change', () => {
  sidebar?.classList.remove('sidebar-open');
  document.body.classList.remove('overflow-hidden');
  syncSidebar();
});
syncSidebar();

document.querySelectorAll('.js-module-menu-toggle').forEach((button) => {
  button.addEventListener('click', () => {
    const submenu = document.getElementById(button.getAttribute('aria-controls'));
    const chevron = button.querySelector('.module-group-chevron');

    if (!submenu) return;

    if (desktopSidebarQuery.matches && document.body.classList.contains('sidebar-collapsed')) {
      sidebarCollapsed = false;
      try {
        localStorage.setItem(sidebarStorageKey, 'false');
      } catch {}
      syncSidebar();
      return;
    }

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
