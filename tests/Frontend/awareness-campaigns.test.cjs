const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const sandbox = { module: { exports: {} }, URL, Intl, Blob, setTimeout };
const assetPath = name => path.join(__dirname, '../../public/assets/js/', name);
vm.runInNewContext(fs.readFileSync(assetPath('awareness-preview.js'), 'utf8'), sandbox);
sandbox.AwarenessPreview = sandbox.module.exports;
sandbox.module = { exports: {} };
vm.runInNewContext(fs.readFileSync(assetPath('awareness-campaign-core.js'), 'utf8'), sandbox);
const core = sandbox.module.exports;
const plain = value => JSON.parse(JSON.stringify(value));

test('initial templates pass validation and campaign messages are independent snapshots', () => {
  const state = core.initialState();
  assert.equal(state.campaigns.length, 4);
  for (const template of state.templates) assert.deepEqual(plain(core.validateTemplate(template)), {});
  const original = state.campaigns[0].message.delta.ops[0].insert;

  state.templates[0].delta.ops[0].insert = 'Edited template';

  assert.equal(state.campaigns[0].message.delta.ops[0].insert, original);
});

test('selected recipients are unique and restricted to available sample emails', () => {
  const selected = core.recipientsFor({ recipientMode: 'selected', recipientIds: [1, 1, 480, 481, 0, '2', -1] });

  assert.deepEqual([...selected], [1, 480]);
  assert.equal(core.recipientsFor({ recipientMode: 'all', recipientIds: [] }).length, 480);
});

test('schedules are parsed as WIB and invalid calendar dates are rejected', () => {
  assert.equal(core.parseSchedule('2026-11-06T09:00'), Date.parse('2026-11-06T02:00:00Z'));
  for (const value of ['2026-02-30T09:00', '2026-11-06T24:00', '2026-13-06T09:00', '', '2026-11-06']) {
    assert.ok(Number.isNaN(core.parseSchedule(value)), value);
  }
});

test('campaign scheduling requires a future time while drafts can omit it', () => {
  const campaign = core.initialState().campaigns[0];
  campaign.scheduledAt = '';
  campaign.endsAt = '';

  assert.deepEqual(plain(core.validateCampaign(campaign)), {});
  assert.ok(core.validateCampaign(campaign, { schedule: true, now: Date.parse('2026-11-05T00:00:00Z') }).schedule);
  campaign.scheduledAt = '2026-11-06T09:00';
  campaign.endsAt = '2026-11-09T17:00';
  assert.deepEqual(plain(core.validateCampaign(campaign, { schedule: true, now: Date.parse('2026-11-05T00:00:00Z') })), {});
  assert.ok(core.validateCampaign(campaign, { schedule: true, now: Date.parse('2026-11-06T02:00:00Z') }).schedule);
});

test('evaluation end must be valid and strictly later than the start when supplied', () => {
  const campaign = { scheduledAt: '2026-11-10T09:00', endsAt: '2026-11-13T17:00' };
  assert.deepEqual(plain(core.validatePeriod(campaign, true)), {});
  for (const endsAt of ['2026-11-10T09:00', '2026-11-10T08:59', '2026-02-30T17:00', 'invalid']) assert.ok(core.validatePeriod({ ...campaign, endsAt }).endsAt);
  assert.ok(core.validatePeriod({ ...campaign, endsAt: '' }, true).endsAt);
  assert.ok(core.validatePeriod({ ...campaign, scheduledAt: '' }).schedule);
  assert.deepEqual(plain(core.validatePeriod({ scheduledAt: '', endsAt: '' })), {});
});

test('evaluation dates round trip without rewriting authored historical schedules', () => {
  const state = core.initialState();
  state.campaigns[0].endsAt = '2026-11-20T16:30';
  const saved = core.load({ getItem: () => JSON.stringify(state) });
  assert.equal(saved.campaigns[0].endsAt, '2026-11-20T16:30');
  const legacy = core.initialState();
  delete legacy.campaigns[0].endsAt;
  const custom = { ...core.clone(legacy.campaigns[0]), id: 'authored-simulation', name: 'Simulasi saya' };
  legacy.campaigns.push(custom);
  const upgraded = core.load({ getItem: () => JSON.stringify(legacy) });
  assert.equal(upgraded.campaigns[0].endsAt, '2026-11-08T17:00');
  assert.equal(upgraded.campaigns.find(item => item.id === custom.id).endsAt, '');
});

