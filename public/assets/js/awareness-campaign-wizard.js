'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('campaignComposer');
  if (!root) return;
  const core = window.AwarenessCampaigns, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI, settings = window.AwarenessSettings;
  const state = core.load(ui.storage);
  const editId = new URLSearchParams(window.location.search).get('edit');
  const existing = editId ? state.campaigns.find(item => item.id === editId) : null;
  if (editId && (!existing || existing.status !== 'draft')) {
    ui.find('wizardUnavailable').textContent = existing ? 'Simulasi ini tidak dapat diedit. Gunakan duplikat untuk membuat draf baru.' : 'Draf simulasi tidak ditemukan.';
    ui.find('wizardUnavailable').classList.remove('d-none'); ui.find('wizardWorkspace').hidden = true; ui.find('saveCampaignDraft').disabled = true; return;
  }
  let currentStep = 0, saving = false, previewTimer;
  let appliedTemplateId = existing?.templateId || state.templates[0]?.id || '';
  const firstTemplate = state.templates.find(item => item.id === appliedTemplateId);
  if (!firstTemplate) appliedTemplateId = '';
  const selected = new Set(existing?.recipientIds || []);
  const fields = { name: 'campaignName', senderName: 'campaignSenderName', senderEmail: 'campaignSenderEmail', schedule: 'campaignSchedule', endsAt: 'campaignEndsAt', subject: 'campaignSubject' };
  ui.find('campaignFormTitle').textContent = existing ? 'Edit draf simulasi' : 'Buat simulasi';
  ui.find('campaignName').value = existing?.name || '';
  ui.find('campaignDescription').value = existing?.description || '';
  const sender = settings.campaignSender(settings.load(ui.storage), existing || {});
  ui.find('campaignSenderName').value = sender.senderName;
  ui.find('campaignSenderEmail').value = sender.senderEmail;
  ui.find('campaignSenderUpdated').classList.toggle('d-none', !existing || existing.senderEmail === sender.senderEmail);
  ui.find('campaignSchedule').value = existing?.scheduledAt || '';
  ui.find('campaignEndsAt').value = existing?.endsAt || '';
  ui.find('campaignSubject').value = existing?.message.subject || firstTemplate?.subject || '';
  const editorElement = ui.find('campaignMessageEditor');
  const editor = ui.createEditor(editorElement, () => {
    ui.find('campaignBodyCount').textContent = core.messageText(editor.getContents()).trim().length.toLocaleString('id-ID') + ' / 20.000 karakter';
    document.querySelector('[data-field-error="body"]').textContent = ''; editorElement.classList.remove('border-danger');
    clearTimeout(previewTimer); previewTimer = setTimeout(renderMail, 150);
  });
  editor.setContents(existing?.message.delta || firstTemplate?.delta || { ops: [{ insert: '\n' }] });
  ui.bindTokens(ui.find('campaignStep2'), editor);
  ui.find('campaignTemplate').replaceChildren(new Option('Tulis email sendiri', ''), ...state.templates.map(item => new Option(item.name, item.id)));
  ui.find('campaignTemplate').value = appliedTemplateId;
  ui.find('applyCampaignTemplate').disabled = !ui.find('campaignTemplate').value;
  ui.find('campaignTemplate').addEventListener('change', event => { ui.find('applyCampaignTemplate').disabled = !event.target.value; });
  ui.find('applyCampaignTemplate').addEventListener('click', () => {
    const template = state.templates.find(item => item.id === ui.find('campaignTemplate').value);
    if (!template) return;
    appliedTemplateId = template.id; ui.find('campaignSubject').value = template.subject; editor.setContents(template.delta); renderMail(); ui.notify('Isi email diisi dari template terpilih.');
  });

  function readForm() {
    const currentSender = settings.campaignSender(settings.load(ui.storage));
    ui.find('campaignSenderEmail').value = currentSender.senderEmail;
    return { id: existing?.id || '', name: ui.find('campaignName').value.trim(), description: ui.find('campaignDescription').value.trim(), senderName: ui.find('campaignSenderName').value.trim(), senderEmail: currentSender.senderEmail, replyTo: currentSender.replyTo, scheduledAt: ui.find('campaignSchedule').value, endsAt: ui.find('campaignEndsAt').value, recipientMode: ui.find('campaignRecipientAll').checked ? 'all' : 'selected', recipientIds: [...selected], templateId: appliedTemplateId, message: { subject: ui.find('campaignSubject').value.trim(), delta: core.normalizeDelta(editor.getContents()) } };
  }
  function renderMail() {
    const form = readForm();
    const context = { campaignName: form.name || 'Simulasi Awareness', senderName: form.senderName, senderEmail: form.senderEmail };
    if (currentStep === 2) ui.paintPreview(ui.find('campaignLivePreview'), form.message, context);
    if (currentStep === 3) ui.paintPreview(ui.find('campaignReviewPreview'), form.message, context);
  }
  ui.find('campaignSubject').addEventListener('input', renderMail);
  window.addEventListener('focus', renderMail);
  const recipientTable = new DataTable(ui.find('wizardRecipientTable'), {
    data: preview.createRecipients(),
    columns: [
      { data: 'id', orderable: false, searchable: false, render: id => `<input type="checkbox" class="form-check-input" data-wizard-recipient="${id}" aria-label="Pilih penerima">` },
      { data: 'email', render: (email, type) => type === 'display' ? preview.escapeHtml(email) : email },
      { data: 'source', render: source => `<span class="badge bg-label-success">${source}</span>` }
    ],
    pageLength: 10, order: [[1, 'asc']], layout: ui.tableLayout, language: ui.tableLanguage
  });
  function syncRecipients() {
    const all = ui.find('campaignRecipientAll').checked;
    ui.find('recipientSelectionPanel').hidden = all;
    ui.find('selectedRecipientTotal').textContent = all ? 480 : selected.size;
    root.querySelectorAll('[data-wizard-recipient]').forEach(input => { input.checked = selected.has(Number(input.dataset.wizardRecipient)); });
    const page = recipientTable.rows({ page: 'current', search: 'applied' }).data().toArray();
    const checked = page.filter(item => selected.has(item.id)).length;
    ui.find('selectRecipientPage').checked = page.length > 0 && checked === page.length;
    ui.find('selectRecipientPage').indeterminate = checked > 0 && checked < page.length;
  }
  if (existing?.recipientMode === 'selected') ui.find('campaignRecipientSelected').checked = true;
  recipientTable.on('draw', syncRecipients);
  root.querySelectorAll('[name="campaignRecipientMode"]').forEach(input => input.addEventListener('change', () => { syncRecipients(); recipientTable.columns.adjust().draw(false); }));
  ui.find('wizardRecipientSearch').addEventListener('input', event => recipientTable.search(event.target.value).draw());
  ui.find('selectFilteredRecipients').addEventListener('click', () => { recipientTable.rows({ search: 'applied' }).data().toArray().forEach(item => selected.add(item.id)); syncRecipients(); });
  ui.find('clearCampaignRecipients').addEventListener('click', () => { selected.clear(); syncRecipients(); });
  ui.find('selectRecipientPage').addEventListener('change', event => { recipientTable.rows({ page: 'current', search: 'applied' }).data().toArray().forEach(item => event.target.checked ? selected.add(item.id) : selected.delete(item.id)); syncRecipients(); });
  ui.find('wizardRecipientTable').addEventListener('change', event => { if (!event.target.matches('[data-wizard-recipient]')) return; const id = Number(event.target.dataset.wizardRecipient); event.target.checked ? selected.add(id) : selected.delete(id); syncRecipients(); });

  function clearErrors() {
    root.querySelectorAll('.is-invalid').forEach(element => element.classList.remove('is-invalid'));
    root.querySelectorAll('[data-field-error]').forEach(element => { element.textContent = ''; });
    editorElement.classList.remove('border-danger'); ui.find('wizardAlert').classList.add('d-none');
  }
  function showErrors(errors) {
    clearErrors();
    for (const [key, message] of Object.entries(errors)) {
      root.querySelector('[data-field-error="' + key + '"]').textContent = message;
      if (fields[key]) ui.find(fields[key]).classList.add('is-invalid'); else if (key === 'body') editorElement.classList.add('border-danger');
    }
    ui.find('wizardAlert').textContent = 'Lengkapi bagian yang ditandai sebelum melanjutkan.'; ui.find('wizardAlert').classList.remove('d-none');
    const field = Object.keys(fields).find(key => errors[key]);
    if (field) ui.find(fields[field]).focus(); else if (errors.body) editor.focus(); else if (errors.recipients) ui.find('campaignRecipientSelected').focus();
  }
  function showStep(index) {
    currentStep = index;
    root.querySelectorAll('[data-wizard-panel]').forEach(panel => { const active = Number(panel.dataset.wizardPanel) === index; panel.hidden = !active; panel.classList.toggle('active', active); });
    root.querySelectorAll('[data-wizard-header]').forEach(header => { header.classList.toggle('active', Number(header.dataset.wizardHeader) === index); header.classList.toggle('crossed', Number(header.dataset.wizardHeader) < index); });
    root.querySelectorAll('[data-wizard-step]').forEach(button => { if (Number(button.dataset.wizardStep) === index) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current'); });
    ui.find('wizardPrevious').textContent = index === 0 ? 'Kembali ke daftar' : 'Sebelumnya';
    ui.find('wizardNext').textContent = index === 3 ? 'Jadwalkan pratinjau' : 'Lanjutkan →';
    if (index === 1) requestAnimationFrame(() => recipientTable.columns.adjust().draw(false));
    if (index === 3) {
      const form = readForm();
      ui.find('reviewCampaignName').textContent = form.name;
      ui.find('reviewCampaignSender').textContent = form.senderName + ' <' + form.senderEmail + '>';
      ui.find('reviewCampaignRecipients').textContent = core.recipientsFor(form).length + ' email · ' + (form.recipientMode === 'all' ? 'Seluruh daftar contoh' : 'Pilihan khusus');
      ui.find('reviewCampaignSchedule').textContent = core.scheduleLabel(form.scheduledAt);
      ui.find('reviewCampaignEndsAt').textContent = core.scheduleLabel(form.endsAt);
      ui.find('reviewCampaignSubject').textContent = form.message.subject;
    }
    renderMail();
  }
  const stageKeys = [['name', 'senderName', 'senderEmail', 'schedule', 'endsAt'], ['recipients'], ['subject', 'body']];
  function goToStep(target) {
    clearErrors();
    if (target > currentStep) {
      const errors = core.validateCampaign(readForm());
      for (let index = 0; index < target; index++) {
        const stageErrors = Object.fromEntries(Object.entries(errors).filter(([key]) => stageKeys[index]?.includes(key)));
        if (Object.keys(stageErrors).length) { showStep(index); showErrors(stageErrors); return; }
      }
    }
    showStep(target);
  }
  root.querySelectorAll('[data-wizard-step]').forEach(button => button.addEventListener('click', () => goToStep(Number(button.dataset.wizardStep))));
  ui.find('wizardPrevious').addEventListener('click', () => currentStep === 0 ? window.location.assign(root.dataset.indexUrl) : goToStep(currentStep - 1));

  function saveCampaign(status) {
    if (saving) return;
    const form = readForm();
    const errors = status === 'draft' ? Object.assign(core.validatePeriod(form), (!form.name || form.name.length > 120 ? { name: 'Isi nama simulasi, maksimal 120 karakter.' } : {})) : core.validateCampaign(form, { schedule: true });
    if (Object.keys(errors).length) {
      const index = errors.name || errors.senderName || errors.senderEmail || errors.schedule || errors.endsAt ? 0 : errors.recipients ? 1 : 2;
      showStep(index); showErrors(errors); return;
    }
    saving = true; ui.find('saveCampaignDraft').disabled = true; ui.find('wizardNext').disabled = true;
    const latest = core.load(ui.storage);
    const id = existing?.id || core.newId('campaign');
    const item = { ...form, id, status, recipientIds: core.recipientsFor(form), target: core.recipientsFor(form).length, sent: 0, clicked: 0, updatedAt: new Date().toISOString() };
    const index = latest.campaigns.findIndex(campaign => campaign.id === id);
    if (existing && (index < 0 || latest.campaigns[index].status !== 'draft')) {
      saving = false; ui.find('saveCampaignDraft').disabled = false; ui.find('wizardNext').disabled = false;
      ui.notify('Draf ini sudah berubah. Kembali ke daftar simulasi untuk membuka versi terbaru.'); return;
    }
    if (index >= 0) latest.campaigns[index] = item; else latest.campaigns.unshift(item);
    if (ui.persist(latest)) {
      const target = new URL(root.dataset.detailUrl); target.searchParams.set('id', id); target.searchParams.set('saved', status); window.location.assign(target.href);
    } else { saving = false; ui.find('saveCampaignDraft').disabled = false; ui.find('wizardNext').disabled = false; }
  }
  ui.find('saveCampaignDraft').addEventListener('click', () => saveCampaign('draft'));
  ui.find('wizardNext').addEventListener('click', () => currentStep === 3 ? saveCampaign('scheduled') : goToStep(currentStep + 1));
  syncRecipients(); showStep(0);
});
