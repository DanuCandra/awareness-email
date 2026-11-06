'use strict';

window.AwarenessApi = (() => {
  const configuration = { ...JSON.parse(document.getElementById('master-endpoints')?.textContent || '{}'), ...JSON.parse(document.getElementById('settings-endpoints')?.textContent || '{}') };
  let settingsState = JSON.parse(document.getElementById('workspace-settings')?.textContent || 'null');
  const settingsSnapshot = () => settingsState ? JSON.parse(JSON.stringify(settingsState)) : null;
  const setSettings = value => { settingsState = JSON.parse(JSON.stringify(value)); };
  function newRequestToken() {
    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
  }
  async function request(url, { method = 'GET', data } = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' };
    const options = { method, headers, credentials: 'same-origin' };
    if (data instanceof FormData) options.body = data;
    else if (data !== undefined) { headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data); }
    const response = await fetch(url, options);
    if ([401, 419].includes(response.status)) {
      window.location.assign(configuration.login);
      throw new Error('Sesi berakhir. Silakan login kembali.');
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      const errors = Object.fromEntries(Object.entries(result.errors || {}).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages]));
      const error = new Error(Object.values(errors)[0] || (response.status === 429 ? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.' : 'Permintaan belum berhasil. Silakan coba lagi.'));
      error.errors = errors; throw error;
    }
    return result;
  }
  async function library() {
    const data = await request(configuration.templates);
    return { templates: data.templates, categories: data.categories.map(item => item.name), categoryRecords: data.categories };
  }
  function showLoadError(root, message) {
    let alert = root.querySelector('[data-load-error]');
    if (!alert) { alert = document.createElement('div'); alert.className = 'alert alert-danger'; alert.setAttribute('role', 'alert'); alert.dataset.loadError = ''; root.prepend(alert); }
    alert.textContent = message + ' Muat ulang halaman untuk mencoba kembali.';
  }
  return { urls: configuration, request, library, showLoadError, settingsSnapshot, setSettings, newRequestToken };
})();
