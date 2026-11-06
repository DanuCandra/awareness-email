'use strict';

(function (root, factory) {
  const preview = factory();
  if (typeof module === 'object' && module.exports) module.exports = preview;
  else root.AwarenessPreview = preview;
})(typeof window === 'undefined' ? globalThis : window, function () {
  const maxRows = 10000;
  const campaigns = [
    { id: 'november', name: 'Simulasi Kenaikan Gaji 2027 — PT AST INDONESIA', date: '2026-11-05', target: 480, sent: 468, clicked: 41, hourly: [12, 9, 8, 5, 4, 3], status: 'Berjalan' },
    { id: 'october', name: 'Simulasi Pembaruan Data Payroll — PT AST INDONESIA', date: '2026-10-08', target: 320, sent: 312, clicked: 46, hourly: [15, 11, 8, 6, 4, 2], status: 'Selesai' },
    { id: 'september', name: 'Simulasi Bonus Akhir Tahun — PT AST INDONESIA', date: '2026-09-10', target: 250, sent: 245, clicked: 49, hourly: [18, 12, 8, 5, 4, 2], status: 'Selesai' }
  ];

  function normalizeEmail(value) {
    return String(value ?? '').trim().toLowerCase();
  }

  function isValidEmail(value) {
    const email = normalizeEmail(value);
    if (email.length > 254 || /\s/.test(email)) return false;
    const parts = email.split('@');
    if (parts.length !== 2) return false;
    const [local, domain] = parts;
    if (!local || local.length > 64 || local.startsWith('.') || local.endsWith('.') || local.includes('..')) return false;
    if (!/^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+$/.test(local)) return false;
    const labels = domain.split('.');
    return labels.length >= 2 && labels.every(label => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(label));
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  function parseCsv(value) {
    const text = String(value).replace(/^\uFEFF/, '');
    const firstLine = text.split(/\r?\n/).find(line => line.trim()) || '';
    const delimiter = firstLine.includes(';') ? ';' : firstLine.includes('\t') ? '\t' : ',';
    const rows = [];
    let cells = [], cell = '', quoted = false, closedQuote = false, line = 1, rowLine = 1;
    function finishRow() {
      cells.push(cell);
      rows.push({ number: rowLine, cells });
      if (rows.length > maxRows + 1) throw new Error('Maksimal 10.000 baris email per file.');
      cells = []; cell = ''; closedQuote = false;
    }
    for (let index = 0; index < text.length; index++) {
      const character = text[index];
      if (quoted) {
        if (character === '"') {
          if (text[index + 1] === '"') { cell += '"'; index++; }
          else { quoted = false; closedQuote = true; }
        } else {
          cell += character;
          if (character === '\n') line++;
        }
      } else if (character === '"') {
        if (cell.trim() || closedQuote) throw new Error('Format tanda kutip CSV tidak valid.');
        cell = ''; quoted = true;
      } else if (character === delimiter) {
        cells.push(cell); cell = ''; closedQuote = false;
      } else if (character === '\r' || character === '\n') {
        if (character === '\r' && text[index + 1] === '\n') index++;
        finishRow(); line++; rowLine = line;
      } else {
        if (closedQuote && character.trim()) throw new Error('Format tanda kutip CSV tidak valid.');
        if (!closedQuote) cell += character;
      }
    }
    if (quoted) throw new Error('Tanda kutip CSV belum ditutup.');
    if (cell || cells.length || closedQuote) finishRow();
    return rows;
  }

  function validateRows(inputRows, existingEmails = []) {
    const rows = inputRows.map((row, index) => Array.isArray(row) ? { number: index + 1, cells: row } : row);
    const headerIndex = rows.findIndex(row => row.cells.some(cell => String(cell ?? '').trim()));
    if (headerIndex < 0) throw new Error('File kosong. Gunakan template dengan judul kolom email.');
    const header = rows[headerIndex];
    if (normalizeEmail(header.cells[0]).replace(/^\uFEFF/, '') !== 'email' || header.cells.slice(1).some(cell => String(cell ?? '').trim()) || header.formula) {
      throw new Error('Gunakan satu kolom dengan judul email pada baris pertama yang terisi.');
    }
    const dataRows = rows.slice(headerIndex + 1);
    if (dataRows.length > maxRows) throw new Error('Maksimal 10.000 baris email per file.');
    const existing = new Set([...existingEmails].map(normalizeEmail));
    const seen = new Set();
    const result = { rows: [], valid: [], duplicates: 0, invalid: 0, blank: 0 };
    for (const row of dataRows) {
      if (!row.formula && row.cells.every(cell => !String(cell ?? '').trim())) { result.blank++; continue; }
      const email = normalizeEmail(row.cells[0]);
      const item = { number: row.number, value: String(row.cells[0] ?? '').trim(), email, status: 'valid', reason: 'Siap ditambahkan' };
      if (row.formula || row.cells.slice(1).some(cell => String(cell ?? '').trim()) || !isValidEmail(email)) {
        item.status = 'invalid';
        item.reason = row.formula ? 'Gunakan nilai email, bukan rumus' : row.cells.slice(1).some(cell => String(cell ?? '').trim()) ? 'Hanya satu kolom email yang diperbolehkan' : 'Format email tidak valid';
        result.invalid++;
      } else if (seen.has(email) || existing.has(email)) {
        item.status = 'duplicate';
        item.reason = seen.has(email) ? 'Berulang di dalam file' : 'Sudah ada di daftar email';
        result.duplicates++;
      } else {
        result.valid.push(email);
      }
      if (item.status !== 'invalid') seen.add(email);
      result.rows.push(item);
    }
    if (!result.rows.length) throw new Error('Belum ada email di file ini. Isi alamat email di bawah judul kolom.');
    return result;
  }

  function parseXml(value) {
    if (value.length > 8 * 1024 * 1024 || /<!DOCTYPE|<!ENTITY/i.test(value)) throw new Error('Isi worksheet tidak didukung atau terlalu besar.');
    const document = new DOMParser().parseFromString(value, 'application/xml');
    if (document.getElementsByTagName('parsererror').length) throw new Error('Struktur file Excel tidak valid.');
    return document;
  }

  const elements = (node, name) => [...node.getElementsByTagNameNS('*', name)];

  async function readWorkbook(buffer, Zip) {
    const zip = await Zip.loadAsync(buffer);
    async function readXml(path, optional = false) {
      const entry = zip.file(path);
      if (!entry) {
        if (optional) return null;
        throw new Error('File Excel tidak lengkap. Simpan ulang sebagai .xlsx atau gunakan template.');
      }
      return parseXml(await entry.async('string'));
    }
    const workbook = await readXml('xl/workbook.xml');
    const relationships = await readXml('xl/_rels/workbook.xml.rels');
    const firstSheet = elements(workbook, 'sheet')[0];
    if (!firstSheet) throw new Error('Worksheet tidak ditemukan.');
    const relationshipId = firstSheet.getAttribute('r:id') || firstSheet.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
    const relation = elements(relationships, 'Relationship').find(item => item.getAttribute('Id') === relationshipId);
    const target = relation?.getAttribute('Target');
    if (!target || relation.getAttribute('TargetMode') === 'External' || /^[a-z]+:/i.test(target)) throw new Error('Worksheet pertama tidak didukung.');
    const sheetPath = new URL(target.replace(/\\/g, '/'), 'https://workbook.invalid/xl/').pathname.slice(1);
    const worksheet = await readXml(sheetPath);
    const sharedXml = await readXml('xl/sharedStrings.xml', true);
    const shared = sharedXml ? elements(sharedXml, 'si').map(item => elements(item, 't').map(text => text.textContent).join('')) : [];
    const sheetRows = elements(worksheet, 'row');
    if (sheetRows.length > maxRows + 1) throw new Error('Maksimal 10.000 baris email per file.');
    return sheetRows.map((row, index) => {
      const cells = [];
      let formula = false;
      for (const cell of elements(row, 'c')) {
        const reference = cell.getAttribute('r') || 'A';
        const column = reference.replace(/\d+/g, '');
        const raw = elements(cell, 'v')[0]?.textContent || '';
        const type = cell.getAttribute('t');
        const value = type === 's' ? shared[Number(raw)] ?? '' : type === 'inlineStr' ? elements(cell, 't').map(text => text.textContent).join('') : raw;
        if (elements(cell, 'f').length) formula = true;
        if (column === 'A') cells[0] = value;
        else if (value.trim()) cells[1] = value;
      }
      return { number: Number(row.getAttribute('r')) || index + 1, cells: [cells[0] ?? '', ...cells.slice(1)], formula };
    });
  }

  async function readFile(file, Zip) {
    if (file.size > 5 * 1024 * 1024) throw new Error('Ukuran file maksimal 5 MB.');
    const extension = file.name.split('.').pop().toLowerCase();
    if (extension === 'csv') return parseCsv(await file.text());
    if (extension !== 'xlsx') throw new Error('Pilih file .xlsx atau .csv. Format .xls belum didukung.');
    try { return await readWorkbook(await file.arrayBuffer(), Zip); }
    catch (error) {
      if (/zip|encrypted|password|end of central/i.test(error.message)) throw new Error('File Excel tidak dapat dibaca. Gunakan .xlsx tanpa kata sandi.');
      throw error;
    }
  }

  async function createTemplate(Zip) {
    const zip = new Zip();
    const namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    zip.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    zip.file('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    zip.file('xl/workbook.xml', `<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="${namespace}" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Email" sheetId="1" r:id="rId1"/></sheets></workbook>`);
    zip.file('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    zip.file('xl/worksheets/sheet1.xml', `<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="${namespace}"><cols><col min="1" max="1" width="42" customWidth="1"/></cols><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>email</t></is></c></row></sheetData></worksheet>`);
    return zip.generateAsync({ type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  }

  function createRecipients() {
    return Array.from({ length: 480 }, (_, index) => ({ id: index + 1, email: `karyawan${String(index + 1).padStart(3, '0')}@perusahaan.example`, source: 'Excel', addedAt: '2026-11-05T08:00:00+07:00' }));
  }

  function metrics(campaign) {
    return { failed: campaign.target - campaign.sent, unclicked: campaign.sent - campaign.clicked, rate: campaign.sent ? campaign.clicked / campaign.sent * 100 : 0 };
  }

  function formatDate(value, withTime = false) {
    return new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', ...(withTime ? { hour: '2-digit', minute: '2-digit', hour12: false } : {}) }).format(new Date(value)) + (withTime ? ' WIB' : '');
  }

  function toCsv(emails) {
    return '\uFEFFemail\r\n' + emails.map(email => '"' + (/^[=+\-@\t\r]/.test(email) ? "'" : '') + email.replace(/"/g, '""') + '"').join('\r\n');
  }

  function download(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = filename;
    document.body.append(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  return { campaigns, normalizeEmail, isValidEmail, escapeHtml, parseCsv, validateRows, readFile, readWorkbook, createTemplate, createRecipients, metrics, formatDate, toCsv, download };
});
