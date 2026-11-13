(() => {
  'use strict';
  const list = document.getElementById('block-list');
  const add = document.querySelector('[data-add-block]');
  if (list && add) {
    add.addEventListener('click', () => {
      const source = list.querySelector('[data-block]');
      if (!source) return;
      const row = source.cloneNode(true);
      row.querySelectorAll('textarea,input').forEach((field) => { field.value = ''; });
      row.querySelectorAll('select').forEach((field) => { field.selectedIndex = 0; });
      list.appendChild(row);
      row.querySelector('select,textarea,input')?.focus();
    });
    list.addEventListener('click', (event) => {
      const button = event.target.closest('.remove-block');
      if (!button) return;
      const rows = list.querySelectorAll('[data-block]');
      if (rows.length === 1) {
        rows[0].querySelectorAll('textarea,input').forEach((field) => { field.value = ''; });
        return;
      }
      button.closest('[data-block]')?.remove();
    });
  }
  document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
  });
})();
