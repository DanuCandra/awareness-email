'use strict';
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-open-customizer]').forEach(button => {
    button.addEventListener('click', () => document.querySelector('.template-customizer-open-btn')?.click());
  });
  const element = document.getElementById('authenticated-admin');
  if (!element) return;
  let current = JSON.parse(element.textContent);
  function paintSession() {
    document.querySelectorAll('[data-admin-session-name]').forEach(node => { node.textContent = current.name; });
    document.querySelectorAll('[data-admin-session-username]').forEach(node => { node.textContent = '@' + current.username; });
  }
  document.addEventListener('awareness-admins-changed', event => { if (event.detail?.admin) { current = event.detail.admin; paintSession(); } });
  paintSession();
});
