const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const sandbox = { module: { exports: {} }, URL, Intl, Blob, setTimeout };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/awareness-preview.js'), 'utf8'), sandbox);
const preview = sandbox.module.exports;

test('campaign denominators and hourly click counts agree', () => {
  for (const campaign of preview.campaigns) {
    assert.equal(campaign.hourly.reduce((sum, count) => sum + count, 0), campaign.clicked);
    const result = preview.metrics(campaign);
    assert.equal(result.failed + campaign.sent, campaign.target);
    assert.equal(result.unclicked + campaign.clicked, campaign.sent);
  }
  assert.equal(preview.metrics({ target: 480, sent: 468, clicked: 41 }).rate.toFixed(1), '8.8');
  assert.equal(preview.metrics({ target: 0, sent: 0, clicked: 0 }).rate, 0);
});

test('sample recipient registry has 480 unique valid emails', () => {
  const recipients = preview.createRecipients();
  assert.equal(recipients.length, 480);
  assert.equal(new Set(recipients.map(row => row.email)).size, 480);
  assert.ok(recipients.every(row => preview.isValidEmail(row.email)));
});

test('import separates valid emails, normalized duplicates, existing emails and invalid rows', () => {
  const result = preview.validateRows([
    ['email'], [' New.Person@Company.example '], ['new.person@company.example'],
    ['existing@company.example'], ['bad@'], [''], ['second+tag@company.example']
  ], ['EXISTING@company.example']);

  assert.deepEqual([...result.valid], ['new.person@company.example', 'second+tag@company.example']);
  assert.equal(result.duplicates, 2);
  assert.equal(result.invalid, 1);
  assert.equal(result.blank, 1);
  assert.equal(result.rows[2].reason, 'Sudah ada di daftar email');
});

test('invalid extra columns cannot reserve an email before a later valid row', () => {
  const result = preview.validateRows([['email'], ['person@company.example', 'Unexpected name'], ['person@company.example']]);

  assert.equal(result.invalid, 1);
  assert.equal(result.duplicates, 0);
  assert.deepEqual([...result.valid], ['person@company.example']);
});

test('CSV accepts BOM, quoted headers, CRLF and escaped quotes without losing row numbers', () => {
  const rows = preview.parseCsv('\uFEFF"email"\r\n"one@company.example"\r\n"bad""quote@company.example"\r\n\r\n');
  const result = preview.validateRows(rows);

  assert.deepEqual([...result.valid], ['one@company.example']);
  assert.equal(result.rows[1].number, 3);
  assert.equal(result.rows[1].value, 'bad"quote@company.example');
  assert.equal(result.invalid, 1);
  assert.equal(result.blank, 1);
});

test('CSV rejects unterminated quotes and unexpected text after a quoted field', () => {
  assert.throws(() => preview.parseCsv('email\n"unfinished@company.example'), /belum ditutup/);
  assert.throws(() => preview.parseCsv('email\n"person@company.example"oops'), /tanda kutip/);
});

test('header and formula rules reject unsupported worksheet structures', () => {
  assert.throws(() => preview.validateRows([['email', 'name'], ['a@company.example', 'A']]), /satu kolom/);
  assert.throws(() => preview.validateRows([['name'], ['a@company.example']]), /satu kolom/);
  assert.throws(() => preview.validateRows([['email'], ['']]), /Belum ada email/);
  const result = preview.validateRows([{ number: 1, cells: ['email'] }, { number: 8, cells: ['person@company.example'], formula: true }]);
  assert.equal(result.invalid, 1);
  assert.equal(result.rows[0].reason, 'Gunakan nilai email, bukan rumus');
});

test('email validation rejects malformed domains and local parts', () => {
  for (const email of ['no-domain', '@company.example', 'a..b@company.example', '.person@company.example', 'person@-company.example', 'person@company..example', 'person name@company.example', 'person@company', 'a'.repeat(65) + '@company.example']) {
    assert.equal(preview.isValidEmail(email), false, email);
  }
  assert.equal(preview.isValidEmail("o'neil+tag@company.example"), true);
});

test('imports enforce the 10000-row boundary', () => {
  const rows = [['email'], ...Array.from({ length: 10000 }, (_, index) => [`person${index}@company.example`])];
  assert.equal(preview.validateRows(rows).valid.length, 10000);
  rows.push(['overflow@company.example']);
  assert.throws(() => preview.validateRows(rows), /10.000/);
});

test('untrusted values are escaped before appearing in the preview table', () => {
  assert.equal(preview.escapeHtml('<img src=x onerror="alert(1)">&'), '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;&amp;');
});

test('CSV export quotes cells and neutralizes spreadsheet formulas', () => {
  const csv = preview.toCsv(['+tag@company.example', 'plain@company.example', '=formula@company.example']);
  assert.ok(csv.startsWith('\uFEFFemail\r\n'));
  assert.ok(csv.includes('"\'+tag@company.example"'));
  assert.ok(csv.includes('"\'=formula@company.example"'));
  assert.ok(csv.includes('"plain@company.example"'));
});

test('file checks reject unsupported and oversized uploads before reading them', async () => {
  await assert.rejects(preview.readFile({ name: 'recipients.xls', size: 50 }), /\.xlsx atau \.csv/);
  await assert.rejects(preview.readFile({ name: 'recipients.xlsx', size: 5 * 1024 * 1024 + 1 }), /5 MB/);
  await assert.rejects(preview.readFile({ name: 'broken.xlsx', size: 50, arrayBuffer: async () => new ArrayBuffer(0) }, { loadAsync: async () => { throw new Error('Bad zip'); } }), /tanpa kata sandi/);
  const rows = await preview.readFile({ name: 'recipients.CSV', size: 50, text: async () => 'email\none@company.example' });
  assert.equal(rows[1].cells[0], 'one@company.example');
});
