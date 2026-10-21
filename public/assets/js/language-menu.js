const languageMenus = document.querySelectorAll('.language-menu');

document.addEventListener('click', (event) => {
  languageMenus.forEach((menu) => {
    if (menu.open && !menu.contains(event.target)) menu.open = false;
  });
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') languageMenus.forEach((menu) => { menu.open = false; });
});
