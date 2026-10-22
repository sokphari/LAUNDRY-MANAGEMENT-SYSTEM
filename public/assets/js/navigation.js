(() => {
  let main = document.querySelector('main');
  const sidebarContent = document.querySelector('#app-sidebar .sidebar-content');
  const sidebarAccount = document.querySelector('#sidebar-account');
  const title = document.querySelector('header h1');
  const progress = document.querySelector('#app-navigation-progress');
  if (!main || !sidebarContent) return;

  let activeRequest = null;
  let navigationId = 0;
  const pageModules = new Map();
  const loadedModules = new Map();

  const registerPageModule = (path, modulePath) => pageModules.set(path, modulePath);
  registerPageModule(/^\/dashboard\/?$/, '/assets/js/dashboard.js');
  registerPageModule(/^\/customers\/?$/, '/assets/js/customers.js');
  registerPageModule(/^\/customers\/create\/?$/, '/assets/js/customer-create.js');
  registerPageModule(/^\/orders(?:\/.*)?\/?$/, '/assets/js/orders.js');
  registerPageModule(/^\/services(?:\/.*)?\/?$/, '/assets/js/services.js');
  registerPageModule(/^\/payments(?:\/.*)?\/?$/, '/assets/js/payments.js');
  registerPageModule(/^\/reports\/?$/, '/assets/js/reports.js');
  registerPageModule(/^\/audit-logs\/?$/, '/assets/js/audit-logs.js');

  const loadPageModule = async (target) => {
    const path = new URL(target, window.location.href).pathname;
    const modulePath = [...pageModules].find(([pattern]) => pattern.test(path))?.[1];
    if (!modulePath) return;
    if (!loadedModules.has(modulePath)) {
      loadedModules.set(modulePath, import(modulePath));
    }
    await loadedModules.get(modulePath);
  };

  const setLoading = (loading) => {
    main.setAttribute('aria-busy', String(loading));
    progress?.classList.toggle('is-active', loading);
  };

  const navigate = async (target, { push = true, scrollY = 0 } = {}) => {
    const url = new URL(target, window.location.href);
    if (url.origin !== window.location.origin) {
      window.location.assign(url.href);
      return;
    }

    activeRequest?.abort();
    activeRequest = new AbortController();
    const currentNavigation = ++navigationId;
    setLoading(true);

    try {
      const response = await fetch(url.href, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        signal: activeRequest.signal,
      });
      if (!response.ok) throw new Error(`Navigation failed (${response.status}).`);
      const html = await response.text();
      if (currentNavigation !== navigationId) return;

      const nextDocument = new DOMParser().parseFromString(html, 'text/html');
      const nextMain = nextDocument.querySelector('main');
      const nextSidebar = nextDocument.querySelector('#app-sidebar .sidebar-content');
      const nextSidebarAccount = nextDocument.querySelector('#sidebar-account');
      if (!nextMain || !nextSidebar || new URL(response.url).pathname === '/login') {
        window.location.assign(response.url || url.href);
        return;
      }

      if (push) {
        history.replaceState({ ...(history.state || {}), scrollY: window.scrollY }, '', window.location.href);
        history.pushState({ scrollY: 0 }, '', response.url);
      }

      const replacementMain = document.importNode(nextMain, true);
      main.replaceWith(replacementMain);
      main = replacementMain;
      sidebarContent.innerHTML = nextSidebar.innerHTML;
      if (sidebarAccount && nextSidebarAccount) sidebarAccount.innerHTML = nextSidebarAccount.innerHTML;
      document.title = nextDocument.title;
      document.documentElement.lang = nextDocument.documentElement.lang;
      document.documentElement.dataset.translations = nextDocument.documentElement.dataset.translations || '{}';
      if (title) title.textContent = nextDocument.querySelector('header h1')?.textContent || nextDocument.title;
      window.scrollTo({ top: scrollY, behavior: 'auto' });
      await loadPageModule(response.url);
      if (currentNavigation !== navigationId) return;
      document.dispatchEvent(new CustomEvent('app:page-ready', { detail: { url: response.url } }));
      main.focus({ preventScroll: true });
    } catch (error) {
      if (error.name === 'AbortError') return;
      window.location.assign(url.href);
    } finally {
      if (currentNavigation === navigationId) setLoading(false);
    }
  };

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest?.('a[href]');
    if (!link || link.target || link.hasAttribute('download') || link.relList.contains('external') || link.hasAttribute('data-no-ajax')) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin || url.hash && url.pathname === location.pathname && url.search === location.search) return;
    if (url.pathname.startsWith('/assets/') || url.pathname === '/logout' || url.pathname === '/language' || url.pathname.includes('/export') || url.searchParams.has('print')) return;
    event.preventDefault();
    navigate(url.href);
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get' || form.hasAttribute('data-no-ajax')) return;
    const action = new URL(form.action || window.location.href, window.location.href);
    if (action.origin !== window.location.origin) return;
    event.preventDefault();
    const query = new URLSearchParams(new FormData(form));
    action.search = query.toString();
    navigate(action.href);
  });

  window.addEventListener('popstate', (event) => {
    navigate(window.location.href, { push: false, scrollY: Number(event.state?.scrollY) || 0 });
  });

  const initialPageUrl = window.location.href;
  loadPageModule(initialPageUrl).then(() => {
    if (window.location.href === initialPageUrl) {
      document.dispatchEvent(new CustomEvent('app:page-ready', { detail: { url: initialPageUrl } }));
    }
  }).catch(() => {});
})();
