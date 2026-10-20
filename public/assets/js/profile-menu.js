const profileMenus = [...document.querySelectorAll('.profile-menu')];

document.addEventListener('click', (event) => {
  profileMenus.forEach((menu) => {
    if (!menu.contains(event.target)) menu.open = false;
  });
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  profileMenus.forEach((menu) => {
    if (!menu.open) return;
    menu.open = false;
    menu.querySelector('summary')?.focus();
  });
});
