'use strict';

(function (root, factory) {
  const api = factory(root.AwarenessPreview);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.AwarenessCampaigns = api;
})(typeof window === 'undefined' ? globalThis : window, function (preview) {
  const storageKey = 'security-awareness.stage3.v1';
  const categories = ['HR & Payroll', 'Administrasi', 'Benefit Karyawan'];
  const defaultSender = { name: 'HRD PT AST INDONESIA', email: 'hrd@ast-indonesia.example' };
  const statuses = { draft: { label: 'Draf', color: 'secondary' }, scheduled: { label: 'Terjadwal', color: 'info' }, running: { label: 'Berjalan', color: 'success' }, completed: { label: 'Selesai', color: 'primary' } };
  const clone = value => JSON.parse(JSON.stringify(value));
  const text = value => typeof value === 'string' ? value : '';
  const escape = preview.escapeHtml;
  const token = { email: '{{email}}', campaign: '{{campaign_name}}', link: '{{tracking_link}}' };

  function normalizeDelta(delta) {
    const ops = Array.isArray(delta?.ops) ? delta.ops : [];
    return { ops: ops.slice(0, 5000).filter(op => typeof op.insert === 'string').map(op => {
      const attributes = {};
      for (const key of ['bold', 'italic', 'underline', 'strike', 'blockquote']) if (op.attributes?.[key] === true) attributes[key] = true;
      if ([1, 2, 3].includes(op.attributes?.header)) attributes.header = op.attributes.header;
      if (['ordered', 'bullet'].includes(op.attributes?.list)) attributes.list = op.attributes.list;
      if (typeof op.attributes?.link === 'string' && /^https?:\/\//i.test(op.attributes.link)) attributes.link = op.attributes.link.slice(0, 1000);
      return { insert: op.insert.slice(0, 32000), ...(Object.keys(attributes).length ? { attributes } : {}) };
    }) };
  }

  function messageText(delta) { return normalizeDelta(delta).ops.map(op => op.insert).join(''); }

  function templateSamples() {
    return [
      {
        id: 'layanan', name: 'Kenaikan Gaji 2027 — PT AST INDONESIA', category: 'HR & Payroll',
        subject: 'Informasi Kenaikan Gaji PT AST INDONESIA Tahun 2027', updatedAt: '2026-11-04T08:00:00+07:00',
        delta: { ops: [
          { insert: 'Penyesuaian Gaji Karyawan Tahun 2027' }, { insert: '\n', attributes: { header: 2 } },
          { insert: 'Yth. Bapak/Ibu Karyawan PT AST INDONESIA,\n\nKami informasikan bahwa penyesuaian gaji karyawan untuk tahun 2027 telah disetujui. Rincian nominal kenaikan dan tanggal berlakunya penyesuaian telah tersedia pada portal personalia.\n\nSilakan periksa rincian kenaikan gaji Anda dan konfirmasikan penerimaan informasi paling lambat pukul 17.00 WIB hari ini melalui tautan berikut:\n\n{{tracking_link}}\n\nInformasi ini bersifat pribadi. Mohon tidak meneruskan email ini kepada pihak lain.\n\nPemberitahuan untuk: {{email}}\n\nHormat kami,\nHRD PT AST INDONESIA\n' }
        ] }
      },
      {
        id: 'pelatihan', name: 'Pembaruan Data Payroll 2027', category: 'Administrasi',
        subject: 'Segera Perbarui Data Payroll Anda Sebelum Penyesuaian Gaji 2027', updatedAt: '2026-11-03T10:00:00+07:00',
        delta: { ops: [
          { insert: 'Verifikasi Data Payroll Karyawan' }, { insert: '\n', attributes: { header: 2 } },
          { insert: 'Yth. Bapak/Ibu Karyawan PT AST INDONESIA,\n\nDalam persiapan penyesuaian gaji tahun 2027, kami menemukan data administrasi payroll untuk alamat email {{email}} belum terkonfirmasi pada sistem personalia.\n\nUntuk menghindari keterlambatan proses penyesuaian gaji, mohon segera periksa status data Anda melalui tautan di bawah ini. Konfirmasi ditunggu paling lambat pukul 16.00 WIB hari ini.\n\n{{tracking_link}}\n\nApabila konfirmasi belum diterima sampai batas waktu tersebut, proses pembaruan payroll Anda akan masuk ke periode berikutnya.\n\nHormat kami,\nAdministrasi Payroll\nPT AST INDONESIA\n' }
        ] }
      },
      {
        id: 'kebijakan', name: 'Bonus Akhir Tahun — PT AST INDONESIA', category: 'Benefit Karyawan',
        subject: 'Konfirmasi Penerimaan Bonus Akhir Tahun PT AST INDONESIA', updatedAt: '2026-11-02T09:00:00+07:00',
        delta: { ops: [
          { insert: 'Pemberitahuan Bonus Akhir Tahun' }, { insert: '\n', attributes: { header: 2 } },
          { insert: 'Yth. Bapak/Ibu Karyawan PT AST INDONESIA,\n\nAlamat email {{email}} tercatat dalam daftar penerima bonus akhir tahun perusahaan. Rincian nominal bonus dan jadwal pencairan dapat dilihat melalui portal benefit karyawan.\n\nMohon tinjau rincian tersebut dan lakukan konfirmasi penerimaan dalam waktu 1 x 24 jam agar data Anda dapat dimasukkan ke jadwal pencairan terdekat.\n\n{{tracking_link}}\n\nInformasi nominal bonus hanya ditampilkan melalui tautan personal di atas. Harap tidak membagikan tautan ini kepada karyawan lain.\n\nHormat kami,\nHRD & Benefit Karyawan\nPT AST INDONESIA\n' }
        ] }
      }
    ];
  }

  function initialState() {
    const templates = templateSamples();
    const campaigns = preview.campaigns.map((campaign, index) => ({
      ...clone(campaign), status: index === 0 ? 'running' : 'completed',
      description: ['Simulasi phishing dengan iming-iming kenaikan gaji 2027 dan batas waktu konfirmasi.', 'Simulasi phishing yang menekan penerima agar segera memperbarui data payroll.', 'Simulasi phishing dengan iming-iming bonus akhir tahun dan tautan personal.'][index],
      senderName: defaultSender.name, senderEmail: defaultSender.email,
      scheduledAt: campaign.date + 'T09:00', endsAt: new Date(Date.parse(campaign.date + 'T00:00:00Z') + 3 * 86400000).toISOString().slice(0, 10) + 'T17:00', recipientMode: campaign.target === 480 ? 'all' : 'selected', recipientIds: Array.from({ length: campaign.target }, (_, id) => id + 1),
      templateId: templates[index].id, message: { subject: templates[index].subject, delta: clone(templates[index].delta) }, updatedAt: campaign.date + 'T09:00:00+07:00'
    }));
    campaigns.push({ id: 'draft-policy', name: 'Draf Simulasi Bonus Karyawan — PT AST INDONESIA', description: 'Persiapan simulasi phishing bertema bonus karyawan.', senderName: defaultSender.name, senderEmail: defaultSender.email, status: 'draft', scheduledAt: '', endsAt: '', recipientMode: 'all', recipientIds: [], target: 480, sent: 0, clicked: 0, templateId: 'kebijakan', message: { subject: templates[2].subject, delta: clone(templates[2].delta) }, updatedAt: '2026-11-04T13:30:00+07:00' });
    return { version: 1, categoryRevision: 1, categories: [...categories], templates, campaigns };
  }

  function refreshSampleContent(state) {
    const legacy = {
      layanan: { name: 'Pembaruan layanan internal', category: 'Internal', subject: 'Informasi pembaruan layanan internal', body: 'Informasi layanan perusahaan\nHalo {{email}},\n\nInformasi pembaruan layanan internal telah tersedia. Silakan tinjau informasi melalui tautan berikut.\n\n{{tracking_link}}\n\nTerima kasih,\nTim IT\n' },
      pelatihan: { name: 'Undangan sesi awareness', category: 'Edukasi', subject: 'Undangan sesi keamanan informasi', body: 'Sesi keamanan informasi\nHalo {{email}},\n\nInformasi kegiatan {{campaign_name}} dapat dilihat melalui tautan di bawah ini.\n\n{{tracking_link}}\n\nSalam,\nTim IT\n' },
      kebijakan: { name: 'Informasi kebijakan perangkat', category: 'Kebijakan', subject: 'Pemberitahuan kebijakan perangkat perusahaan', body: 'Informasi kebijakan perangkat\nHalo {{email}},\n\nRingkasan kebijakan penggunaan perangkat perusahaan tersedia untuk ditinjau.\n\n{{tracking_link}}\n\nTerima kasih atas perhatian Anda.\nTim IT\n' }
    };
    const fresh = initialState();
    function matchesLegacyDelta(delta, previous) {
      const headingEnd = previous.body.indexOf('\n');
      const expected = { ops: [{ insert: previous.body.slice(0, headingEnd) }, { insert: '\n', attributes: { header: 2 } }, { insert: previous.body.slice(headingEnd + 1) }] };
      return JSON.stringify(normalizeDelta(delta)) === JSON.stringify(normalizeDelta(expected));
    }
    state.templates = state.templates.map(item => {
      const previous = legacy[item.id];
      return previous && item.name === previous.name && item.category === previous.category && item.subject === previous.subject && matchesLegacyDelta(item.delta, previous) ? clone(fresh.templates.find(sample => sample.id === item.id)) : item;
    });
    const oldCampaignNames = { november: 'Awareness November 2026', october: 'Awareness Oktober 2026', september: 'Awareness September 2026', 'draft-policy': 'Simulasi kebijakan perangkat' };
    state.campaigns.forEach(item => {
      const previous = legacy[item.templateId];
      const sample = fresh.campaigns.find(record => record.id === item.id);
      if (!previous || !sample || item.name !== oldCampaignNames[item.id] || item.message.subject !== previous.subject || !matchesLegacyDelta(item.message.delta, previous)) return;
      item.name = sample.name;
      item.message = clone(sample.message);
      if (item.senderName === 'Tim IT Awareness') item.senderName = defaultSender.name;
      if (item.senderEmail === 'awareness@perusahaan.example') item.senderEmail = defaultSender.email;
      if (['Simulasi internal untuk evaluasi awareness terhadap tautan email.', 'Persiapan simulasi lanjutan.'].includes(item.description)) item.description = sample.description;
    });
    return state;
  }

  function load(storage) {
    try {
      const raw = storage.getItem(storageKey);
      if (!raw) return initialState();
      if (raw.length > 1000000) return initialState();
      const state = JSON.parse(raw);
      const validRecord = item => item && typeof item.id === 'string' && /^[a-z0-9-]+$/i.test(item.id) && typeof item.name === 'string';
      if (state.version !== 1 || !Array.isArray(state.templates) || !Array.isArray(state.campaigns) || !state.templates.every(validRecord) || !state.campaigns.every(item => validRecord(item) && Object.prototype.hasOwnProperty.call(statuses, item.status) && item.message && Array.isArray(item.recipientIds))) return initialState();
      state.templates.forEach(item => { item.delta = normalizeDelta(item.delta); });
      state.campaigns.forEach(item => { item.message.delta = normalizeDelta(item.message.delta); });
      const samples = initialState().campaigns;
      state.campaigns.forEach(item => {
        if (Object.prototype.hasOwnProperty.call(item, 'endsAt')) return;
        const sample = samples.find(value => value.id === item.id && value.name === item.name && value.status === item.status && value.scheduledAt === item.scheduledAt);
        item.endsAt = sample?.endsAt || '';
      });
      return normalizeCategoryState(refreshSampleContent(state));
    } catch { return initialState(); }
  }

  function save(storage, state) {
    try {
      const encoded = JSON.stringify(state);
      if (encoded.length > 1000000) return false;
      storage.setItem(storageKey, encoded);
      return true;
    } catch { return false; }
  }

  function newId(prefix) { return prefix + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 9); }

  function normalizeCategoryName(value) { return text(value).normalize('NFC').trim().replace(/\s+/g, ' '); }

  function normalizeCategoryState(state) {
    const retiring = state.categoryRevision !== 1;
    const legacy = { internal: 'HR & Payroll', edukasi: 'Administrasi', kebijakan: 'Benefit Karyawan' };
    const names = Array.isArray(state.categories) ? state.categories : [...categories];
    if (!Array.isArray(state.categories)) state.templates.forEach(item => names.push(item.category));
    const registry = [];
    names.forEach(value => {
      let name = normalizeCategoryName(value);
      if (retiring && Object.prototype.hasOwnProperty.call(legacy, name.toLowerCase())) name = legacy[name.toLowerCase()];
      if (name && name.length <= 60 && !registry.some(item => item.toLowerCase() === name.toLowerCase())) registry.push(name);
    });
    state.categories = registry;
    state.categoryRevision = 1;
    state.templates.forEach(item => {
      let name = normalizeCategoryName(item.category);
      if (retiring && Object.prototype.hasOwnProperty.call(legacy, name.toLowerCase())) name = legacy[name.toLowerCase()];
      item.category = registry.find(value => value.toLowerCase() === name.toLowerCase()) || registry[0] || '';
    });
    return state;
  }

  function categoryNameError(value, available, previous = '') {
    const name = normalizeCategoryName(value);
    if (!name || name.length > 60) return 'Isi nama kategori, maksimal 60 karakter.';
    if (available.some(item => item !== previous && item.toLowerCase() === name.toLowerCase())) return 'Nama kategori ini sudah digunakan.';
    return '';
  }

  function addCategory(state, value) {
    const name = normalizeCategoryName(value);
    const error = categoryNameError(name, state.categories);
    if (error) throw new Error(error);
    state.categories.push(name);
    return name;
  }

  function renameCategory(state, previous, value) {
    if (!state.categories.includes(previous)) throw new Error('Kategori tidak ditemukan.');
    const name = normalizeCategoryName(value);
    const error = categoryNameError(name, state.categories, previous);
    if (error) throw new Error(error);
    state.categories = state.categories.map(item => item === previous ? name : item);
    state.templates.forEach(item => { if (item.category === previous) item.category = name; });
    return name;
  }

  function removeCategory(state, name, replacement = '') {
    if (!state.categories.includes(name)) throw new Error('Kategori tidak ditemukan.');
    const templates = state.templates.filter(item => item.category === name);
    if (templates.length && (!state.categories.includes(replacement) || replacement === name)) throw new Error('Pilih kategori pengganti untuk template yang masih menggunakan kategori ini.');
    templates.forEach(item => { item.category = replacement; });
    state.categories = state.categories.filter(item => item !== name);
    return templates.length;
  }

  function recipientsFor(campaign, available = campaign.recipientRegistry || preview.createRecipients()) {
    const ids = new Set(available.map(item => item.id));
    if (campaign.recipientMode === 'all') return [...ids];
    return [...new Set((campaign.recipientIds || []).filter(id => Number.isInteger(id) && ids.has(id)))];
  }

  function parseSchedule(value) {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(text(value))) return NaN;
    const timestamp = Date.parse(value + ':00+07:00');
    if (!Number.isFinite(timestamp)) return NaN;
    const roundTrip = new Date(timestamp + 7 * 3600000).toISOString().slice(0, 16);
    return roundTrip === value ? timestamp : NaN;
  }

  function scheduleLabel(value) { return Number.isFinite(parseSchedule(value)) ? preview.formatDate(value + ':00+07:00', true) : 'Belum ditentukan'; }

  function validatePeriod(campaign, required = false) {
    const errors = {}, start = parseSchedule(campaign.scheduledAt), end = parseSchedule(campaign.endsAt);
    if ((required || campaign.scheduledAt || campaign.endsAt) && !Number.isFinite(start)) errors.schedule = 'Isi tanggal dan jam mulai yang valid dalam zona waktu WIB.';
    if ((required || campaign.endsAt) && !Number.isFinite(end)) errors.endsAt = 'Isi tanggal dan jam selesai yang valid dalam zona waktu WIB.';
    else if (Number.isFinite(start) && Number.isFinite(end) && end <= start) errors.endsAt = 'Tanggal dan jam selesai harus setelah waktu mulai.';
    return errors;
  }

  function validateMessage(message) {
    const errors = {};
    if (!text(message.subject).trim() || text(message.subject).length > 180) errors.subject = 'Isi subjek email, maksimal 180 karakter.';
    const body = messageText(message.delta).trim();
    if (!body || body.length > 20000) errors.body = 'Isi pesan email, maksimal 20.000 karakter.';
    else if (!body.includes(token.link)) errors.body = 'Sisipkan tautan simulasi ke dalam isi email.';
    return errors;
  }

  function validateTemplate(item, availableCategories = categories) {
    const errors = validateMessage(item);
    if (!text(item.name).trim() || text(item.name).length > 100) errors.name = 'Isi nama template, maksimal 100 karakter.';
    if (!availableCategories.includes(item.category)) errors.category = 'Pilih kategori template yang tersedia.';
    return errors;
  }

  function validateCampaign(campaign, options = {}) {
    const errors = {};
    if (!text(campaign.name).trim() || text(campaign.name).length > 120) errors.name = 'Isi nama simulasi, maksimal 120 karakter.';
    if (!text(campaign.senderName).trim() || text(campaign.senderName).length > 80) errors.senderName = 'Isi nama pengirim, maksimal 80 karakter.';
    if (!preview.isValidEmail(campaign.senderEmail)) errors.senderEmail = 'Masukkan alamat email pengirim yang valid.';
    if (!recipientsFor(campaign, options.recipients).length) errors.recipients = 'Pilih minimal satu email penerima.';
    Object.assign(errors, validateMessage(campaign.message || {}));
    Object.assign(errors, validatePeriod(campaign, !!options.schedule));
    if (options.schedule && (!Number.isFinite(parseSchedule(campaign.scheduledAt)) || parseSchedule(campaign.scheduledAt) <= (options.now ?? Date.now()))) errors.schedule = 'Pilih jadwal mendatang dalam zona waktu WIB.';
    return errors;
  }

  function duplicateCampaign(campaign, id, now) {
    return { ...clone(campaign), id, name: ('Salinan ' + campaign.name).slice(0, 120), status: 'draft', scheduledAt: '', endsAt: '', sent: 0, clicked: 0, hourly: [], updatedAt: now };
  }

  function inlineHtml(parts, context) {
    function styled(value, attributes = {}) {
      let html = escape(value);
      if (attributes.bold) html = '<strong>' + html + '</strong>';
      if (attributes.italic) html = '<em>' + html + '</em>';
      if (attributes.underline) html = '<u>' + html + '</u>';
      if (attributes.strike) html = '<s>' + html + '</s>';
      if (attributes.link && /^https?:\/\//i.test(attributes.link)) html = '<a href="#preview-link">' + html + '</a>';
      return html;
    }
    const raw = parts.map(part => part.text).join('');
    function slice(start, end) {
      let offset = 0;
      return parts.map(part => {
        const left = Math.max(0, start - offset), right = Math.min(part.text.length, end - offset);
        offset += part.text.length;
        return right > left ? styled(part.text.slice(left, right), part.attributes) : '';
      }).join('');
    }
    let result = '', cursor = 0;
    for (const match of raw.matchAll(/\{\{(email|campaign_name|tracking_link)\}\}/g)) {
      result += slice(cursor, match.index);
      result += match[1] === 'tracking_link' ? '<a class="email-cta" href="#preview-link">Lihat informasi</a>' : escape(match[1] === 'email' ? context.email || 'karyawan001@perusahaan.example' : context.campaignName || 'Simulasi Awareness');
      cursor = match.index + match[0].length;
    }
    return result + slice(cursor, raw.length);
  }

  function renderBody(delta, context = {}) {
    const lines = [];
    let parts = [];
    normalizeDelta(delta).ops.forEach(op => {
      const chunks = op.insert.split('\n');
      chunks.forEach((chunk, index) => {
        if (chunk) parts.push({ text: chunk, attributes: op.attributes });
        if (index < chunks.length - 1) { lines.push({ parts, attributes: op.attributes || {} }); parts = []; }
      });
    });
    if (parts.length) lines.push({ parts, attributes: {} });
    let output = '', list = null;
    for (const line of lines) {
      const nextList = line.attributes.list === 'ordered' ? 'ol' : line.attributes.list === 'bullet' ? 'ul' : null;
      if (nextList !== list) {
        if (list) output += '</' + list + '>';
        if (nextList) output += '<' + nextList + '>';
        list = nextList;
      }
      const content = inlineHtml(line.parts, context) || '<br>';
      const tag = list ? 'li' : line.attributes.header ? 'h' + line.attributes.header : line.attributes.blockquote ? 'blockquote' : 'p';
      output += '<' + tag + '>' + content + '</' + tag + '>';
    }
    if (list) output += '</' + list + '>';
    return output;
  }

  function emailDocument(delta, context = {}, accent = '#7367f0') {
    const safeAccent = /^#[a-f0-9]{6}$/i.test(accent) ? accent : '#7367f0';
    return '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;; form-action &#39;none&#39;; base-uri &#39;none&#39;"><style>body{margin:0;padding:24px;font:14px/1.65 Arial,sans-serif;color:#444050;background:#fff;overflow-wrap:anywhere}p{margin:0 0 14px}h1,h2,h3{line-height:1.3;margin:0 0 18px}h1{font-size:24px}h2{font-size:21px}h3{font-size:18px}a{color:' + safeAccent + '}a.email-cta{display:inline-block;padding:10px 18px;background:' + safeAccent + ';color:#fff;border-radius:5px;text-decoration:none}blockquote{border-left:3px solid #dedde4;padding-left:12px;margin:0 0 14px;color:#6d6b77}li{margin-bottom:6px}</style></head><body>' + renderBody(delta, context) + '</body></html>';
  }

  return { storageKey, categories, defaultSender, statuses, token, clone, normalizeDelta, messageText, initialState, load, save, newId, normalizeCategoryName, normalizeCategoryState, categoryNameError, addCategory, renameCategory, removeCategory, recipientsFor, parseSchedule, scheduleLabel, validatePeriod, validateMessage, validateTemplate, validateCampaign, duplicateCampaign, renderBody, emailDocument };
});
