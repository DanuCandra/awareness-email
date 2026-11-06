'use strict';

window.AwarenessComposeUI = (() => {
  const core = window.AwarenessCampaigns;
  const find = id => document.getElementById(id);
  let storage;
  try { storage = window.sessionStorage; }
  catch { storage = { getItem: () => null, setItem: () => { throw new Error('Storage unavailable'); } }; }
  const previews = new Map();

  function notify(message) {
    find('composeToastMessage').textContent = message;
    bootstrap.Toast.getOrCreateInstance(find('composeToast'), { delay: 5000 }).show();
  }

  function persist(state) {
    const saved = core.save(storage, state);
    if (!saved) notify('Draf belum tersimpan. Penyimpanan sementara browser tidak tersedia atau penuh.');
    return saved;
  }

  function confirmAction(title, message, label = 'Hapus', color = 'danger') {
    return new Promise(resolve => {
      const element = find('composeConfirmModal');
      const modal = bootstrap.Modal.getOrCreateInstance(element);
      let accepted = false;
      find('composeConfirmTitle').textContent = title;
      find('composeConfirmMessage').textContent = message;
      const button = find('composeConfirmButton');
      button.textContent = label; button.className = 'btn btn-' + color + ' m-0'; button.disabled = false;
      button.onclick = () => { accepted = true; button.disabled = true; modal.hide(); };
      element.addEventListener('hidden.bs.modal', () => resolve(accepted), { once: true });
      modal.show();
    });
  }

  function createEditor(element, onChange) {
    const editor = new Quill(element, {
      theme: 'snow', placeholder: 'Tulis isi email simulasi di sini…',
      formats: ['header', 'bold', 'italic', 'underline', 'strike', 'blockquote', 'list', 'link'],
      modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] }
    });
    editor.on('text-change', () => onChange(editor.getContents()));
    element.querySelector('.ql-editor').setAttribute('aria-label', 'Isi email simulasi');
    element.querySelector('.ql-editor').setAttribute('role', 'textbox');
    element.querySelector('.ql-editor').setAttribute('aria-multiline', 'true');
    return editor;
  }

  function bindTokens(container, editor) {
    container.querySelectorAll('[data-insert-token]').forEach(button => button.addEventListener('click', () => {
      const value = core.token[button.dataset.insertToken];
      if (!value) return;
      const range = editor.getSelection(true);
      const index = range?.index ?? Math.max(0, editor.getLength() - 1);
      if (range?.length) editor.deleteText(index, range.length, 'user');
      editor.insertText(index, value, 'user');
      editor.setSelection(index + value.length, 0, 'user');
    }));
  }

  function paintPreview(container, message, context = {}) {
    if (!container) return;
    previews.set(container, { message: core.clone(message), context: { ...context } });
    container.querySelector('[data-preview-subject]').textContent = message.subject || 'Subjek email';
    const sender = window.AwarenessSettings.load(storage).smtp;
    container.querySelector('[data-preview-sender]').textContent = context.senderName || sender.fromName;
    container.querySelector('[data-preview-sender-email]').textContent = context.senderEmail || sender.fromEmail || 'Email pengirim belum diatur';
    container.querySelector('[data-preview-recipient]').textContent = context.email || 'karyawan001@perusahaan.example';
    const accent = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim();
    container.querySelector('iframe').srcdoc = core.emailDocument(message.delta, context, accent);
  }

  function initializePreviews() {
    document.querySelectorAll('.mail-preview').forEach(container => {
      container.querySelectorAll('[data-preview-device]').forEach(button => button.addEventListener('click', () => {
        const mobile = button.dataset.previewDevice === 'mobile';
        container.querySelector('.mail-preview-device').classList.toggle('is-mobile', mobile);
        container.querySelectorAll('[data-preview-device]').forEach(other => {
          const selected = other === button;
          other.classList.toggle('btn-primary', selected); other.classList.toggle('btn-label-secondary', !selected);
          other.setAttribute('aria-pressed', String(selected));
        });
      }));
    });
    new MutationObserver(() => {
      for (const [container, payload] of previews) if (!container.closest('[hidden]')) paintPreview(container, payload.message, payload.context);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['style', 'data-bs-theme'] });
  }

  function badge(status) {
    const item = core.statuses[status] || core.statuses.draft;
    return `<span class="badge bg-label-${item.color}">${item.label}</span>`;
  }

  const tableLanguage = { emptyTable: 'Belum ada data untuk ditampilkan.', zeroRecords: 'Tidak ada hasil yang sesuai pencarian.', info: 'Menampilkan _START_–_END_ dari _TOTAL_ data', infoEmpty: 'Menampilkan 0 data', infoFiltered: '(dari _MAX_ data)', paginate: { first: '«', previous: '‹', next: '›', last: '»' }, aria: { paginate: { first: 'Halaman pertama', previous: 'Halaman sebelumnya', next: 'Halaman berikutnya', last: 'Halaman terakhir' } } };
  const tableLayout = { topStart: null, topEnd: null, bottomStart: { rowClass: 'row mx-0 px-4 py-4 align-items-center border-top', features: ['info'] }, bottomEnd: 'paging' };
  document.addEventListener('DOMContentLoaded', initializePreviews);
  return { find, storage, notify, persist, confirmAction, createEditor, bindTokens, paintPreview, badge, tableLanguage, tableLayout };
})();
