(() => {
  const root = document.querySelector('[data-notifications]');
  if (!root) return;

  const toggle = root.querySelector('#notifications-toggle');
  const panel = root.querySelector('#notifications-panel');
  const list = root.querySelector('[data-notification-list]');
  const badge = root.querySelector('[data-notification-badge]');
  const markReadButton = root.querySelector('[data-notification-mark-read]');
  if (!toggle || !panel || !list || !badge || !markReadButton) return;

  let unreadCount = 0;
  let requestController;

  const updateBadge = (count, failedAttempts = 0) => {
    const unreadAlerts = Math.max(0, Number(count) || 0);
    const recentFailures = Math.max(0, Number(failedAttempts) || 0);
    unreadCount = unreadAlerts;
    badge.hidden = unreadCount === 0;
    badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
    toggle.setAttribute('aria-label', recentFailures > 0
      ? `Notifications, ${unreadAlerts} unread, ${recentFailures} failed sign-in attempts in the last 15 minutes`
      : unreadAlerts > 0 ? `Notifications, ${unreadAlerts} unread` : 'Notifications');
    markReadButton.hidden = unreadAlerts === 0;
  };

  const showMessage = (message) => {
    const paragraph = document.createElement('p');
    paragraph.className = 'px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400';
    paragraph.setAttribute('role', 'status');
    paragraph.textContent = message;
    list.replaceChildren(paragraph);
  };

  const renderNotifications = (notifications) => {
    if (!Array.isArray(notifications) || notifications.length === 0) {
      showMessage('No security activity yet.');
      return;
    }

    const fragment = document.createDocumentFragment();
    notifications.forEach((notification) => {
      const item = document.createElement('article');
      item.setAttribute('role', 'listitem');
      item.className = `flex gap-3 border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-zinc-800${notification.is_unread ? ' bg-violet-50/70 dark:bg-violet-950/20' : ''}`;

      const marker = document.createElement('span');
      marker.className = `mt-1.5 size-2 shrink-0 rounded-full ${notification.is_unread ? 'bg-primary' : 'bg-zinc-300 dark:bg-zinc-700'}`;
      marker.setAttribute('aria-hidden', 'true');

      const content = document.createElement('div');
      content.className = 'min-w-0 flex-1';
      const title = document.createElement('p');
      title.className = 'text-sm font-medium text-zinc-900 dark:text-zinc-100';
      title.textContent = t(String(notification.summary || notification.action_label || 'Security event'));
      if (notification.action === 'auth.login_failed' && Number(notification.failed_attempts) > 0) {
        const count = document.createElement('span');
        count.className = 'mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-300';
        count.textContent = `${Number(notification.failed_attempts)} failed attempt${Number(notification.failed_attempts) === 1 ? '' : 's'} in the last 15 minutes`;
        title.append(document.createElement('br'), count);
      }
      const metadata = document.createElement('p');
      metadata.className = 'mt-1 text-xs text-zinc-500 dark:text-zinc-400';
      const actor = notification.actor ? String(notification.actor) : t('Unverified sign-in');
      const timestamp = String(notification.created_display || '');
      metadata.textContent = timestamp ? `${actor} · ${timestamp}` : actor;
      content.append(title, metadata);
      item.append(marker, content);
      fragment.append(item);
    });
    list.replaceChildren(fragment);
  };

  const fetchNotifications = async (markRead = false) => {
    requestController?.abort();
    requestController = new AbortController();
    const url = markRead ? root.dataset.readEndpoint : root.dataset.endpoint;
    const options = {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      signal: requestController.signal,
    };
    if (markRead) {
      options.method = 'POST';
      options.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
      options.body = new URLSearchParams({ _csrf_token: root.dataset.csrfToken || '' });
    }

    try {
      const response = await fetch(url, options);
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Notifications are temporarily unavailable.');
      updateBadge(result.unread_count, result.failed_attempt_count);
      if (panel && !panel.hidden) renderNotifications(result.notifications);
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (!panel.hidden) showMessage(t(error.message || 'Notifications are temporarily unavailable.'));
    }
  };

  toggle.addEventListener('click', () => {
    const willOpen = panel.hidden;
    panel.hidden = !willOpen;
    toggle.setAttribute('aria-expanded', String(willOpen));
    if (willOpen) fetchNotifications();
  });

  markReadButton.addEventListener('click', () => fetchNotifications(true));

  document.addEventListener('click', (event) => {
    if (!panel.hidden && !root.contains(event.target)) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });

  const refresh = () => {
    if (document.visibilityState === 'visible') fetchNotifications();
  };
  fetchNotifications();
  window.setInterval(refresh, 15000);
  document.addEventListener('visibilitychange', refresh);
})();
