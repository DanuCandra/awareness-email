'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const root = document.getElementById('emailWorkspace');
  if (!root) return;
  const preview = window.AwarenessPreview;
  const escape = preview.escapeHtml;
  const find = id => document.getElementById(id);
  const api = window.AwarenessApi;
  let recipients = [];
  let editId = null;
  let pendingDelete = [];
  const selected = new Set();
  const editor = bootstrap.Offcanvas.getOrCreateInstance(find('emailEditor'));
  const deletion = bootstrap.Modal.getOrCreateInstance(find('deleteEmailModal'));
  const importer = bootstrap.Modal.getOrCreateInstance(find('emailImportModal'));
  const toast = bootstrap.Toast.getOrCreateInstance(find('emailToast'), { delay: 4500 });
  const table = new DataTable(find('recipientTable'), {
    data: recipients,
    columns: [
      { data: 'id', orderable: false, searchable: false, render: id => `<input class="form-check-input" type="checkbox" data-select-email="${id}" aria-label="Pilih email">` },
      { data: 'email', render: (email, type) => type === 'display' ? `<span class="fw-medium">${escape(email)}</span>` : email },
      { data: 'source', render: (source, type) => type === 'display' ? `<span class="badge ${source !== 'Manual' ? 'bg-label-success' : 'bg-label-info'}">${source}</span>` : source },
      { data: 'addedAt', render: (date, type) => type === 'display' || type === 'filter' ? escape(preview.formatDate(date)) : date },
      { data: 'id', orderable: false, searchable: false, render: id => `<div class="d-flex align-items-center gap-1"><button type="button" class="btn btn-icon btn-text-secondary rounded-pill" data-edit-email="${id}" aria-label="Edit email"><i class="icon-base ti tabler-pencil" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-text-danger rounded-pill" data-delete-email="${id}" aria-label="Hapus email"><i class="icon-base ti tabler-trash" aria-hidden="true"></i></button></div>` }
    ],
    order: [[1, 'asc']], pageLength: 10,
    layout: { topStart: null, topEnd: null, bottomStart: { rowClass: 'row mx-0 px-4 py-4 align-items-center border-top', features: ['info'] }, bottomEnd: 'paging' },
    language: { emptyTable: 'Belum ada email. Tambahkan email atau impor file Excel.', zeroRecords: 'Tidak ada email yang sesuai pencarian.', info: 'Menampilkan _START_–_END_ dari _TOTAL_ email', infoEmpty: 'Menampilkan 0 email', infoFiltered: '(dari _MAX_ email)', paginate: { first: '«', previous: '‹', next: '›', last: '»' }, aria: { paginate: { first: 'Halaman pertama', previous: 'Halaman sebelumnya', next: 'Halaman berikutnya', last: 'Halaman terakhir' } } },
    columnDefs: [{ targets: 0, width: '36px' }, { targets: 4, width: '96px' }]
  });

  function notify(message) {
    find('emailToastMessage').textContent = message;
    toast.show();
  }

  function updateSelection() {
    find('selectedEmailCount').textContent = selected.size;
    find('deleteSelectedEmails').disabled = selected.size === 0;
    root.querySelectorAll('[data-select-email]').forEach(input => { input.checked = selected.has(Number(input.dataset.selectEmail)); });
    const page = table.rows({ page: 'current', search: 'applied' }).data().toArray();
    const checkedCount = page.filter(row => selected.has(row.id)).length;
    find('selectPageEmails').checked = page.length > 0 && checkedCount === page.length;
    find('selectPageEmails').indeterminate = checkedCount > 0 && checkedCount < page.length;
  }

  function refreshTable() {
    table.clear().rows.add(recipients).draw(false);
    if (table.page() >= table.page.info().pages && table.page.info().pages) table.page('last').draw('page');
    root.querySelectorAll('[data-email-total]').forEach(element => { element.textContent = recipients.length.toLocaleString('id-ID'); });
    root.querySelectorAll('[data-email-imported]').forEach(element => { element.textContent = recipients.filter(row => row.source !== 'Manual').length.toLocaleString('id-ID'); });
    root.querySelectorAll('[data-email-manual]').forEach(element => { element.textContent = recipients.filter(row => row.source === 'Manual').length.toLocaleString('id-ID'); });
    updateSelection();
  }
  table.on('draw', updateSelection);
  find('emailSearch').addEventListener('input', event => table.search(event.target.value).draw());
  find('emailSourceFilter').addEventListener('change', event => table.column(2).search(event.target.value).draw());
  find('emailPageLength').addEventListener('change', event => table.page.len(Number(event.target.value)).draw());
  find('selectPageEmails').addEventListener('change', event => {
    table.rows({ page: 'current', search: 'applied' }).data().toArray().forEach(row => event.target.checked ? selected.add(row.id) : selected.delete(row.id));
    updateSelection();
  });
  find('recipientTable').addEventListener('change', event => {
    if (!event.target.matches('[data-select-email]')) return;
    const id = Number(event.target.dataset.selectEmail);
    event.target.checked ? selected.add(id) : selected.delete(id);
    updateSelection();
  });

  function openDelete(ids) {
    pendingDelete = ids;
    find('deleteEmailMessage').textContent = ids.length === 1 ? `Hapus ${recipients.find(row => row.id === ids[0]).email} dari daftar email?` : `Hapus ${ids.length} email terpilih dari daftar email?`;
    deletion.show();
  }
  find('deleteSelectedEmails').addEventListener('click', () => openDelete([...selected]));
  find('confirmDeleteEmails').addEventListener('click', async event => {
    const button = event.currentTarget; button.disabled = true;
    try {
      const result = await api.request(api.urls.bulkEmails, { method: 'DELETE', data: { ids: pendingDelete } });
      pendingDelete.forEach(id => selected.delete(id));
      deletion.hide(); await reload(); notify(result.deleted + ' email dihapus dari daftar.');
    } catch (error) { notify(error.message); }
    finally { button.disabled = false; }
  });

  find('emailEditor').addEventListener('hidden.bs.offcanvas', () => {
    editId = null; find('emailEditorForm').reset();
    find('recipientEmail').classList.remove('is-invalid');
    find('emailEditorTitle').textContent = 'Tambah email';
  });
  find('emailEditor').addEventListener('shown.bs.offcanvas', () => find('recipientEmail').focus());
  find('emailEditorForm').addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.submitter; if (button.disabled) return;
    button.disabled = true;
    try {
      const email = preview.normalizeEmail(find('recipientEmail').value);
      await api.request(api.urls.emails + (editId ? '/' + editId : ''), { method: editId ? 'PUT' : 'POST', data: { email } });
      editor.hide(); await reload(); notify('Email tersimpan.');
    } catch (error) {
      find('recipientEmail').classList.add('is-invalid');
      find('recipientEmailError').textContent = error.errors?.email || error.message;
      find('recipientEmail').focus();
    } finally { button.disabled = false; }
  });
  find('recipientEmail').addEventListener('input', () => find('recipientEmail').classList.remove('is-invalid'));
  find('recipientTable').addEventListener('click', event => {
    const edit = event.target.closest('[data-edit-email]');
    const remove = event.target.closest('[data-delete-email]');
    if (edit) {
      editId = Number(edit.dataset.editEmail);
      find('recipientEmail').value = recipients.find(row => row.id === editId).email;
      find('emailEditorTitle').textContent = 'Edit email'; editor.show();
    }
    if (remove) openDelete([Number(remove.dataset.deleteEmail)]);
  });

  async function downloadTemplate(button) {
    button.disabled = true;
    try { preview.download(await preview.createTemplate(window.JSZip), 'template-email.xlsx'); }
    catch { notify('Template belum dapat diunduh. Silakan coba lagi.'); }
    finally { button.disabled = false; }
  }
  find('downloadEmailTemplate').addEventListener('click', event => downloadTemplate(event.currentTarget));
  root.querySelectorAll('[data-download-template]').forEach(button => button.addEventListener('click', () => downloadTemplate(button)));
  find('exportEmailList').addEventListener('click', () => {
    const emails = table.rows({ search: 'applied' }).data().toArray().map(row => row.email);
    if (!emails.length) { notify('Tidak ada email pada hasil pencarian untuk diekspor.'); return; }
    preview.download(new Blob([preview.toCsv(emails)], { type: 'text/csv;charset=utf-8;' }), 'daftar-email.csv');
  });

  let importFile = null, importResult = null, importStage = 'upload', importPage = 0, readVersion = 0, reading = false, committing = false, exampleOnly = false;
  function importError(message = '', tone = 'danger') {
    find('importError').classList.remove('alert-danger', 'alert-info');
    find('importError').classList.add('alert-' + tone);
    find('importError').textContent = message;
    find('importError').classList.toggle('d-none', !message);
  }
  function setStage(stage) {
    importStage = stage;
    root.querySelectorAll('[data-import-pane]').forEach(pane => { pane.hidden = pane.dataset.importPane !== stage; });
    const currentStep = { upload: 1, review: 2, complete: 3 }[stage];
    root.querySelectorAll('[data-import-step]').forEach(badge => {
      const active = Number(badge.dataset.importStep) <= currentStep;
      badge.className = 'badge me-2 ' + (active ? 'bg-primary' : 'bg-label-secondary');
    });
    find('importBackButton').textContent = stage === 'review' ? 'Pilih file lain' : stage === 'complete' ? 'Tutup' : 'Batal';
    find('importNextButton').textContent = stage === 'upload' ? 'Periksa file →' : stage === 'review' ? `Tambahkan ${importResult.valid.length} email valid` : 'Lihat daftar email';
    find('importNextButton').disabled = stage === 'upload' ? !importFile || reading : stage === 'review' ? !importResult.valid.length || exampleOnly : false;
    importError();
  }
  function chooseFile(file) {
    readVersion++; importFile = file || null; importResult = null; exampleOnly = false;
    reading = false; find('importLoading').classList.add('d-none');
    setStage('upload');
    find('selectedImportFile').classList.toggle('d-none', !file);
    if (file) find('selectedImportFile').textContent = `${file.name} · ${(file.size / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} KB`;
  }
  function renderPreview() {
    const filter = find('importStatusFilter').value;
    const rows = importResult.rows.filter(row => !filter || row.status === filter);
    const pageCount = Math.max(1, Math.ceil(rows.length / 10));
    importPage = Math.max(0, Math.min(importPage, pageCount - 1));
    const pageRows = rows.slice(importPage * 10, importPage * 10 + 10);
    const badges = { valid: ['success', 'Valid'], duplicate: ['warning', 'Duplikat'], invalid: ['danger', 'Tidak valid'] };
    find('importPreviewRows').innerHTML = pageRows.length ? pageRows.map(row => `<tr><td>${row.number}</td><td class="text-nowrap">${escape(row.value || '—')}</td><td><span class="badge bg-label-${badges[row.status][0]}">${badges[row.status][1]}</span></td><td>${escape(row.reason)}</td></tr>`).join('') : '<tr><td colspan="4" class="text-center text-body-secondary py-5">Tidak ada baris dengan status ini.</td></tr>';
    find('importPreviewInfo').textContent = rows.length ? `${importPage * 10 + 1}–${Math.min((importPage + 1) * 10, rows.length)} dari ${rows.length} baris` : '0 baris';
    find('previousImportPage').disabled = importPage === 0;
    find('nextImportPage').disabled = importPage + 1 >= pageCount;
  }
  function reviewRows(result, name) {
    importResult = result;
    find('importFileName').textContent = name;
    find('importRowSummary').textContent = `${importResult.rows.length} baris email diperiksa · ${importResult.blank} baris kosong dilewati`;
    find('importValidCount').textContent = importResult.valid.length;
    find('importDuplicateCount').textContent = importResult.duplicates;
    find('importInvalidCount').textContent = importResult.invalid;
    importPage = 0; find('importStatusFilter').value = '';
    setStage('review'); renderPreview();
    if (exampleOnly) importError('Ini hanya contoh pemeriksaan. Pilih file Anda untuk menyimpan email ke daftar.', 'info');
  }
  find('emailImportModal').addEventListener('hide.bs.modal', event => { if (committing) event.preventDefault(); });
  find('emailImportModal').addEventListener('hidden.bs.modal', () => {
    chooseFile(null); find('importFile').value = '';
  });
  find('browseImportFile').addEventListener('click', () => find('importFile').click());
  find('importFile').addEventListener('change', event => chooseFile(event.target.files[0]));
  const dropArea = find('importDropArea');
  ['dragenter', 'dragover'].forEach(name => dropArea.addEventListener(name, event => { event.preventDefault(); dropArea.classList.add('border-primary'); }));
  ['dragleave', 'drop'].forEach(name => dropArea.addEventListener(name, event => { event.preventDefault(); dropArea.classList.remove('border-primary'); }));
  dropArea.addEventListener('drop', event => {
    if (event.dataTransfer.files.length !== 1) { importError('Pilih satu file Excel atau CSV setiap kali impor.'); return; }
    chooseFile(event.dataTransfer.files[0]);
  });
  find('useImportExample').addEventListener('click', () => {
    chooseFile(null); exampleOnly = true;
    reviewRows(preview.validateRows([['email'], ['pegawai.baru@perusahaan.example'], ['anggota.baru@perusahaan.example'], ['PEGAWAI.BARU@perusahaan.example'], ['email-tanpa-domain'], ['karyawan@'], ['']], recipients.map(row => row.email)), 'Contoh hasil validasi');
  });
  find('importStatusFilter').addEventListener('change', () => { importPage = 0; renderPreview(); });
  find('previousImportPage').addEventListener('click', () => { importPage--; renderPreview(); });
  find('nextImportPage').addEventListener('click', () => { importPage++; renderPreview(); });
  find('importBackButton').addEventListener('click', () => {
    if (importStage === 'review') { chooseFile(null); find('importFile').value = ''; }
    else importer.hide();
  });
  find('importNextButton').addEventListener('click', async () => {
    if (importStage === 'complete') { importer.hide(); return; }
    if (importStage === 'review') {
      if (exampleOnly || committing || !importResult?.valid.length) return;
      committing = true; find('importNextButton').disabled = true; find('importBackButton').disabled = true;
      try {
        const result = await api.request(api.urls.importCommit, { method: 'POST', data: { token: importResult.token } });
        await reload();
        find('importCompleteMessage').textContent = result.added + ' email valid tersimpan.' + (result.skipped ? ' ' + result.skipped + ' email yang sudah ada dilewati.' : '');
        setStage('complete');
      } catch (error) { importError(error.message); }
      finally { committing = false; find('importBackButton').disabled = false; find('importNextButton').disabled = importStage === 'review' && !importResult?.valid.length; }
      return;
    }
    if (!importFile || reading) return;
    const version = ++readVersion;
    const file = importFile;
    reading = true; importError(); find('importNextButton').disabled = true; find('importLoading').classList.remove('d-none');
    try {
      const data = new FormData(); data.append('file', file);
      const result = await api.request(api.urls.importPreview, { method: 'POST', data });
      if (version === readVersion) reviewRows(result, file.name);
    } catch (error) {
      if (version === readVersion) importError(error.message || 'File tidak dapat dibaca. Gunakan template Excel yang disediakan.');
    } finally {
      if (version === readVersion) {
        reading = false; find('importLoading').classList.add('d-none');
        find('importNextButton').disabled = importStage === 'upload' ? !importFile : !importResult?.valid.length;
      }
    }
  });
  async function reload() {
    const result = await api.request(api.urls.emails);
    recipients = result.data;
    const existing = new Set(recipients.map(row => row.id));
    selected.forEach(id => { if (!existing.has(id)) selected.delete(id); });
    refreshTable();
  }
  refreshTable();
  try { await reload(); }
  catch (error) { api.showLoadError(root, error.message); }
});
