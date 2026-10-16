(() => {
  const storageKey = 'laundry-theme';
  const savedTheme = localStorage.getItem(storageKey);
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');

  document.documentElement.classList.toggle('dark', initialTheme === 'dark');

  const syncThemeButton = (button) => {
    const isDark = document.documentElement.classList.contains('dark');
    button.setAttribute('aria-pressed', String(isDark));
    button.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    button.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      syncThemeButton(button);
      button.addEventListener('click', () => {
        const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        document.documentElement.classList.toggle('dark', nextTheme === 'dark');
        localStorage.setItem(storageKey, nextTheme);
        syncThemeButton(button);
      });
    });
  });
})();
