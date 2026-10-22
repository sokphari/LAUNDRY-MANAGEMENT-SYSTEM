const initializeReportsPage = () => {
  const form = document.getElementById('reports-filter');
  const range = document.getElementById('reports-range');
  if (!form || !range) return;
(() => { const form=document.getElementById('reports-filter'); const range=document.getElementById('reports-range'); const boxes=[...document.querySelectorAll('[data-report-custom]')]; const toggle=()=>boxes.forEach(el=>el.classList.toggle('hidden',range.value!=='custom')); range.addEventListener('change',()=>{toggle(); if(range.value!=='custom') form.requestSubmit();}); form.addEventListener('change',e=>{if(e.target.matches('input[type=date]')&&document.getElementById('reports-from').value&&document.getElementById('reports-to').value) form.requestSubmit();});
})();
};
document.addEventListener('app:page-ready', initializeReportsPage);
