const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const sandbox = { module: { exports: {} }, URL, Intl, Blob, setTimeout };
function loadModule(file, name) {
  sandbox.module = { exports: {} };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/', file), 'utf8'), sandbox);
  sandbox[name] = sandbox.module.exports;
  return sandbox.module.exports;
}
loadModule('awareness-preview.js', 'AwarenessPreview');
const campaigns = loadModule('awareness-campaign-core.js', 'AwarenessCampaigns');
const settings = loadModule('awareness-settings-core.js', 'AwarenessSettings');

test('SMTP send interval accepts zero or whole seconds and survives reload', () => {
  const target = storage(), smtp = settings.defaults().smtp;
  for (const interval of ['5', '0', '60']) {
    const result = settings.saveSection(target, 'smtp', { ...smtp, sendIntervalSeconds: interval }, '2026-11-06T03:00:00Z');
    assert.equal(result.saved, true);
    assert.equal(settings.load(target).smtp.sendIntervalSeconds, Number(interval));
  }
  const before = target.getItem(settings.storageKey);
  for (const interval of ['', '-1', '1.5', '1e2', 'abc', Infinity, 9007199254740992]) {
    const result = settings.saveSection(target, 'smtp', { ...smtp, sendIntervalSeconds: interval }, '2026-11-06T03:00:00Z');
    assert.equal(result.saved, false);
    assert.ok(result.errors.sendIntervalSeconds);
    assert.equal(target.getItem(settings.storageKey), before);
  }
});

test('older SMTP settings receive a zero interval without losing their sender settings', () => {
  const target = storage(), value = settings.defaults();
  value.smtp.fromName = 'Pengirim yang sudah diatur';
  delete value.smtp.sendIntervalSeconds;
  target.setItem(settings.storageKey, JSON.stringify(value));
  const loaded = settings.load(target);
  assert.equal(loaded.smtp.fromName, 'Pengirim yang sudah diatur');
  assert.equal(loaded.smtp.sendIntervalSeconds, 0);
});
const plain = value => JSON.parse(JSON.stringify(value));
function storage() {
  const values = new Map();
  return { getItem: key => values.get(key) || null, setItem: (key, value) => values.set(key, value) };
}

test('default settings validate and use the same sample sender as campaign templates', () => {
  const value = settings.defaults();

  for (const section of ['identity', 'smtp', 'landing', 'reports', 'audit']) assert.deepEqual(plain(settings.validate(section, value[section])), {});
  assert.equal(value.smtp.fromEmail, campaigns.defaultSender.email);
  assert.equal(value.smtp.fromName, campaigns.defaultSender.name);
});

test('SMTP saves normalize From while keeping a different login username and preserving other sections', () => {
  const target = storage(), initial = settings.defaults();
  settings.saveSection(target, 'identity', { ...initial.identity, companyName: 'Nama baru' }, '2026-11-06T01:00:00Z');

  const saved = settings.saveSection(target, 'smtp', { ...initial.smtp, fromEmail: ' Payroll@Ast-Indonesia.Example ', username: 'smtp-account-01', fromName: 'Payroll', replyTo: 'IT@Ast-Indonesia.Example' }, '2026-11-06T02:00:00Z');

  assert.equal(saved.saved, true);
  const reloaded = settings.load(target);
  assert.equal(reloaded.identity.companyName, 'Nama baru');
  assert.equal(reloaded.smtp.fromEmail, 'payroll@ast-indonesia.example');
  assert.equal(reloaded.smtp.username, 'smtp-account-01');
  assert.equal(reloaded.smtp.replyTo, 'it@ast-indonesia.example');
  assert.equal(reloaded.updatedAt, '2026-11-06T02:00:00Z');
});

test('passwords and arbitrary extra properties never enter browser storage', () => {
  const target = storage(), smtp = settings.defaults().smtp;

  settings.saveSection(target, 'smtp', { ...smtp, password: 'test-only-secret', token: 'test-only-token' }, '2026-11-06T02:00:00Z');

  const raw = target.getItem(settings.storageKey);
  assert.ok(!raw.includes('test-only-secret'));
  assert.ok(!raw.includes('test-only-token'));
  assert.ok(!raw.includes('password'));
});

test('invalid settings do not overwrite saved configuration', () => {
  const target = storage(), initial = settings.defaults();
  settings.saveSection(target, 'smtp', initial.smtp, '2026-11-06T02:00:00Z');
  const before = target.getItem(settings.storageKey);

  for (const override of [{ host: 'https://mail.example' }, { port: '0' }, { port: '65536' }, { port: '2e3' }, { encryption: 'invalid' }, { username: '' }, { fromEmail: 'bad@' }, { fromName: '' }, { replyTo: 'invalid' }]) {
    const result = settings.saveSection(target, 'smtp', { ...initial.smtp, ...override }, '2026-11-06T03:00:00Z');
    assert.equal(result.saved, false);
    assert.ok(Object.keys(result.errors).length > 0);
    assert.equal(target.getItem(settings.storageKey), before);
  }
});

