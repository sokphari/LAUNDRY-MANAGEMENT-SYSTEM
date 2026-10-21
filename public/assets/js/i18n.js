(() => {
  let translations = {};
  try {
    translations = JSON.parse(document.documentElement.dataset.translations || '{}');
  } catch {}

  window.appTranslate = (message, values = {}) => Object.entries(values).reduce(
    (text, [key, value]) => text.replaceAll(`{${key}}`, String(value)),
    translations[message] || message,
  );

  // Translate exact static labels in server-rendered views as well as text added
  // later by AJAX. Record values and arbitrary customer-entered content are left
  // untouched because only strings with an explicit dictionary entry are changed.
  if (document.documentElement.lang === 'km') {
    const translateNode = (root) => {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      const textNodes = [];
      while (walker.nextNode()) textNodes.push(walker.currentNode);
      textNodes.forEach((node) => {
        const original = node.nodeValue;
        const trimmed = original.trim();
        const parent = node.parentElement;
        const staticLabel = parent && ['LABEL', 'BUTTON', 'OPTION', 'DT', 'H1', 'H2', 'H3', 'H4', 'LEGEND'].includes(parent.tagName);
        const staticInlineLabel = parent?.tagName === 'SPAN' && parent.closest('label') && !parent.closest('table');
        const staticLink = parent?.tagName === 'A' && !parent.closest('table') && !parent.closest('[data-user-content]');
        const staticTableHeading = parent?.tagName === 'TH' && parent.closest('thead');
        const staticCopy = parent?.tagName === 'P' && !parent.closest('table') && !parent.closest('[data-user-content]');
        if ((staticLabel || staticInlineLabel || staticLink || staticTableHeading || staticCopy) && trimmed && translations[trimmed]) {
          const start = original.indexOf(trimmed);
          node.nodeValue = `${original.slice(0, start)}${translations[trimmed]}${original.slice(start + trimmed.length)}`;
        }
      });

      const elements = root.nodeType === Node.ELEMENT_NODE ? [root, ...root.querySelectorAll('*')] : [...root.querySelectorAll('*')];
      elements.forEach((element) => ['placeholder', 'title', 'aria-label', 'aria-description'].forEach((attribute) => {
        const value = element.getAttribute(attribute);
        if (value && translations[value]) element.setAttribute(attribute, translations[value]);
      }));
    };

    translateNode(document.body);
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.DOCUMENT_FRAGMENT_NODE) translateNode(node);
    }))).observe(document.body, { childList: true, subtree: true });
  }
})();
