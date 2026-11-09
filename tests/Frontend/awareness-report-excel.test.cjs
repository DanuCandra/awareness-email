const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const sandbox = { module: { exports: {} }, URL, Intl, Blob, setTimeout };
function load(name, globalName) {
  sandbox.module = { exports: {} };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/', name), 'utf8'), sandbox);
  sandbox[globalName] = sandbox.module.exports;
  return sandbox.module.exports;
}
load('awareness-preview.js', 'AwarenessPreview');
const campaigns = load('awareness-campaign-core.js', 'AwarenessCampaigns');
const reports = load('awareness-report-core.js', 'AwarenessReports');

function installedZip() {
  const bundle = fs.readFileSync(path.join(__dirname, '../../public/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'), 'utf8');
  const start = bundle.indexOf('"./node_modules/jszip/dist/jszip.min.js":');
  assert.notEqual(start, -1, 'Report table bundle must include JSZip.');
  const sourceStart = bundle.indexOf('eval(', start) + 5, quote = bundle[sourceStart];
  let end = sourceStart + 1;
  for (; end < bundle.length; end++) {
    if (bundle[end] === '\\') end++;
    else if (bundle[end] === quote) break;
  }
  const source = vm.runInNewContext(bundle.slice(sourceStart, end + 1));
  const module = { exports: {} };
  vm.runInNewContext(source, { module, exports: module.exports, __webpack_require__: require, setImmediate, clearImmediate, setTimeout, clearTimeout, Buffer, Uint8Array, ArrayBuffer, Blob });
  return module.exports;
}
const Zip = installedZip();
async function workbook(sheet) {
  const blob = await reports.createWorkbook(Zip, sheet);
  assert.equal(blob.type, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  const bytes = Buffer.from(await blob.arrayBuffer());
  assert.equal(bytes.readUInt16LE(0), 0x4b50);
  const zip = await Zip.loadAsync(bytes, { checkCRC32: true });
  return { zip, xml: await zip.file('xl/worksheets/sheet1.xml').async('string') };
}

test('summary export creates a real XLSX with numeric metrics, styled headers, filter and frozen row', async () => {
  const simulation = { ...campaigns.initialState().campaigns[0], dataSource: 'database' };
  const { zip, xml } = await workbook(reports.summarySheet([simulation]));
  assert.ok(zip.file('[Content_Types].xml'));
  assert.ok(zip.file('_rels/.rels'));
  assert.match(await zip.file('xl/workbook.xml').async('string'), /name="Ringkasan Simulasi"/);
  assert.match(await zip.file('xl/_rels/workbook.xml.rels').async('string'), /styles\.xml/);
  assert.match(await zip.file('xl/styles.xml').async('string'), /FF7367F0/);
  assert.match(xml, /<c r="E2" s="2"><v>480<\/v><\/c>/);
  assert.match(xml, /<c r="I2" s="2"><v>8.76<\/v><\/c>/);
  assert.match(xml, /state="frozen"/);
  assert.match(xml, /<autoFilter ref="A1:N2"/);
  assert.match(xml, /Aktivitas tersimpan/);
  assert.doesNotMatch(xml, /Data contoh frontend/);
});

test('activity workbook includes every supplied filtered row across pages and keeps delivery states and WIB', async () => {
  const simulation = campaigns.initialState().campaigns[0];
  const rows = reports.recipientRows(simulation).slice(0, 25).map(row => ({ ...row, dataSource: 'database' }));
  rows.push({ ...reports.recipientRows(simulation)[41], dataSource: 'database' });
  const { xml } = await workbook(reports.activitySheet(rows));
  assert.equal((xml.match(/<row r=/g) || []).length, 27);
  assert.match(xml, /karyawan025@perusahaan.example/);
  assert.match(xml, /karyawan042@perusahaan.example/);
  assert.doesNotMatch(xml, /karyawan043@perusahaan.example/);
  assert.match(xml, /Belum diakses/);
  assert.match(xml, /09.03 WIB/);
  assert.match(xml, /<autoFilter ref="A1:R27"/);
});

test('XLSX treats formula-like fields and IPs as text, escapes XML, and preserves Unicode', async () => {
  const { xml } = await workbook({ name: 'Data', headers: ['Nama', 'IP', 'Jumlah'], rows: [
    ['=HYPERLINK("https://example.com")', '001.002.003.004', 7],
    ['PT AST & <Indonesia> — 日本語 😀\u0001', '  @malicious', 0],
    ['+SUM(1,2)', '10.0.0.1', 12.5]
  ] });
  assert.match(xml, /r="A2" s="2" t="inlineStr"/);
  assert.match(xml, /=HYPERLINK\(&quot;https:\/\/example.com&quot;\)/);
  assert.match(xml, /001\.002\.003\.004/);
  assert.match(xml, /&amp; &lt;Indonesia&gt; — 日本語 😀/);
  assert.match(xml, /<c r="C4" s="2"><v>12.5<\/v><\/c>/);
  assert.doesNotMatch(xml, /<f[ >]|\u0001|\uFFFD/);
});

test('missing Excel runtime or oversized worksheets fail before download', async () => {
  await assert.rejects(reports.createWorkbook(undefined, { headers: ['Email'], rows: [] }), /Komponen Excel belum tersedia/);
  await assert.rejects(reports.createWorkbook(Zip, { headers: ['Email'], rows: { length: 1048576 } }), /melebihi batas/);
});

test('database client details reach the browser, operating system and device Excel columns', async () => {
  const row = { ...reports.recipientRows(campaigns.initialState().campaigns[0])[0], dataSource: 'database', browser: 'Edge', os: 'Windows', device: 'Desktop' };
  const { xml } = await workbook(reports.activitySheet([row]));
  assert.match(xml, /<c r="H2"[^>]*><is><t xml:space="preserve">Edge<\/t>/);
  assert.match(xml, /<c r="I2"[^>]*><is><t xml:space="preserve">Windows<\/t>/);
  assert.match(xml, /<c r="J2"[^>]*><is><t xml:space="preserve">Desktop<\/t>/);
});