test('new campaigns and edited drafts use current SMTP From while retaining a custom campaign name', () => {
  const value = settings.defaults();
  value.smtp.fromName = 'Pengirim baru'; value.smtp.fromEmail = 'baru@ast-indonesia.example';
  const draft = campaigns.initialState().campaigns.find(item => item.status === 'draft');
  const before = plain(draft);

  assert.deepEqual(plain(settings.campaignSender(value)), { senderName: 'Pengirim baru', senderEmail: 'baru@ast-indonesia.example', replyTo: '' });
  assert.equal(settings.campaignSender(value, { ...draft, senderName: 'Nama khusus HR' }).senderName, 'Nama khusus HR');
  assert.equal(settings.campaignSender(value, draft).senderEmail, 'baru@ast-indonesia.example');
  assert.deepEqual(plain(draft), before);
});

test('changing SMTP configuration never rewrites historical campaign snapshots', () => {
  const target = storage(), state = campaigns.initialState();
  campaigns.save(target, state);
  const before = target.getItem(campaigns.storageKey);

  settings.saveSection(target, 'smtp', { ...settings.defaults().smtp, fromEmail: 'baru@ast-indonesia.example' }, '2026-11-06T03:00:00Z');

  assert.equal(target.getItem(campaigns.storageKey), before);
  assert.equal(campaigns.load(target).campaigns[0].senderEmail, campaigns.defaultSender.email);
});

test('landing configuration rejects executable links, credentials, and invalid education content', () => {
  const initial = settings.defaults().landing;

  for (const baseUrl of ['javascript:alert(1)', 'ftp://mail.example', 'https://user:pass@site.example', 'https://site.example/?secret=1', 'https://site.example/#hash']) assert.ok(settings.validate('landing', { ...initial, baseUrl }).baseUrl);
  assert.ok(settings.validate('landing', { ...initial, tips: '' }).tips);
  assert.deepEqual(plain(settings.validate('landing', { ...initial, type: 'confirmation', tips: '' })), {});
  const target = storage();
  settings.saveSection(target, 'landing', { ...initial, baseUrl: 'https://awareness.example/subdir/' }, '2026-11-06T03:00:00Z');
  assert.equal(settings.load(target).landing.baseUrl, 'https://awareness.example/subdir');
});

test('identity validation rejects active or oversized logo formats', () => {
  const value = settings.defaults().identity;

  for (const logo of ['data:image/svg+xml;base64,PHN2Zz4=', 'https://external.example/logo.png', 'data:image/png;base64,' + 'a'.repeat(700001)]) assert.ok(settings.validate('identity', { ...value, logo }).logo);
  assert.deepEqual(plain(settings.validate('identity', { ...value, logo: 'data:image/png;base64,aGVsbG8=' })), {});
});

test('report preferences produce inclusive WIB calendar date ranges and valid table sizes', () => {
  const value = settings.defaults();
  value.reports.period = '30'; value.reports.pageLength = 25;

  assert.deepEqual(plain(settings.reportDefaults(value, '2026-11-06')), { from: '2026-10-08', to: '2026-11-06', pageLength: 25 });
  value.reports.period = 'all';
  assert.deepEqual(plain(settings.reportDefaults(value, '2026-11-06')), { from: '', to: '', pageLength: 25 });
  assert.ok(settings.validate('reports', { pageLength: 999, period: 'all' }).pageLength);
  assert.ok(settings.validate('reports', { pageLength: 10, period: 'invalid' }).period);
  assert.ok(settings.validate('audit', { retentionMonths: 0 }).retentionMonths);
});

test('blocked storage and corrupted settings fail safely without masking save failures', () => {
  for (const raw of ['broken', '{"version":2}', JSON.stringify({ version: 1, smtp: { fromEmail: 'bad@' } })]) assert.equal(settings.load({ getItem: () => raw }).smtp.fromEmail, campaigns.defaultSender.email);
  const blocked = { getItem: () => { throw new Error('Blocked'); }, setItem: () => { throw new Error('Blocked'); } };

  const result = settings.saveSection(blocked, 'audit', { retentionMonths: 12 }, '2026-11-06T03:00:00Z');

  assert.equal(result.saved, false);
  assert.equal(result.storageError, true);
  assert.equal(settings.saveSection(storage(), 'unknown', {}, '').saved, false);
});
