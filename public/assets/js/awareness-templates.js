'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('templateWorkspace')) return;
  const core = window.AwarenessCampaigns, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI;
  const api = window.AwarenessApi;
  let state;
  try { state = await api.library(); }
  catch (error) { api.showLoadError(document.getElementById('templateWorkspace'), error.message); ui.find('createEmailTemplate').disabled = true; ui.find('manageTemplateCategories').disabled = true; return; }
  const categoryId = name => state.categoryRecords.find(item => item.name === name)?.id;
  let editingId = null, previewTimer, editingCategory = null, pendingCategoryDelete = null;
  const escape = preview.escapeHtml;
  const editorElement = ui.find('templateMessageEditor');
  const editorModal = bootstrap.Modal.getOrCreateInstance(ui.find('templateEditorModal'));
  const previewModal = bootstrap.Modal.getOrCreateInstance(ui.find('templatePreviewModal'));
  const categoryModal = bootstrap.Modal.getOrCreateInstance(ui.find('templateCategoryModal'));
  const editor = ui.createEditor(editorElement, () => {
    ui.find('templateBodyCount').textContent = core.messageText(editor.getContents()).trim().length.toLocaleString('id-ID') + ' / 20.000 karakter';
    document.querySelector('[data-template-error="body"]').textContent = '';
    editorElement.classList.remove('border-danger');
    clearTimeout(previewTimer); previewTimer = setTimeout(renderEditorPreview, 150);
  });
  ui.bindTokens(ui.find('templateTokenButtons'), editor);

  function renderEditorPreview() {
    ui.paintPreview(ui.find('templateEditorPreview'), { subject: ui.find('templateSubject').value, delta: editor.getContents() }, { campaignName: 'Simulasi Awareness' });
  }
  function clearErrors() {
    ui.find('templateEditorForm').querySelectorAll('.is-invalid').forEach(element => element.classList.remove('is-invalid'));
    ui.find('templateEditorForm').querySelectorAll('[data-template-error]').forEach(element => { element.textContent = ''; });
    editorElement.classList.remove('border-danger');
  }
  function openEditor(item = null) {
    renderCategoryChoices();
    if (!state.categories.length) return;
    clearErrors(); editingId = item?.id || null;
    ui.find('templateEditorTitle').textContent = item ? 'Edit template email' : 'Buat template email';
    ui.find('templateName').value = item?.name || '';
    ui.find('templateCategory').value = item?.category || state.categories[0];
    ui.find('templateSubject').value = item?.subject || '';
    ui.find('saveEmailTemplate').disabled = false;
    editor.setContents(item?.delta || { ops: [{ insert: '\n' }] });
    renderEditorPreview(); editorModal.show();
  }
  function renderCategoryChoices() {
    const filter = ui.find('templateCategoryFilter'), field = ui.find('templateCategory');
    const selectedFilter = filter.value, selectedField = field.value;
    filter.replaceChildren(new Option('Semua kategori', ''), ...state.categories.map(name => new Option(name, name)));
    field.replaceChildren(...(state.categories.length ? state.categories.map(name => new Option(name, name)) : [new Option('Belum ada kategori', '')]));
    filter.value = state.categories.includes(selectedFilter) ? selectedFilter : '';
    field.value = state.categories.includes(selectedField) ? selectedField : (state.categories[0] || '');
    ui.find('createEmailTemplate').disabled = !state.categories.length;
    ui.find('noTemplateCategories').classList.toggle('d-none', state.categories.length > 0);
  }
  function resetCategoryForm() {
    editingCategory = null;
    ui.find('templateCategoryForm').reset();
    ui.find('templateCategoryName').classList.remove('is-invalid');
    ui.find('templateCategoryNameError').textContent = '';
    ui.find('categoryNameLabel').textContent = 'Nama kategori baru';
    ui.find('saveTemplateCategory').textContent = 'Tambah kategori';
    ui.find('cancelCategoryEdit').classList.add('d-none');
  }
  function renderCategoryList() {
    ui.find('categoryRegistryCount').textContent = state.categories.length + ' kategori';
    ui.find('templateCategoryRows').innerHTML = state.categories.map((name, index) => {
      const count = state.templates.filter(item => item.category === name).length;
      return `<tr><td class="text-break">${escape(name)}</td><td><span class="badge bg-label-secondary">${count} template</span></td><td><div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-icon btn-label-secondary" data-category-action="edit" data-category-index="${index}" aria-label="Ubah kategori ${escape(name)}"><i class="icon-base ti tabler-pencil icon-sm" aria-hidden="true"></i></button><button type="button" class="btn btn-sm btn-icon btn-label-danger" data-category-action="delete" data-category-index="${index}" aria-label="Hapus kategori ${escape(name)}"><i class="icon-base ti tabler-trash icon-sm" aria-hidden="true"></i></button></div></td></tr>`;
    }).join('') || '<tr><td colspan="3" class="text-center text-body-secondary py-6">Belum ada kategori. Tambahkan kategori pertama di atas.</td></tr>';
  }
  function showCategoryList() {
    pendingCategoryDelete = null;
    ui.find('categoryManagePanel').hidden = false;
    ui.find('categoryDeletePanel').hidden = true;
    ui.find('categoryModalBack').textContent = 'Tutup';
    ui.find('confirmCategoryDelete').classList.add('d-none');
    ui.find('categoryActionError').classList.add('d-none');
    renderCategoryList();
  }
  function refreshCategories() { renderCategoryChoices(); renderCategoryList(); renderGrid(); }
  ui.find('manageTemplateCategories').addEventListener('click', () => {
    resetCategoryForm(); showCategoryList(); refreshCategories(); categoryModal.show();
  });
  ui.find('templateCategoryModal').addEventListener('shown.bs.modal', () => ui.find('templateCategoryName').focus());
  ui.find('templateCategoryModal').addEventListener('hidden.bs.modal', () => { resetCategoryForm(); showCategoryList(); });
  ui.find('cancelCategoryEdit').addEventListener('click', () => { resetCategoryForm(); ui.find('templateCategoryName').focus(); });
  ui.find('templateCategoryName').addEventListener('input', () => {
    ui.find('templateCategoryName').classList.remove('is-invalid');
    ui.find('templateCategoryNameError').textContent = '';
  });
  ui.find('templateCategoryForm').addEventListener('submit', async event => {
    event.preventDefault();
    const button = ui.find('saveTemplateCategory'); if (button.disabled) return;
    button.disabled = true;
    const previous = editingCategory;
    try {
      const result = await api.request(api.urls.categories + (previous === null ? '' : '/' + categoryId(previous)), { method: previous === null ? 'POST' : 'PUT', data: { name: ui.find('templateCategoryName').value } });
      state = await api.library();
      if (ui.find('templateCategoryFilter').value === previous) {
        ui.find('templateCategoryFilter').add(new Option(result.data.name, result.data.name)); ui.find('templateCategoryFilter').value = result.data.name;
      }
      resetCategoryForm(); refreshCategories(); ui.find('templateCategoryName').focus();
      ui.notify(previous === null ? 'Kategori baru tersimpan.' : 'Nama kategori diperbarui.');
    } catch (error) {
      ui.find('templateCategoryName').classList.add('is-invalid');
      ui.find('templateCategoryNameError').textContent = error.errors?.name || error.errors?.name_key || error.message;
    } finally { button.disabled = false; }
  });
  ui.find('templateCategoryRows').addEventListener('click', event => {
    const button = event.target.closest('[data-category-action]');
    if (!button) return;
    const name = state.categories[Number(button.dataset.categoryIndex)];
    if (name === undefined) return;
    if (button.dataset.categoryAction === 'edit') {
      resetCategoryForm(); editingCategory = name;
      ui.find('templateCategoryName').value = name;
      ui.find('categoryNameLabel').textContent = 'Ubah nama kategori';
      ui.find('saveTemplateCategory').textContent = 'Simpan perubahan';
      ui.find('cancelCategoryEdit').classList.remove('d-none');
      ui.find('templateCategoryName').focus(); return;
    }
    pendingCategoryDelete = name;
    const count = state.templates.filter(item => item.category === name).length;
    const alternatives = state.categories.filter(item => item !== name);
    ui.find('categoryManagePanel').hidden = true;
    ui.find('categoryDeletePanel').hidden = false;
    ui.find('categoryDeleteHeading').textContent = 'Hapus kategori “' + name + '”?';
    ui.find('categoryDeleteDescription').textContent = count ? count + ' template masih menggunakan kategori ini. Pilih kategori pengganti sebelum menghapusnya.' : 'Kategori ini belum digunakan oleh template dan dapat dihapus.';
    ui.find('categoryReplacementField').hidden = !count || !alternatives.length;
    ui.find('categoryReplacement').replaceChildren(new Option('Pilih kategori pengganti', ''), ...alternatives.map(value => new Option(value, value)));
    ui.find('categoryReplacementUnavailable').classList.toggle('d-none', !count || alternatives.length > 0);
    ui.find('confirmCategoryDelete').classList.remove('d-none');
    ui.find('confirmCategoryDelete').disabled = count > 0;
    ui.find('categoryModalBack').textContent = 'Batal';
    ui.find('categoryModalBack').focus();
  });
  ui.find('categoryReplacement').addEventListener('change', () => { ui.find('confirmCategoryDelete').disabled = !ui.find('categoryReplacement').value; });
  ui.find('categoryModalBack').addEventListener('click', () => {
    if (pendingCategoryDelete !== null) { showCategoryList(); ui.find('templateCategoryName').focus(); }
    else categoryModal.hide();
  });
  ui.find('confirmCategoryDelete').addEventListener('click', async event => {
    if (pendingCategoryDelete === null || event.currentTarget.disabled) return;
    const button = event.currentTarget; button.disabled = true;
    try {
      await api.request(api.urls.categories + '/' + categoryId(pendingCategoryDelete), { method: 'DELETE', data: { replacement_id: categoryId(ui.find('categoryReplacement').value) || null } });
      state = await api.library(); resetCategoryForm(); showCategoryList(); refreshCategories(); ui.find('templateCategoryName').focus();
      ui.notify('Kategori dihapus. Semua template email tetap tersimpan.');
    } catch (error) { ui.find('categoryActionError').textContent = error.message; ui.find('categoryActionError').classList.remove('d-none'); }
    finally { button.disabled = false; }
  });
  function renderGrid() {
    const query = ui.find('templateSearch').value.trim().toLowerCase();
    const category = ui.find('templateCategoryFilter').value;
    const items = state.templates.filter(item => (!category || item.category === category) && (item.name + ' ' + item.subject).toLowerCase().includes(query));
    ui.find('templateCount').textContent = items.length + ' dari ' + state.templates.length + ' template';
    ui.find('templateEmpty').classList.toggle('d-none', items.length > 0);
    ui.find('templateGrid').innerHTML = items.map(item => {
      const excerpt = core.messageText(item.delta).replace(/\{\{tracking_link\}\}/g, 'Lihat informasi').replace(/\{\{email\}\}/g, 'email penerima').replace(/\{\{campaign_name\}\}/g, 'nama simulasi').replace(/\s+/g, ' ').trim();
      return `<div class="col-md-6 col-xl-4"><article class="card h-100"><div class="card-body d-flex flex-column"><div class="d-flex align-items-center justify-content-between gap-3 mb-5"><div class="avatar"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-mail icon-26px" aria-hidden="true"></i></span></div><span class="badge bg-label-secondary">${escape(item.category)}</span></div><h5 class="card-title mb-2 text-break">${escape(item.name)}</h5><p class="small text-body-secondary mb-5">Template email simulasi</p><div class="bg-lighter rounded p-4 mb-5"><h6 class="template-card-subject mb-3">${escape(item.subject)}</h6><p class="template-card-excerpt small text-body-secondary mb-0">${escape(excerpt)}</p></div><div class="d-flex flex-wrap gap-2 mt-auto"><button type="button" class="btn btn-sm btn-label-primary" data-template-action="preview" data-id="${item.id}"><i class="icon-base ti tabler-eye icon-sm me-1" aria-hidden="true"></i>Pratinjau</button><button type="button" class="btn btn-sm btn-label-secondary" data-template-action="edit" data-id="${item.id}"><i class="icon-base ti tabler-pencil icon-sm me-1" aria-hidden="true"></i>Edit</button><div class="dropdown ms-auto"><button type="button" class="btn btn-sm btn-icon btn-text-secondary" data-bs-toggle="dropdown" aria-label="Aksi template"><i class="icon-base ti tabler-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button type="button" class="dropdown-item" data-template-action="duplicate" data-id="${item.id}"><i class="icon-base ti tabler-copy icon-sm me-2" aria-hidden="true"></i>Duplikat</button></li><li><button type="button" class="dropdown-item text-danger" data-template-action="delete" data-id="${item.id}"><i class="icon-base ti tabler-trash icon-sm me-2" aria-hidden="true"></i>Hapus</button></li></ul></div></div></div></article></div>`;
    }).join('');
  }
  ui.find('createEmailTemplate').addEventListener('click', () => openEditor());
  ui.find('templateSearch').addEventListener('input', renderGrid);
  ui.find('templateCategoryFilter').addEventListener('change', renderGrid);
  ui.find('clearTemplateFilters').addEventListener('click', () => { ui.find('templateSearch').value = ''; ui.find('templateCategoryFilter').value = ''; renderGrid(); });
  ui.find('templateSubject').addEventListener('input', renderEditorPreview);
  ui.find('templateEditorModal').addEventListener('shown.bs.modal', () => ui.find('templateName').focus());
  ui.find('templateEditorModal').addEventListener('hidden.bs.modal', () => clearTimeout(previewTimer));
  function showTemplateErrors(errors) {
    const fields = { name: 'templateName', category: 'templateCategory', subject: 'templateSubject' };
    for (const [key, message] of Object.entries(errors)) {
      const mapped = key === 'category_id' ? 'category' : key.startsWith('delta') ? 'body' : key;
      const feedback = document.querySelector('[data-template-error="' + mapped + '"]');
      if (feedback) feedback.textContent = message;
      if (fields[mapped]) ui.find(fields[mapped]).classList.add('is-invalid');
      else editorElement.classList.add('border-danger');
    }
  }
  function templatePayload(item) { return { name: item.name, subject: item.subject, category_id: categoryId(item.category), delta: item.delta }; }
  ui.find('templateEditorForm').addEventListener('submit', async event => {
    event.preventDefault(); clearErrors();
    const button = ui.find('saveEmailTemplate'); if (button.disabled) return;
    const item = { name: ui.find('templateName').value.trim(), category: ui.find('templateCategory').value, subject: ui.find('templateSubject').value.trim(), delta: core.normalizeDelta(editor.getContents()) };
    const errors = core.validateTemplate(item, state.categories);
    if (Object.keys(errors).length) { showTemplateErrors(errors); return; }
    button.disabled = true;
    try {
      await api.request(api.urls.templates + (editingId ? '/' + editingId : ''), { method: editingId ? 'PUT' : 'POST', data: templatePayload(item) });
      state = await api.library(); renderGrid(); editorModal.hide(); ui.notify('Template email tersimpan dan siap dipilih pada simulasi.');
    } catch (error) {
      showTemplateErrors(error.errors || { body: error.message });
    } finally { button.disabled = false; }
  });
  let busy = false;
  ui.find('templateGrid').addEventListener('click', async event => {
    const button = event.target.closest('[data-template-action]');
    if (!button || busy) return;
    const latest = state;
    const item = latest.templates.find(template => template.id === button.dataset.id);
    if (!item) { ui.notify('Template tidak ditemukan. Muat ulang halaman untuk memperbarui daftar.'); return; }
    const action = button.dataset.templateAction;
    if (action === 'edit') { openEditor(item); return; }
    if (action === 'preview') { ui.find('templatePreviewTitle').textContent = item.name; ui.paintPreview(ui.find('templateReadPreview'), item, { campaignName: 'Simulasi Awareness' }); previewModal.show(); return; }
    busy = true;
    try {
      if (action === 'duplicate') {
        const copy = { ...item, name: ('Salinan ' + item.name).slice(0, 100) };
        const result = await api.request(api.urls.templates, { method: 'POST', data: templatePayload(copy) });
        state = await api.library(); renderGrid(); openEditor(result.data);
      } else if (action === 'delete' && await ui.confirmAction('Hapus template email?', 'Hapus “' + item.name + '” dari koleksi? Simulasi tersimpan tetap memakai salinan emailnya sendiri.')) {
        await api.request(api.urls.templates + '/' + item.id, { method: 'DELETE' });
        state = await api.library(); renderGrid(); ui.notify('Template dihapus dari koleksi.');
      }
    } catch (error) { ui.notify(error.message); }
    finally { busy = false; }
  });
  renderCategoryChoices(); renderGrid();
});
