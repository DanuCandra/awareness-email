'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('settingsWorkspace');
  if (!root) return;
  const settings = window.AwarenessSettings, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI, api = window.AwarenessApi;
  const saving = new Set(); let testing = false;
  let state = settings.load(ui.storage), logo = state.identity.logo, logoReadId = 0, testMode = 'connection';
  const escape = preview.escapeHtml;
  const field = (section, key) => section === 'smtp' && key === 'password' ? ui.find('settingsSmtpPassword') : root.querySelector('[data-setting="' + section + '.' + key + '"]');
  const form = section => root.querySelector('[data-section="' + section + '"]');
  const testModal = bootstrap.Modal.getOrCreateInstance(ui.find('settingsTestModal'));
  const landingModal = bootstrap.Modal.getOrCreateInstance(ui.find('settingsLandingModal'));

  function readSection(section) {
    const value = { ...state[section] };
    form(section).querySelectorAll('[data-setting]').forEach(input => { value[input.dataset.setting.split('.')[1]] = input.value.trim(); });
    if (section === 'identity') value.logo = logo;
    if (section === 'smtp') value.password = ui.find('settingsSmtpPassword').value;
    return value;
  }
  function clearPassword() {
    ui.find('settingsSmtpPassword').value = ''; ui.find('settingsSmtpPassword').type = 'password';
    ui.find('toggleSettingsPassword').setAttribute('aria-pressed', 'false'); ui.find('toggleSettingsPassword').setAttribute('aria-label', 'Tampilkan password');
    paintHelpers();
  }
  function clearErrors(section) {
    form(section).querySelectorAll('.is-invalid').forEach(input => { input.classList.remove('is-invalid'); input.setAttribute('aria-invalid', 'false'); });
    form(section).querySelectorAll('[data-setting-error]').forEach(element => { element.textContent = ''; });
  }
  function showErrors(section, errors) {
    clearErrors(section);
    for (const [key, message] of Object.entries(errors)) {
      const feedback = form(section).querySelector('[data-setting-error="' + section + '.' + key + '"]');
      if (feedback) feedback.textContent = message;
      const input = field(section, key);
      if (input) { input.classList.add('is-invalid'); input.setAttribute('aria-invalid', 'true'); }
    }
    form(section).querySelector('.is-invalid')?.focus();
  }
  function paintLogo() {
    ui.find('settingsLogoImage').classList.toggle('d-none', !logo); ui.find('settingsLogoFallback').classList.toggle('d-none', !!logo);
    if (logo) ui.find('settingsLogoImage').src = logo; else ui.find('settingsLogoImage').removeAttribute('src');
    ui.find('removeSettingsLogo').disabled = !logo;
  }
  function smtpDirty() {
    return [...form('smtp').querySelectorAll('[data-setting]')].some(input => input.value.trim() !== String(state.smtp[input.dataset.setting.split('.')[1]] ?? '').trim()) || !!ui.find('settingsSmtpPassword').value;
  }
  function paintHelpers() {
    const smtp = readSection('smtp');
    const dirty = smtpDirty(), blocked = dirty || !state.smtp.configured || saving.has('smtp') || testing;
    ui.find('testSettingsConnection').disabled = blocked; ui.find('testSettingsEmail').disabled = blocked;
    ui.find('settingsTestHint').textContent = dirty ? 'Simpan perubahan SMTP sebelum menjalankan pengujian.' : !state.smtp.configured ? 'Lengkapi dan simpan konfigurasi SMTP beserta password untuk menguji koneksi.' : 'Pengujian menggunakan konfigurasi SMTP yang tersimpan.';
    const badge = ui.find('settingsConnectionStatus');
    badge.textContent = dirty ? 'Belum disimpan' : !state.smtp.configured ? 'Belum dikonfigurasi' : state.smtp.connectionSuccessful === true ? 'Koneksi berhasil' : state.smtp.connectionSuccessful === false ? 'Koneksi gagal' : 'Belum diuji';
    badge.className = 'badge bg-label-' + (dirty || !state.smtp.configured ? 'secondary' : state.smtp.connectionSuccessful === true ? 'success' : state.smtp.connectionSuccessful === false ? 'danger' : 'secondary');
    ui.find('settingsConnectionChecked').textContent = state.smtp.connectionCheckedAt ? 'Terakhir diuji ' + preview.formatDate(state.smtp.connectionCheckedAt, true) : '';
    const interval = Number(smtp.sendIntervalSeconds);
    ui.find('settingsIntervalPreview').textContent = /^\d+$/.test(String(smtp.sendIntervalSeconds)) && Number.isSafeInteger(interval) && interval >= 0 ? interval === 0 ? 'Pengiriman tanpa jeda tambahan (0 detik).' : 'Jeda antar proses pengiriman: ' + interval.toLocaleString('id-ID') + ' detik. Email pertama mengikuti jadwal simulasi.' : 'Masukkan jeda berupa bilangan bulat 0 atau lebih.';
    ui.find('settingsSenderPreview').textContent = (smtp.fromName || 'Nama pengirim') + ' <' + (smtp.fromEmail || 'email-pengirim@example.com') + '>';
    ui.find('settingsTrackingExample').textContent = field('landing', 'baseUrl').value.replace(/\/+$/, '') + '/t/contoh-token';
    ui.find('settingsEducationTips').hidden = field('landing', 'type').value !== 'education';
  }
  function paintSaved() {
    ui.find('settingsSavedSender').textContent = state.smtp.fromEmail || 'Belum diatur';
    ui.find('settingsPasswordStatus').textContent = state.smtp.passwordConfigured ? 'Password tersimpan terenkripsi. Kosongkan untuk mempertahankannya; isi untuk mengganti.' : 'Password SMTP belum disimpan. Isi password sesuai penyedia mail server.';
    ui.find('settingsUpdated').textContent = Number.isFinite(Date.parse(state.updatedAt)) ? 'Disimpan ' + preview.formatDate(state.updatedAt, true) : 'Belum ada perubahan tersimpan.';
  }
  function fillSection(section) {
    form(section).querySelectorAll('[data-setting]').forEach(input => { input.value = state[section][input.dataset.setting.split('.')[1]]; });
    if (section === 'identity') { logoReadId++; logo = state.identity.logo; ui.find('settingsLogoFile').value = ''; paintLogo(); }
    if (section === 'smtp') clearPassword();
    clearErrors(section);
  }
  root.querySelectorAll('.settings-form').forEach(element => {
    const section = element.dataset.section;
    fillSection(section);
    element.addEventListener('submit', async event => {
      event.preventDefault(); if (saving.has(section)) return;
      const values = readSection(section), errors = settings.validate(section, values);
      showErrors(section, errors); if (Object.keys(errors).length) return;
      saving.add(section); const button = element.querySelector('[type="submit"]'); button.disabled = true; paintHelpers();
      try {
        const response = await api.request(api.urls.settings + '/' + section, { method: 'PUT', data: values });
        api.setSettings(response.data); state = settings.load(ui.storage); fillSection(section); paintSaved();
        ui.notify(section === 'smtp' ? 'Pengaturan SMTP tersimpan. Pengirim simulasi mengikuti pengaturan ini.' : 'Pengaturan tersimpan.');
      } catch (error) { showErrors(section, error.errors || {}); ui.notify(error.message); }
      finally { saving.delete(section); button.disabled = false; paintHelpers(); }
    });
    element.querySelector('[data-settings-discard]').addEventListener('click', () => {
      if (saving.has(section)) return;
      state = settings.load(ui.storage); fillSection(section); paintHelpers(); paintSaved(); ui.notify('Isian dikembalikan ke pengaturan yang tersimpan.');
    });
    element.addEventListener('input', event => {
      if (event.target.matches('[data-setting]')) {
        event.target.classList.remove('is-invalid'); event.target.setAttribute('aria-invalid', 'false');
        const feedback = element.querySelector('[data-setting-error="' + event.target.dataset.setting + '"]');
        if (feedback) feedback.textContent = '';
      }
      if (event.target.id === 'settingsSmtpPassword') { event.target.classList.remove('is-invalid'); form('smtp').querySelector('[data-setting-error="smtp.password"]').textContent = ''; }
      paintHelpers();
    });
    element.addEventListener('change', paintHelpers);
  });
  field('smtp', 'encryption').addEventListener('change', () => {
    const port = field('smtp', 'port');
    if (['587', '465'].includes(port.value)) port.value = field('smtp', 'encryption').value === 'tls' ? '465' : '587';
  });
  ui.find('toggleSettingsPassword').addEventListener('click', () => {
    const visible = ui.find('settingsSmtpPassword').type === 'password';
    ui.find('settingsSmtpPassword').type = visible ? 'text' : 'password';
    ui.find('toggleSettingsPassword').setAttribute('aria-pressed', String(visible)); ui.find('toggleSettingsPassword').setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
  });
  window.addEventListener('pagehide', clearPassword);
  ui.find('settings-smtp-tab').addEventListener('hidden.bs.tab', clearPassword);

  ui.find('settingsLogoFile').addEventListener('change', event => {
    const file = event.target.files[0], currentRead = ++logoReadId;
    const feedback = root.querySelector('[data-setting-error="identity.logo"]'); feedback.textContent = '';
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 500 * 1024) { feedback.textContent = 'Gunakan PNG, JPG, atau WebP, maksimal 500 KB.'; event.target.value = ''; return; }
    const reader = new FileReader();
    reader.onload = () => {
      const image = new Image();
      image.onload = () => { if (currentRead !== logoReadId) return; logo = reader.result; paintLogo(); };
      image.onerror = () => { if (currentRead === logoReadId) feedback.textContent = 'Gambar tidak dapat dibaca. Pilih gambar lain.'; };
      image.src = reader.result;
    };
    reader.onerror = () => { if (currentRead === logoReadId) feedback.textContent = 'File gambar tidak dapat dibaca.'; };
    reader.readAsDataURL(file);
  });
  ui.find('removeSettingsLogo').addEventListener('click', () => { logoReadId++; logo = ''; ui.find('settingsLogoFile').value = ''; paintLogo(); });

  function openTest(mode) {
    if (smtpDirty() || !state.smtp.configured || testing) { ui.notify('Simpan konfigurasi SMTP sebelum menguji.'); return; }
    const smtp = state.smtp; testMode = mode;
    ui.find('settingsTestTitle').textContent = mode === 'email' ? 'Kirim email percobaan' : 'Uji koneksi SMTP';
    ui.find('settingsTestSubmit').textContent = mode === 'email' ? 'Kirim email uji' : 'Uji koneksi';
    ui.find('settingsTestDescription').textContent = mode === 'email' ? 'Satu email percobaan akan dikirim ke alamat di bawah menggunakan SMTP yang tersimpan.' : 'Server akan menguji koneksi SMTP terenkripsi menggunakan kredensial tersimpan. Uji ini tidak mengirim email.';
    ui.find('settingsTestServer').textContent = smtp.host + ':' + smtp.port + ' · ' + (smtp.encryption === 'tls' ? 'TLS langsung' : 'STARTTLS');
    ui.find('settingsTestSender').textContent = smtp.fromName + ' <' + smtp.fromEmail + '>';
    ui.find('settingsTestRecipientField').hidden = mode !== 'email';
    ui.find('settingsTestRecipient').value = state.identity.contactEmail || JSON.parse(document.getElementById('authenticated-admin').textContent).email;
    ui.find('settingsTestRecipient').classList.remove('is-invalid');
    ui.find('settingsTestResult').classList.add('d-none'); testModal.show();
  }
  ui.find('testSettingsConnection').addEventListener('click', () => openTest('connection'));
  ui.find('testSettingsEmail').addEventListener('click', () => openTest('email'));
  ui.find('settingsTestModal').addEventListener('shown.bs.modal', () => { if (testMode === 'email') ui.find('settingsTestRecipient').focus(); });
  ui.find('settingsTestModal').addEventListener('hide.bs.modal', event => { if (testing) event.preventDefault(); });
  ui.find('settingsTestForm').addEventListener('submit', async event => {
    event.preventDefault(); if (testing) return;
    const recipient = ui.find('settingsTestRecipient');
    if (testMode === 'email' && !preview.isValidEmail(recipient.value)) { recipient.classList.add('is-invalid'); recipient.focus(); return; }
    recipient.classList.remove('is-invalid'); testing = true;
    const button = ui.find('settingsTestSubmit'), result = ui.find('settingsTestResult'); button.disabled = true;
    result.className = 'alert alert-primary mt-5 mb-0'; result.textContent = testMode === 'email' ? 'Mengirim email percobaan…' : 'Menguji koneksi SMTP…'; paintHelpers();
    try {
      const response = await api.request(testMode === 'email' ? api.urls.testEmail : api.urls.connection, { method: 'POST', data: { revision: state.smtp.revision, ...(testMode === 'email' ? { recipient: preview.normalizeEmail(recipient.value) } : {}) } });
      if (response.settings) { api.setSettings(response.settings); state = settings.load(ui.storage); paintSaved(); }
      result.className = 'alert alert-' + (response.configurationChanged ? 'info' : 'success') + ' mt-5 mb-0'; result.textContent = response.message;
    } catch (error) {
      result.className = 'alert alert-danger mt-5 mb-0'; result.textContent = error.message;
      try { const latest = await api.request(api.urls.settings); api.setSettings(latest.data); state = settings.load(ui.storage); paintSaved(); } catch {}
    } finally { testing = false; button.disabled = false; paintHelpers(); }
  });
  ui.find('previewSettingsLanding').addEventListener('click', () => {
    const landing = readSection('landing'), errors = settings.validate('landing', landing); showErrors('landing', errors);
    if (Object.keys(errors).length) return;
    const identity = state.identity;
    ui.find('settingsLandingPreview').innerHTML = `<div class="text-center mb-6">${identity.logo ? `<img src="${escape(identity.logo)}" alt="Logo perusahaan" width="80" height="80" class="object-fit-contain mb-4">` : '<div class="avatar avatar-lg mx-auto mb-4"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-shield-check icon-32px" aria-hidden="true"></i></span></div>'}<p class="small text-body-secondary text-break mb-3">${escape(identity.companyName)}</p><h4 class="text-break mb-4">${escape(landing.title)}</h4><p class="text-break mb-0">${escape(landing.message).replace(/\n/g, '<br>')}</p></div>${landing.type === 'education' ? `<div class="card"><div class="card-body"><h6 class="mb-4">Kenali sebelum mengeklik</h6><ul class="mb-0 ps-5">${landing.tips.split('\n').filter(tip => tip.trim()).map(tip => '<li class="mb-3 text-break">' + escape(tip) + '</li>').join('')}</ul></div></div>` : ''}${identity.contactEmail ? `<p class="small text-body-secondary text-center text-break mt-5 mb-0">Hubungi tim IT: ${escape(identity.contactEmail)}</p>` : ''}`;
    landingModal.show();
  });
  paintHelpers(); paintSaved();
  const section = new URLSearchParams(window.location.search).get('section');
  if (['identity', 'smtp', 'landing', 'reports', 'audit'].includes(section)) bootstrap.Tab.getOrCreateInstance(ui.find('settings-' + section + '-tab')).show();
});
