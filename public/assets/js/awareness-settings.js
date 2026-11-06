'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('settingsWorkspace');
  if (!root) return;
  const settings = window.AwarenessSettings, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI;
  let state = settings.load(ui.storage), logo = state.identity.logo, logoReadId = 0, testMode = 'connection';
  const escape = preview.escapeHtml;
  const field = (section, key) => root.querySelector('[data-setting="' + section + '.' + key + '"]');
  const form = section => root.querySelector('[data-section="' + section + '"]');
  const testModal = bootstrap.Modal.getOrCreateInstance(ui.find('settingsTestModal'));
  const landingModal = bootstrap.Modal.getOrCreateInstance(ui.find('settingsLandingModal'));

  function readSection(section) {
    const value = { ...state[section] };
    form(section).querySelectorAll('[data-setting]').forEach(input => { value[input.dataset.setting.split('.')[1]] = input.value.trim(); });
    if (section === 'identity') value.logo = logo;
    return value;
  }
  function clearPassword() {
    ui.find('settingsSmtpPassword').value = ''; ui.find('settingsSmtpPassword').type = 'password';
    ui.find('toggleSettingsPassword').setAttribute('aria-pressed', 'false'); ui.find('toggleSettingsPassword').setAttribute('aria-label', 'Tampilkan password');
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
  function paintHelpers() {
    const smtp = readSection('smtp');
    const interval = Number(smtp.sendIntervalSeconds);
    ui.find('settingsIntervalPreview').textContent = /^\d+$/.test(String(smtp.sendIntervalSeconds)) && Number.isSafeInteger(interval) && interval >= 0 ? interval === 0 ? 'Pengiriman tanpa jeda tambahan (0 detik).' : 'Jeda antar proses pengiriman: ' + interval.toLocaleString('id-ID') + ' detik. Email pertama mengikuti jadwal simulasi.' : 'Masukkan jeda berupa bilangan bulat 0 atau lebih.';
    ui.find('settingsSenderPreview').textContent = (smtp.fromName || 'Nama pengirim') + ' <' + (smtp.fromEmail || 'email-pengirim@example.com') + '>';
    ui.find('settingsTrackingExample').textContent = field('landing', 'baseUrl').value.replace(/\/+$/, '') + '/t/contoh-token';
    ui.find('settingsEducationTips').hidden = field('landing', 'type').value !== 'education';
  }
  function paintSaved() {
    ui.find('settingsSavedSender').textContent = state.smtp.fromEmail;
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
    element.addEventListener('submit', event => {
      event.preventDefault();
      const result = settings.saveSection(ui.storage, section, readSection(section), new Date().toISOString());
      if (!result.saved) {
        showErrors(section, result.errors);
        if (result.storageError) ui.notify('Pengaturan belum tersimpan. Penyimpanan sementara browser tidak tersedia atau penuh.');
        return;
      }
      state = result.value; fillSection(section); paintHelpers(); paintSaved();
      ui.notify(section === 'smtp' ? 'Pengaturan SMTP tersimpan di pratinjau. Email pengirim simulasi baru dan draf yang dibuka mengikuti pengaturan ini.' : 'Pengaturan bagian ini tersimpan sementara di tab browser.');
    });
    element.querySelector('[data-settings-discard]').addEventListener('click', () => {
      state = settings.load(ui.storage); fillSection(section); paintHelpers(); paintSaved(); ui.notify('Isian dikembalikan ke pengaturan yang tersimpan.');
    });
    element.addEventListener('input', event => {
      if (event.target.matches('[data-setting]')) {
        event.target.classList.remove('is-invalid'); event.target.setAttribute('aria-invalid', 'false');
        const feedback = element.querySelector('[data-setting-error="' + event.target.dataset.setting + '"]');
        if (feedback) feedback.textContent = '';
      }
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
    const smtp = readSection('smtp'), errors = settings.validate('smtp', smtp); showErrors('smtp', errors);
    if (Object.keys(errors).length) return;
    testMode = mode;
    ui.find('settingsTestTitle').textContent = mode === 'email' ? 'Email percobaan · Pratinjau' : 'Uji koneksi SMTP · Pratinjau';
    ui.find('settingsTestSubmit').textContent = mode === 'email' ? 'Simulasikan email uji' : 'Periksa format';
    ui.find('settingsTestServer').textContent = smtp.host + ':' + smtp.port + ' · ' + (smtp.encryption === 'tls' ? 'TLS langsung' : 'STARTTLS');
    ui.find('settingsTestSender').textContent = smtp.fromName + ' <' + smtp.fromEmail + '>';
    ui.find('settingsTestRecipientField').hidden = mode !== 'email';
    ui.find('settingsTestRecipient').value = state.identity.contactEmail; ui.find('settingsTestRecipient').classList.remove('is-invalid');
    ui.find('settingsTestResult').classList.add('d-none'); testModal.show();
  }
  ui.find('testSettingsConnection').addEventListener('click', () => openTest('connection'));
  ui.find('testSettingsEmail').addEventListener('click', () => openTest('email'));
  ui.find('settingsTestModal').addEventListener('shown.bs.modal', () => { if (testMode === 'email') ui.find('settingsTestRecipient').focus(); });
  ui.find('settingsTestForm').addEventListener('submit', event => {
    event.preventDefault();
    const recipient = ui.find('settingsTestRecipient');
    if (testMode === 'email' && !preview.isValidEmail(recipient.value)) { recipient.classList.add('is-invalid'); recipient.focus(); return; }
    recipient.classList.remove('is-invalid');
    ui.find('settingsTestResult').textContent = testMode === 'email' ? 'Pratinjau email uji untuk ' + preview.normalizeEmail(recipient.value) + ' siap. Tidak ada email yang dikirim. Pengiriman aktual tersedia setelah backend diimplementasikan.' : 'Format pengaturan SMTP lengkap. Koneksi dan autentikasi ke mail server belum diuji; hasil ini belum menunjukkan bahwa server dapat digunakan.';
    ui.find('settingsTestResult').classList.remove('d-none');
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