test('campaign validation reports sender, name, recipient and message failures', () => {
  const errors = core.validateCampaign({ name: '', senderName: '', senderEmail: 'bad@', recipientMode: 'selected', recipientIds: [], message: { subject: '', delta: { ops: [] } } });

  assert.deepEqual(Object.keys(errors).sort(), ['body', 'name', 'recipients', 'senderEmail', 'senderName', 'subject']);
});

test('templates require supported categories, a subject and a simulation link', () => {
  const errors = core.validateTemplate({ name: '', category: 'Unknown', subject: 'x'.repeat(181), delta: { ops: [{ insert: 'Message without a link\n' }] } });

  assert.deepEqual(Object.keys(errors).sort(), ['body', 'category', 'name', 'subject']);
});

test('duplicating a campaign creates an independent draft with cleared delivery state', () => {
  const original = core.initialState().campaigns[0];
  const copy = core.duplicateCampaign(original, 'campaign-copy', '2026-11-05T12:00:00Z');

  assert.equal(copy.status, 'draft');
  assert.equal(copy.sent, 0);
  assert.equal(copy.clicked, 0);
  assert.equal(copy.scheduledAt, '');
  assert.equal(copy.endsAt, '');
  assert.equal(copy.id, 'campaign-copy');
  copy.message.delta.ops[0].insert = 'Changed';
  assert.notEqual(original.message.delta.ops[0].insert, 'Changed');
});

test('rich email preview preserves formatting and resolves tokens split across formatting runs', () => {
  const body = core.renderBody({ ops: [
    { insert: 'Halo ' }, { insert: '{{email}}', attributes: { bold: true } }, { insert: '\n' },
    { insert: '{{track', attributes: { bold: true } }, { insert: 'ing_link}}', attributes: { italic: true } }, { insert: '\n' },
    { insert: '{{campaign_name}}\n' }
  ] }, { email: 'person@company.example', campaignName: 'November' });

  assert.ok(body.includes('person@company.example'));
  assert.ok(body.includes('November'));
  assert.equal((body.match(/class="email-cta"/g) || []).length, 1);
  assert.ok(!body.includes('{{tracking_link}}'));
});

test('email previews escape text and reject embedded resources and executable links', () => {
  const html = core.emailDocument({ ops: [
    { insert: '<script>alert(1)</script>\n' },
    { insert: { image: 'https://external.example/image.png' } },
    { insert: 'Click', attributes: { link: 'javascript:alert(1)', header: 'img' } },
    { insert: '\n{{email}}\n' }
  ] }, { email: '"><img src=x onerror=alert(1)>' }, '"><script>alert(2)</script>');

  assert.ok(html.includes('&lt;script&gt;'));
  assert.ok(!html.includes('<script>'));
  assert.ok(!html.includes('<img'));
  assert.ok(!html.includes('javascript:'));
  assert.ok(html.includes('default-src &#39;none&#39;'));
});

test('list formatting closes before ordinary paragraphs', () => {
  const html = core.renderBody({ ops: [{ insert: 'First' }, { insert: '\n', attributes: { list: 'bullet' } }, { insert: 'Second' }, { insert: '\n', attributes: { list: 'bullet' } }, { insert: 'After\n' }] });

  assert.equal(html, '<ul><li>First</li><li>Second</li></ul><p>After</p>');
});

test('session preview state round-trips and gracefully handles unavailable storage', () => {
  const values = new Map();
  const storage = { getItem: key => values.get(key) || null, setItem: (key, value) => values.set(key, value) };
  const state = core.initialState();
  state.templates[0].name = 'Customized';

  assert.equal(core.save(storage, state), true);
  assert.equal(core.load(storage).templates[0].name, 'Customized');
  assert.equal(core.save({ setItem: () => { throw new Error('Quota exceeded'); } }, state), false);
  assert.equal(core.load({ getItem: () => { throw new Error('Blocked'); } }).campaigns[0].id, 'november');
});

test('corrupt or incompatible stored data falls back to sample state', () => {
  for (const value of ['broken JSON', '{"version":2}', '{"version":1,"templates":[],"campaigns":[{}]}']) {
    assert.equal(core.load({ getItem: () => value }).templates.length, 3);
  }
  const invalidStatus = core.initialState();
  invalidStatus.campaigns[0].status = '__proto__';
  assert.equal(core.load({ getItem: () => JSON.stringify(invalidStatus) }).campaigns[0].status, 'running');
});

