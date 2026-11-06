'use strict';

(function (root, factory) {
  const api = factory(root.AwarenessPreview, root.AwarenessCampaigns);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.AwarenessSettings = api;
})(typeof window === 'undefined' ? globalThis : window, function (preview, campaigns) {
  const storageKey = 'security-awareness.settings.v1';
  const text = value => typeof value === 'string' ? value.trim() : '';
  const sections = ['identity', 'smtp', 'landing', 'reports', 'audit'];

  function defaults() {
    return {
      version: 1,
      identity: { companyName: 'PT AST INDONESIA', contactEmail: 'it@ast-indonesia.example', logo: '' },
      smtp: { host: 'smtp.mail-provider.example', port: '587', encryption: 'starttls', username: campaigns.defaultSender.email, fromEmail: campaigns.defaultSender.email, fromName: campaigns.defaultSender.name, replyTo: '', sendIntervalSeconds: 0 },
      landing: { baseUrl: 'https://awareness.ast-indonesia.example', type: 'education', title: 'Ini adalah simulasi keamanan email', message: 'Anda baru saja membuka tautan dalam simulasi phishing perusahaan. Gunakan kesempatan ini untuk mengenali ciri email yang mencurigakan.', tips: 'Periksa alamat pengirim sebelum memercayai isi email.\nWaspadai permintaan mendesak dan iming-iming hadiah atau kenaikan gaji.\nKonfirmasikan informasi melalui kanal resmi perusahaan.' },
      reports: { timezone: 'Asia/Jakarta', pageLength: 10, period: 'all' },
      audit: { retentionMonths: 6 }, updatedAt: ''
    };
  }

  function normalize(value) {
    const fallback = defaults(), item = value || {};
    const strings = (section, keys) => Object.fromEntries(keys.map(key => [key, text(item[section]?.[key] ?? fallback[section][key])]));
    return {
      version: 1,
      identity: strings('identity', ['companyName', 'contactEmail', 'logo']),
      smtp: { ...strings('smtp', ['host', 'encryption', 'username', 'fromEmail', 'fromName', 'replyTo']), port: String(item.smtp?.port ?? fallback.smtp.port).trim(), sendIntervalSeconds: item.smtp?.sendIntervalSeconds ?? 0 },
      landing: strings('landing', ['baseUrl', 'type', 'title', 'message', 'tips']),
      reports: { timezone: 'Asia/Jakarta', pageLength: Number(item.reports?.pageLength ?? 10), period: String(item.reports?.period ?? 'all') },
      audit: { retentionMonths: Number(item.audit?.retentionMonths ?? 6) }, updatedAt: text(item.updatedAt)
    };
  }

  function validate(section, value) {
    const errors = {}, item = value || {};
    const length = (key, max, label) => { if (!text(item[key]) || text(item[key]).length > max) errors[key] = 'Isi ' + label + ', maksimal ' + max + ' karakter.'; };
    if (section === 'identity') {
      length('companyName', 100, 'nama perusahaan');
      if (item.contactEmail && !preview.isValidEmail(item.contactEmail)) errors.contactEmail = 'Masukkan email kontak IT yang valid.';
      if (item.logo && (item.logo.length > 700000 || !/^data:image\/(?:png|jpeg|webp);base64,[A-Za-z0-9+/]+=*$/.test(item.logo))) errors.logo = 'Gunakan gambar PNG, JPG, atau WebP, maksimal 500 KB.';
    } else if (section === 'smtp') {
      const host = text(item.host);
      if (!host || host.length > 253 || !host.split('.').every(label => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(label))) errors.host = 'Isi hostname SMTP tanpa http:// atau https://.';
      if (!/^\d{1,5}$/.test(String(item.port)) || Number(item.port) < 1 || Number(item.port) > 65535) errors.port = 'Port harus berupa angka antara 1–65535.';
      if (!['starttls', 'tls'].includes(item.encryption)) errors.encryption = 'Pilih enkripsi STARTTLS atau TLS.';
      length('username', 254, 'username SMTP'); length('fromName', 80, 'nama pengirim');
      if (!preview.isValidEmail(item.fromEmail)) errors.fromEmail = 'Masukkan email pengirim yang valid.';
      if (item.replyTo && !preview.isValidEmail(item.replyTo)) errors.replyTo = 'Masukkan alamat Reply-To yang valid atau kosongkan.';
      if (!/^\d+$/.test(String(item.sendIntervalSeconds)) || !Number.isSafeInteger(Number(item.sendIntervalSeconds)) || Number(item.sendIntervalSeconds) < 0) errors.sendIntervalSeconds = 'Isi jeda berupa bilangan bulat 0 atau lebih. Nilai 0 berarti tanpa jeda.';
    } else if (section === 'landing') {
      try { const url = new URL(item.baseUrl); if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password || url.search || url.hash) throw new Error(); }
      catch { errors.baseUrl = 'Gunakan URL http/https tanpa kredensial, query, atau fragmen.'; }
      if (text(item.baseUrl).length > 250) errors.baseUrl = 'URL maksimal 250 karakter.';
      if (!['education', 'confirmation'].includes(item.type)) errors.type = 'Pilih jenis halaman yang tersedia.';
      length('title', 120, 'judul halaman'); length('message', 1200, 'pesan halaman');
      if (text(item.tips).length > 1500) errors.tips = 'Tips maksimal 1.500 karakter.';
      else if (item.type === 'education' && !text(item.tips)) errors.tips = 'Tambahkan minimal satu tips keamanan.';
    } else if (section === 'reports') {
      if (![10, 25, 50].includes(Number(item.pageLength))) errors.pageLength = 'Pilih 10, 25, atau 50 baris.';
      if (!['all', '30', '90'].includes(String(item.period))) errors.period = 'Pilih periode laporan yang tersedia.';
    } else if (section === 'audit' && ![3, 6, 12].includes(Number(item.retentionMonths))) errors.retentionMonths = 'Pilih masa penyimpanan 3, 6, atau 12 bulan.';
    return errors;
  }

  function load(storage) {
    try {
      const raw = storage.getItem(storageKey);
      if (!raw || raw.length > 900000) return defaults();
      const parsed = JSON.parse(raw);
      if (parsed.version !== 1) return defaults();
      const normalized = normalize(parsed);
      if (sections.some(section => Object.keys(validate(section, normalized[section])).length)) return defaults();
      return normalized;
    } catch { return defaults(); }
  }

  function saveSection(storage, section, value, now) {
    if (!sections.includes(section)) return { saved: false, errors: { section: 'Bagian pengaturan tidak tersedia.' } };
    const current = load(storage), next = normalize({ ...current, [section]: value });
    const errors = validate(section, next[section]);
    if (Object.keys(errors).length) return { saved: false, errors };
    if (section === 'smtp') { next.smtp.fromEmail = preview.normalizeEmail(next.smtp.fromEmail); next.smtp.replyTo = preview.normalizeEmail(next.smtp.replyTo); next.smtp.sendIntervalSeconds = Number(next.smtp.sendIntervalSeconds); }
    if (section === 'landing') next.landing.baseUrl = next.landing.baseUrl.replace(/\/+$/, '');
    next.updatedAt = now;
    try { storage.setItem(storageKey, JSON.stringify(next)); return { saved: true, value: next, errors: {} }; }
    catch { return { saved: false, errors: {}, storageError: true }; }
  }

  function campaignSender(settings, campaign = {}) {
    return { senderEmail: settings.smtp.fromEmail, senderName: campaign.senderName || settings.smtp.fromName, replyTo: settings.smtp.replyTo };
  }

  function reportDefaults(settings, today) {
    if (settings.reports.period === 'all') return { from: '', to: '', pageLength: settings.reports.pageLength };
    const date = new Date(today + 'T00:00:00Z');
    if (!Number.isFinite(date.getTime())) return { from: '', to: '', pageLength: settings.reports.pageLength };
    date.setUTCDate(date.getUTCDate() - Number(settings.reports.period) + 1);
    return { from: date.toISOString().slice(0, 10), to: today, pageLength: settings.reports.pageLength };
  }

  return { storageKey, defaults, normalize, validate, load, saveSection, campaignSender, reportDefaults };
});
