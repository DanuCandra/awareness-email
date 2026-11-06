'use strict';

(function (root, factory) {
  const api = factory(root.AwarenessPreview, root.crypto);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.AwarenessAdmins = api;
})(typeof window === 'undefined' ? globalThis : window, function (preview, cryptoApi) {
  const storageKey = 'security-awareness.admins.v1', sessionKey = 'security-awareness.admin-session.v1';
  const demo = { id: 'admin-demo', name: 'Administrator', username: 'admin', email: 'admin@ast-indonesia.example', password: 'Admin123!' };
  const normalize = value => String(value ?? '').trim().toLowerCase();
  const publicAdmin = item => ({ id: item.id, name: item.name, username: item.username, email: item.email, createdAt: item.createdAt });
  const hex = buffer => [...new Uint8Array(buffer)].map(value => value.toString(16).padStart(2, '0')).join('');

  function load(storage) {
    try {
      const raw = storage.getItem(storageKey);
      if (!raw || raw.length > 1000000) return null;
      const state = JSON.parse(raw);
      if (state.version !== 1 || !Array.isArray(state.admins) || !state.admins.length) return null;
      if (!state.admins.every(item => /^[a-z0-9-]+$/.test(item.id) && typeof item.name === 'string' && /^[a-z0-9._-]{3,30}$/.test(item.username) && preview.isValidEmail(item.email) && /^[a-f0-9]{32}$/.test(item.salt) && /^[a-f0-9]{64}$/.test(item.hash))) return null;
      if (new Set(state.admins.map(item => item.id)).size !== state.admins.length || new Set(state.admins.map(item => normalize(item.email))).size !== state.admins.length || new Set(state.admins.map(item => normalize(item.username))).size !== state.admins.length) return null;
      return state;
    } catch { return null; }
  }

  async function passwordHash(password, salt) {
    if (!cryptoApi?.subtle) throw new Error('Pratinjau login memerlukan localhost atau HTTPS.');
    const bytes = Uint8Array.from(salt.match(/../g), value => parseInt(value, 16));
    const material = await cryptoApi.subtle.importKey('raw', new TextEncoder().encode(password), 'PBKDF2', false, ['deriveBits']);
    return hex(await cryptoApi.subtle.deriveBits({ name: 'PBKDF2', salt: bytes, iterations: 100000, hash: 'SHA-256' }, material, 256));
  }

  async function credentials(password) {
    if (!cryptoApi?.subtle) throw new Error('Pratinjau login memerlukan localhost atau HTTPS.');
    const salt = hex(cryptoApi.getRandomValues(new Uint8Array(16)));
    return { salt, hash: await passwordHash(password, salt) };
  }

  function persist(storage, state) { storage.setItem(storageKey, JSON.stringify(state)); }

  async function initialize(storage) {
    const existing = load(storage);
    if (existing) return existing;
    const { password, ...admin } = demo;
    const seed = { ...admin, ...await credentials(password), createdAt: '2026-11-06T00:00:00Z' };
    const latest = load(storage);
    if (latest) return latest;
    const state = { version: 1, admins: [seed] };
    persist(storage, state); return state;
  }

  function validate(input, admins, editingId = '') {
    const errors = {}, name = String(input.name || '').trim(), username = normalize(input.username), email = normalize(input.email);
    if (!name || name.length > 100) errors.name = 'Isi nama admin, maksimal 100 karakter.';
    if (!/^[a-z0-9._-]{3,30}$/.test(username)) errors.username = 'Username 3–30 karakter: huruf, angka, titik, tanda hubung, atau garis bawah.';
    else if (admins.some(item => item.id !== editingId && normalize(item.username) === username)) errors.username = 'Username sudah digunakan oleh admin lain.';
    if (!preview.isValidEmail(email)) errors.email = 'Masukkan alamat email yang valid.';
    else if (admins.some(item => item.id !== editingId && normalize(item.email) === email)) errors.email = 'Email sudah digunakan oleh admin lain.';
    const password = String(input.password || '');
    if (!editingId || password || input.passwordConfirmation) {
      if (password.length < 8 || password.length > 128) errors.password = 'Password minimal 8 karakter dan maksimal 128 karakter.';
      if (password !== input.passwordConfirmation) errors.passwordConfirmation = 'Konfirmasi password belum sama.';
    }
    return errors;
  }

  function session(storage) {
    try {
      const value = JSON.parse(storage.getItem(sessionKey));
      const item = load(storage)?.admins.find(admin => admin.id === value?.adminId && admin.hash === value?.revision);
      return item ? publicAdmin(item) : null;
    } catch { return null; }
  }

  async function saveAdmin(storage, input, editingId = '', now = new Date().toISOString()) {
    let state = await initialize(storage);
    let errors = validate(input, state.admins, editingId);
    if (Object.keys(errors).length) return { saved: false, errors };
    if (editingId && !state.admins.some(item => item.id === editingId)) return { saved: false, errors: {}, message: 'Admin tidak ditemukan. Muat ulang daftar.' };
    const secret = input.password ? await credentials(input.password) : null;
    state = load(storage);
    if (!state) throw new Error('Data pratinjau tidak tersedia. Muat ulang halaman.');
    errors = validate(input, state.admins, editingId);
    if (Object.keys(errors).length) return { saved: false, errors };
    const existing = editingId ? state.admins.find(item => item.id === editingId) : null;
    if (editingId && !existing) return { saved: false, errors: {}, message: 'Admin tidak ditemukan. Muat ulang daftar.' };
    const current = session(storage);
    const item = { ...existing, id: editingId || 'admin-' + cryptoApi.randomUUID(), name: input.name.trim(), username: normalize(input.username), email: normalize(input.email), ...(secret || {}), createdAt: existing?.createdAt || now };
    if (existing) state.admins[state.admins.findIndex(admin => admin.id === editingId)] = item; else state.admins.push(item);
    persist(storage, state);
    return { saved: true, admin: publicAdmin(item), errors: {}, sessionInvalidated: !!secret && current?.id === editingId };
  }

  function removeAdmin(storage, id) {
    const state = load(storage);
    if (!state?.admins.some(item => item.id === id)) throw new Error('Admin tidak ditemukan.');
    if (state.admins.length === 1) throw new Error('Minimal satu akun admin harus tetap tersedia.');
    if (session(storage)?.id === id) throw new Error('Akun yang sedang digunakan tidak dapat dihapus.');
    state.admins = state.admins.filter(item => item.id !== id); persist(storage, state);
  }

  async function login(storage, identifier, password) {
    const state = await initialize(storage), value = normalize(identifier);
    const item = state.admins.find(admin => normalize(admin.username) === value || normalize(admin.email) === value);
    if (!item || typeof password !== 'string' || password.length < 8 || password.length > 128 || await passwordHash(password, item.salt) !== item.hash) return null;
    const latest = load(storage)?.admins.find(admin => admin.id === item.id && admin.hash === item.hash);
    if (!latest) return null;
    storage.setItem(sessionKey, JSON.stringify({ adminId: latest.id, revision: latest.hash }));
    return publicAdmin(latest);
  }

  function logout(storage) { storage.removeItem(sessionKey); }
  return { storageKey, sessionKey, demo, load, initialize, validate, session, saveAdmin, removeAdmin, login, logout, publicAdmin };
});