test('phishing samples use company benefit lures without exposing the simulation in the email body', () => {
  const state = core.initialState();

  assert.equal(state.templates[0].subject, 'Informasi Kenaikan Gaji PT AST INDONESIA Tahun 2027');
  assert.equal(state.templates[0].category, 'HR & Payroll');
  for (const template of state.templates) {
    const body = core.messageText(template.delta);
    assert.ok(body.includes('PT AST INDONESIA'));
    assert.ok(body.includes('{{tracking_link}}'));
    assert.ok(!/awareness|simulasi/i.test(body));
  }
  assert.equal(state.campaigns[0].senderName, 'HRD PT AST INDONESIA');
  assert.equal(state.campaigns[0].senderEmail, 'hrd@ast-indonesia.example');
  assert.equal(state.campaigns[0].message.subject, state.templates[0].subject);
});

function legacyTemplate() {
  return {
    id: 'layanan', name: 'Pembaruan layanan internal', category: 'Internal', subject: 'Informasi pembaruan layanan internal', updatedAt: '2026-11-04T08:00:00+07:00',
    delta: { ops: [{ insert: 'Informasi layanan perusahaan' }, { insert: '\n', attributes: { header: 2 } }, { insert: 'Halo {{email}},\n\nInformasi pembaruan layanan internal telah tersedia. Silakan tinjau informasi melalui tautan berikut.\n\n{{tracking_link}}\n\nTerima kasih,\nTim IT\n' }] }
  };
}

test('untouched cached samples refresh without removing user-created templates or campaigns', () => {
  const state = core.initialState();
  const legacy = legacyTemplate();
  state.templates[0] = legacy;
  state.campaigns[0].name = 'Awareness November 2026';
  state.campaigns[0].senderName = 'Tim IT Awareness';
  state.campaigns[0].senderEmail = 'awareness@perusahaan.example';
  state.campaigns[0].message = { subject: legacy.subject, delta: core.clone(legacy.delta) };
  const custom = { ...core.clone(legacy), id: 'my-template', name: 'Template saya' };
  state.templates.push(custom);
  const customDraft = core.duplicateCampaign(state.campaigns[0], 'my-draft', '2026-11-05T12:00:00Z');
  customDraft.name = 'Draf buatan saya';
  state.campaigns.push(customDraft);

  const refreshed = core.load({ getItem: () => JSON.stringify(state) });

  assert.equal(refreshed.templates[0].subject, 'Informasi Kenaikan Gaji PT AST INDONESIA Tahun 2027');
  assert.equal(refreshed.campaigns[0].senderName, 'HRD PT AST INDONESIA');
  assert.equal(refreshed.campaigns[0].message.subject, refreshed.templates[0].subject);
  assert.deepEqual(plain(refreshed.templates.find(item => item.id === custom.id)), { ...plain(custom), category: 'HR & Payroll' });
  assert.deepEqual(plain(refreshed.campaigns.find(item => item.id === customDraft.id)), plain(customDraft));
});

test('sample refresh preserves authored formatting and does not restore deleted templates', () => {
  const state = core.initialState();
  const legacy = legacyTemplate();
  legacy.delta.ops[0].attributes = { bold: true };
  state.templates = [legacy];
  state.campaigns[0].name = 'Awareness November 2026';
  state.campaigns[0].message = { subject: legacy.subject, delta: core.clone(legacy.delta) };

  const refreshed = core.load({ getItem: () => JSON.stringify(state) });

  assert.equal(refreshed.templates.length, 1);
  assert.equal(refreshed.templates[0].name, 'Pembaruan layanan internal');
  assert.equal(refreshed.templates[0].delta.ops[0].attributes.bold, true);
  assert.equal(refreshed.campaigns[0].message.delta.ops[0].attributes.bold, true);
  assert.equal(refreshed.campaigns[0].name, 'Awareness November 2026');
});

test('legacy categories retire while authored content and custom categories survive', () => {
  const state = core.initialState();
  delete state.categoryRevision;
  delete state.categories;
  const oldCategories = ['Internal', 'Edukasi', 'Kebijakan'];
  state.templates.forEach((item, index) => { item.category = oldCategories[index]; item.name = 'Authored ' + index; });
  state.templates.push({ ...core.clone(state.templates[0]), id: 'custom-category', category: 'Operasional' });
  const bodies = plain(state.templates.map(item => item.delta));

  const loaded = core.load({ getItem: () => JSON.stringify(state) });

  assert.deepEqual(plain(loaded.categories), ['HR & Payroll', 'Administrasi', 'Benefit Karyawan', 'Operasional']);
  assert.deepEqual(plain(loaded.templates.map(item => item.category)), plain(loaded.categories));
  assert.deepEqual(plain(loaded.templates.map(item => item.delta)), bodies);
  assert.equal(loaded.templates[0].name, 'Authored 0');
});

