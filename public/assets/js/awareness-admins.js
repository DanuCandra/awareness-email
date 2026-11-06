'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const root = document.getElementById('adminWorkspace');
  if (!root) return;
  const preview = window.AwarenessPreview, ui = window.AwarenessComposeUI, escape = preview.escapeHtml;
  let state, editingId = '', busy = false;
  let current = JSON.parse(document.getElementById('authenticated-admin').textContent);
  async function request(url, method = 'GET', data = null) {
    const response = await fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, ...(data ? { body: JSON.stringify(data) } : {}) });
    if ([401, 419].includes(response.status)) { window.location.assign(root.dataset.loginUrl); throw new Error('Sesi berakhir. Silakan masuk kembali.'); }
    const result = await response.json();
    if (response.status === 422) {
      const errors = Object.fromEntries(Object.entries(result.errors || {}).map(([key, messages]) => [key === 'password_confirmation' ? 'passwordConfirmation' : key, messages[0]]));
      return { saved: false, errors, message: result.message };
    }
    if (!response.ok) throw new Error(result.message || 'Permintaan ke server gagal.');
    return result;
  }
  const core = {
    session: () => current,
    load: () => state,
    publicAdmin: item => item,
    initialize: async () => { const result = await request(root.dataset.adminsUrl); state = { admins: result.data }; current = result.current_admin; },
    saveAdmin: async (storage, values, id) => {
      const { passwordConfirmation, ...data } = values;
      const result = await request(id ? root.dataset.adminsUrl + '/' + id : root.dataset.adminsUrl, id ? 'PUT' : 'POST', { ...data, password_confirmation: passwordConfirmation });
      return result.saved === false ? result : { saved: true, sessionInvalidated: result.sessionInvalidated };
    },
    removeAdmin: async (storage, id) => { const result = await request(root.dataset.adminsUrl + '/' + id, 'DELETE'); if (result.saved === false) throw new Error(Object.values(result.errors)[0] || result.message); }
  };
  const modalElement = ui.find('adminEditorModal'), modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const input = key => root.ownerDocument.querySelector('[data-admin-field="' + key + '"]');
  const table = new DataTable(ui.find('adminTable'), {
    data: [], columns: [
      { data: 'name', render: (value, type, row) => type === 'display' ? `<span class="fw-medium text-break">${escape(value)}</span>${core.session(ui.storage)?.id === row.id ? '<span class="badge bg-label-success ms-2">Anda</span>' : ''}` : value },
      { data: 'username', render: (value, type) => type === 'display' ? `<span class="text-nowrap">@${escape(value)}</span>` : value },
      { data: 'email', render: (value, type) => type === 'display' ? escape(value) : value },
      { data: 'createdAt', render: (value, type) => ['sort', 'type'].includes(type) ? Date.parse(value) || 0 : Number.isFinite(Date.parse(value)) ? escape(preview.formatDate(value, true)) : '—' },
      { data: 'id', orderable: false, searchable: false, render: id => `<div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-label-primary" data-admin-action="edit" data-admin-id="${escape(id)}"><i class="icon-base ti tabler-pencil icon-sm me-1" aria-hidden="true"></i>Edit</button><button type="button" class="btn btn-sm btn-icon btn-label-danger" data-admin-action="delete" data-admin-id="${escape(id)}" aria-label="Hapus admin" ${state?.admins.length === 1 || core.session(ui.storage)?.id === id ? 'disabled' : ''}><i class="icon-base ti tabler-trash icon-sm" aria-hidden="true"></i></button></div>` }
    ], order: [], pageLength: 10, layout: ui.tableLayout, language: ui.tableLanguage
  });
  async function refresh() {
    await core.initialize();
    state = core.load(ui.storage);
    if (!state) throw new Error('Data admin tidak tersedia. Muat ulang halaman.');
    table.clear().rows.add(state.admins.map(core.publicAdmin)).draw();
    ui.find('adminTotal').textContent = state.admins.length;
    const current = core.session(ui.storage);
    ui.find('adminCurrentName').textContent = current?.name || 'Belum masuk';
    ui.find('adminCurrentEmail').textContent = current ? current.email : 'Masuk menggunakan email atau username.';
    document.dispatchEvent(new CustomEvent('awareness-admins-changed', { detail: { admin: current } }));
  }
  function showPageError(message) { ui.find('adminPageError').textContent = message; ui.find('adminPageError').classList.remove('d-none'); }
  function clearErrors() {
    ui.find('adminEditorForm').querySelectorAll('.is-invalid').forEach(element => { element.classList.remove('is-invalid'); element.setAttribute('aria-invalid', 'false'); });
    ui.find('adminEditorForm').querySelectorAll('[data-admin-error]').forEach(element => { element.textContent = ''; });
    ui.find('adminEditorError').classList.add('d-none');
  }
  function clearPasswords() {
    for (const key of ['password', 'passwordConfirmation']) { input(key).value = ''; input(key).type = 'password'; }
    modalElement.querySelectorAll('[data-admin-toggle]').forEach(button => {
      button.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-label', 'Tampilkan ' + (button.dataset.adminToggle === 'password' ? 'password' : 'konfirmasi password'));
    });
  }
  function openEditor(item = null) {
    clearErrors(); clearPasswords(); editingId = item?.id || '';
    ui.find('adminEditorTitle').textContent = item ? 'Edit admin' : 'Tambah admin';
    for (const key of ['name', 'username', 'email']) input(key).value = item?.[key] || '';
    ui.find('adminPasswordHint').textContent = item ? 'Kosongkan kedua kolom password jika tidak ingin menggantinya. Password baru minimal 8 karakter. Mengganti password akun sendiri akan meminta login ulang.' : 'Buat password minimal 8 karakter untuk akun baru.';
    input('password').required = input('passwordConfirmation').required = !item;
    modal.show();
  }
  try { await refresh(); ui.find('createAdmin').disabled = false; }
  catch { showPageError('Daftar admin belum dapat dimuat dari server. Muat ulang halaman untuk mencoba kembali.'); return; }
  ui.find('createAdmin').addEventListener('click', () => { if (!busy) openEditor(); });
  ui.find('adminSearch').addEventListener('input', event => table.search(event.target.value).draw());
  ui.find('adminPageLength').addEventListener('change', event => table.page.len(Number(event.target.value)).draw());
  modalElement.addEventListener('shown.bs.modal', () => input('name').focus());
  modalElement.addEventListener('hidden.bs.modal', clearPasswords);
  window.addEventListener('pagehide', clearPasswords);
  modalElement.querySelectorAll('[data-admin-toggle]').forEach(button => button.addEventListener('click', () => {
    const element = input(button.dataset.adminToggle), visible = element.type === 'password'; element.type = visible ? 'text' : 'password'; button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', (visible ? 'Sembunyikan ' : 'Tampilkan ') + (button.dataset.adminToggle === 'password' ? 'password' : 'konfirmasi password'));
  }));
  ui.find('adminEditorForm').addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return; clearErrors(); busy = true; ui.find('saveAdmin').disabled = true;
    modalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => { button.disabled = true; });
    const values = Object.fromEntries(['name', 'username', 'email', 'password', 'passwordConfirmation'].map(key => [key, input(key).value]));
    try {
      const result = await core.saveAdmin(ui.storage, values, editingId);
      if (!result.saved) {
        for (const [key, message] of Object.entries(result.errors)) { input(key).classList.add('is-invalid'); input(key).setAttribute('aria-invalid', 'true'); document.querySelector('[data-admin-error="' + key + '"]').textContent = message; }
        if (result.message) { ui.find('adminEditorError').textContent = result.message; ui.find('adminEditorError').classList.remove('d-none'); }
        modalElement.querySelector('.is-invalid')?.focus(); return;
      }
      clearPasswords();
      if (result.sessionInvalidated) { const target = new URL(root.dataset.loginUrl); target.searchParams.set('password', 'updated'); window.location.assign(target.href); }
      else { await refresh(); modal.hide(); ui.notify(editingId ? 'Perubahan akun admin tersimpan di database.' : 'Admin ditambahkan. Akun dapat masuk menggunakan email atau username.'); }
    } catch { ui.find('adminEditorError').textContent = 'Permintaan belum selesai. Muat ulang daftar sebelum mencoba kembali.'; ui.find('adminEditorError').classList.remove('d-none'); }
    finally { busy = false; ui.find('saveAdmin').disabled = false; modalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => { button.disabled = false; }); }
  });
  ui.find('adminTable').addEventListener('click', async event => {
    const button = event.target.closest('[data-admin-action]');
    if (!button || busy || button.disabled) return;
    const item = core.load(ui.storage)?.admins.find(admin => String(admin.id) === button.dataset.adminId);
    if (!item) { showPageError('Admin tidak ditemukan. Muat ulang daftar.'); return; }
    if (button.dataset.adminAction === 'edit') { openEditor(item); return; }
    busy = true;
    try { if (await ui.confirmAction('Hapus akun admin?', 'Hapus akun “' + item.name + '” (@' + item.username + ')? Akun ini tidak dapat dipakai untuk login setelah dihapus.')) { await core.removeAdmin(ui.storage, item.id); await refresh(); ui.notify('Akun admin dihapus dari database.'); } }
    catch (error) { ui.notify(error.message); }
    finally { busy = false; }
  });
  if (new URLSearchParams(window.location.search).get('edit') === 'me') {
    const current = core.session(ui.storage);
    if (current) openEditor(current); else ui.notify('Masuk terlebih dahulu untuk mengedit akun sendiri.');
  }
});
