'use strict';
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('adminLoginForm');
  if (!form) return;
  form.addEventListener('submit', () => {
    const button = document.getElementById('adminLoginSubmit');
    button.disabled = true;
    button.textContent = 'Memeriksa akun…';
  });
  window.addEventListener('pageshow', () => {
    const button = document.getElementById('adminLoginSubmit');
    button.disabled = false;
    button.textContent = 'Masuk';
  });
});