test('new categories normalize whitespace and reject duplicate or invalid names without mutation', () => {
  const state = core.initialState();

  assert.equal(core.addCategory(state, '  Operasional   Kantor  '), 'Operasional Kantor');
  const before = JSON.stringify(state);
  for (const value of ['operasional kantor', ' HR & Payroll ', '', '   ', 'x'.repeat(61)]) {
    assert.throws(() => core.addCategory(state, value));
    assert.equal(JSON.stringify(state), before);
  }
});

test('renaming a category updates templates and preserves campaign messages and the category after reload', () => {
  const state = core.initialState();
  const campaigns = plain(state.campaigns);
  const message = plain(state.templates[0].delta);

  core.renameCategory(state, 'HR & Payroll', 'Kompensasi');

  assert.equal(state.templates[0].category, 'Kompensasi');
  assert.deepEqual(plain(state.templates[0].delta), message);
  assert.deepEqual(plain(state.campaigns), campaigns);
  const loaded = core.load({ getItem: () => JSON.stringify(state) });
  assert.ok(!loaded.categories.includes('HR & Payroll'));
  assert.equal(loaded.templates[0].category, 'Kompensasi');
  core.renameCategory(state, 'Kompensasi', 'KOMPENSASI');
  assert.equal(state.templates[0].category, 'KOMPENSASI');
  const before = JSON.stringify(state);
  assert.throws(() => core.renameCategory(state, 'KOMPENSASI', 'administrasi'));
  assert.throws(() => core.renameCategory(state, 'Missing', 'New'));
  assert.equal(JSON.stringify(state), before);
});

test('deleting a used category requires a valid replacement and preserves all templates', () => {
  const state = core.initialState();
  const before = JSON.stringify(state);
  for (const replacement of ['', 'Missing', 'HR & Payroll']) {
    assert.throws(() => core.removeCategory(state, 'HR & Payroll', replacement));
    assert.equal(JSON.stringify(state), before);
  }

  assert.equal(core.removeCategory(state, 'HR & Payroll', 'Administrasi'), 1);

  assert.equal(state.templates.length, 3);
  assert.equal(state.templates[0].category, 'Administrasi');
  assert.equal(state.categories.includes('HR & Payroll'), false);
  const loaded = core.load({ getItem: () => JSON.stringify(state) });
  assert.equal(loaded.categories.includes('HR & Payroll'), false);
  assert.equal(loaded.templates[0].category, 'Administrasi');
  assert.throws(() => core.removeCategory(state, 'Missing'));
});

test('unused categories can be deleted and an empty category list stays empty after reload', () => {
  const state = core.initialState();
  state.templates = [];
  for (const name of [...state.categories]) assert.equal(core.removeCategory(state, name), 0);

  const loaded = core.load({ getItem: () => JSON.stringify(state) });

  assert.deepEqual(plain(loaded.categories), []);
  assert.deepEqual(plain(loaded.templates), []);
  core.addCategory(loaded, 'Kategori pertama');
  assert.deepEqual(plain(loaded.categories), ['Kategori pertama']);
});

test('template validation follows the current category catalog including newly added names', () => {
  const state = core.initialState();
  core.addCategory(state, 'Operasional');
  const template = { ...state.templates[0], category: 'Operasional' };

  assert.deepEqual(plain(core.validateTemplate(template, state.categories)), {});
  core.removeCategory(state, 'Operasional');
  assert.ok(core.validateTemplate(template, state.categories).category);
});


test('database recipients use actual ids and an empty registry cannot fall back to samples', () => {
  const registry = [{ id: 902, email: 'real@example.com' }, { id: 1200, email: 'other@example.com' }];
  assert.deepEqual([...core.recipientsFor({ recipientMode: 'all' }, registry)], [902, 1200]);
  assert.deepEqual([...core.recipientsFor({ recipientMode: 'selected', recipientIds: [1, 902, 902, 999] }, registry)], [902]);
  assert.deepEqual([...core.recipientsFor({ recipientMode: 'all', recipientRegistry: [] })], []);
  assert.ok(core.validateCampaign({ ...core.initialState().campaigns[0], recipientMode: 'all' }, { recipients: [] }).recipients);
});
