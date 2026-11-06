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
const preview = load('awareness-preview.js', 'AwarenessPreview');
const campaigns = load('awareness-campaign-core.js', 'AwarenessCampaigns');
const reports = load('awareness-report-core.js', 'AwarenessReports');
const plain = value => JSON.parse(JSON.stringify(value));

test('evaluation boundaries are inclusive and missing or reversed periods remain unclassified', () => {
  const campaign = { scheduledAt: '2026-11-10T09:00', endsAt: '2026-11-10T10:00' };
  assert.equal(reports.eventPeriod(campaign, '2026-11-10T02:00:00Z'), 'within');
  assert.equal(reports.eventPeriod(campaign, '2026-11-10T03:00:00Z'), 'within');
  assert.equal(reports.eventPeriod(campaign, '2026-11-10T01:59:59Z'), 'before');
  assert.equal(reports.eventPeriod(campaign, '2026-11-10T03:00:01Z'), 'after');
  assert.equal(reports.eventPeriod({ ...campaign, endsAt: '' }, '2026-11-10T02:30:00Z'), 'unknown');
  assert.equal(reports.eventPeriod({ ...campaign, endsAt: '2026-11-09T10:00' }, '2026-11-10T02:30:00Z'), 'unknown');
});

test('repeat clicks after the deadline stay in the audit but do not increase evaluation totals', () => {
  const campaign = { ...campaigns.initialState().campaigns[0], scheduledAt: '2026-11-10T09:00', endsAt: '2026-11-10T09:25', recipientMode: 'selected', recipientIds: [1, 2, 3], sent: 3, clicked: 3, hourly: [3] };
  const rows = reports.recipientRows(campaign);
  const summary = reports.summarize([campaign], rows);
  assert.equal(summary.clicked, 2);
  assert.equal(summary.clicks, 2);
  assert.equal(summary.afterClicks, 4);
  assert.equal(summary.unclicked, 1);
  assert.equal(summary.rate, 2 / 3 * 100);
  assert.equal(rows[1].withinClicks, 1);
  assert.equal(rows[1].afterClicks, 1);
  assert.equal(rows[2].events.length, 3);
  assert.ok(reports.activityExport(rows).includes('Akses setelah batas'));
  assert.ok(reports.summaryExport([campaign]).includes('10 Nov 2026, 09.25 WIB'));
});

test('simulations without a deadline do not dilute the evaluation rate', () => {
  const complete = campaigns.initialState().campaigns[0];
  const missing = { ...complete, id: 'missing-deadline', endsAt: '' };
  const rows = [complete, missing].flatMap(reports.recipientRows);
  const summary = reports.summarize([complete, missing], rows);
  assert.equal(summary.sent, 936);
  assert.equal(summary.evaluationSent, 468);
  assert.equal(summary.clicked, 41);
  assert.equal(summary.rate, 41 / 468 * 100);
  assert.equal(summary.missingPeriods, 1);
  assert.equal(summary.unclassifiedClicks, 81);
});

test('report totals reconcile with campaign delivery counts and separate unique people from repeat clicks', () => {
  const selected = reports.filterCampaigns(campaigns.initialState().campaigns);
  const rows = selected.flatMap(reports.recipientRows);

  const summary = reports.summarize(selected, rows);

  assert.deepEqual(plain(summary), { campaigns: 3, target: 1050, sent: 1025, clicked: 136, failed: 25, unclicked: 889, rate: 136 / 1025 * 100, clicks: 269, uniqueEmails: 49, evaluationSent: 1025, afterClicks: 0, beforeClicks: 0, unclassifiedClicks: 0, missingPeriods: 0 });
  for (const campaign of selected) {
    const value = reports.summarize([campaign], reports.recipientRows(campaign));
    assert.equal(value.target, campaign.target);
    assert.equal(value.sent, campaign.sent);
    assert.equal(value.clicked, campaign.clicked);
  }
});

test('recipient histories preserve first and last times and repeat clicks do not inflate unique counts', () => {
  const campaign = campaigns.initialState().campaigns[0];

  const rows = reports.recipientRows(campaign);

  assert.equal(rows[0].email, 'karyawan001@perusahaan.example');
  assert.equal(rows[0].ip, '10.10.20.31');
  assert.equal(rows[2].clicks, 3);
  assert.equal(rows[2].firstClick, rows[2].events[0].at);
  assert.equal(rows[2].lastClick, rows[2].events[2].at);
  assert.ok(Date.parse(rows[2].lastClick) > Date.parse(rows[2].firstClick));
  assert.equal(rows[41].status, 'not_clicked');
  assert.equal(rows[468].status, 'failed');
  for (const row of rows.filter(item => !item.clicked)) {
    assert.equal(row.ip, '');
    assert.equal(row.firstClick, '');
    assert.equal(row.browser, '');
    assert.equal(row.clicks, 0);
    assert.deepEqual(plain(row.events), []);
  }
});

test('first click distribution matches the dashboard by hour in WIB', () => {
  const campaign = campaigns.initialState().campaigns[0];
  const clicked = reports.recipientRows(campaign).filter(item => item.clicked);

  const counts = Array.from({ length: 6 }, (_, index) => clicked.filter(item => new Date(Date.parse(item.firstClick) + 7 * 3600000).getUTCHours() === index + 9).length);

  assert.deepEqual(counts, [12, 9, 8, 5, 4, 3]);
  assert.ok(clicked.every(item => Date.parse(item.firstClick) > campaigns.parseSchedule(campaign.scheduledAt)));
});

test('draft and scheduled campaigns produce no invented delivery or click evidence', () => {
  for (const status of ['draft', 'scheduled']) {
    const campaign = { ...campaigns.initialState().campaigns[0], status };

    const rows = reports.recipientRows(campaign);

    assert.equal(rows.length, 480);
    assert.ok(rows.every(row => row.status === 'pending' && !row.ip && !row.clicked && row.clicks === 0));
    assert.equal(reports.filterCampaigns([campaign]).length, 0);
  }
});

test('selected recipient ids are respected and delivery counts cannot exceed the selected cohort', () => {
  const campaign = { ...campaigns.initialState().campaigns[0], recipientMode: 'selected', recipientIds: [2, 200, 480, 2, 481], sent: 20, clicked: 10, hourly: [] };

  const rows = reports.recipientRows(campaign);

  assert.deepEqual(plain(rows.map(row => row.recipientId)), [2, 200, 480]);
  assert.ok(rows.every(row => row.clicked && row.events.length > 0));
  assert.equal(rows[2].email, 'karyawan480@perusahaan.example');
  assert.equal(rows[2].ip, '10.10.22.110');
});

test('an all-failed campaign and an empty report have finite zero rates', () => {
  const campaign = { ...campaigns.initialState().campaigns[0], sent: 0, clicked: 100 };

  const value = reports.summarize([campaign], reports.recipientRows(campaign));

  assert.equal(value.sent, 0);
  assert.equal(value.clicked, 0);
  assert.equal(value.failed, 480);
  assert.equal(value.rate, 0);
  assert.equal(reports.summarize([], []).rate, 0);
  assert.ok(reports.summaryExport([campaign]).includes('"0","480","0","","0","0","0","0","Data contoh frontend"'));
});

test('campaign and date filters are inclusive and keep draft or unavailable campaigns out of results', () => {
  const items = campaigns.initialState().campaigns;
  const original = plain(items);

  assert.deepEqual(plain(reports.filterCampaigns(items, { from: '2026-10-08', to: '2026-11-05' }).map(item => item.id)), ['november', 'october']);
  assert.deepEqual(plain(reports.filterCampaigns(items, { campaign: 'october', from: '2026-10-08', to: '2026-10-08' }).map(item => item.id)), ['october']);
  assert.equal(reports.filterCampaigns(items, { campaign: 'missing' }).length, 0);
  assert.equal(reports.filterCampaigns(items, { campaign: 'draft-policy' }).length, 0);
  assert.equal(reports.filterCampaigns(items, { from: '2027-01-01' }).length, 0);
  assert.deepEqual(plain(items), original);
});

test('report filters reject impossible dates and reversed periods', () => {
  for (const filters of [{ from: '2026-02-29' }, { to: '2026-13-01' }, { from: '5/11/2026' }, { from: '2026-11-06', to: '2026-11-05' }]) assert.ok(reports.validateFilters(filters));
  for (const filters of [{}, { from: '', to: '' }, { from: '2028-02-29' }, { from: '2026-11-05', to: '2026-11-05' }]) assert.equal(reports.validateFilters(filters), '');
});

test('CSV export preserves Unicode and quoted fields while neutralizing spreadsheet formulas', () => {
  const csv = reports.toCsv(['Simulasi', 'Keterangan'], [['=SUM(1,2)', 'PT AST, "Indonesia"'], ['  @malicious', 'Baris 1\nBaris 2'], ['\tformula', 'Gaji naik — 2027']]);

  assert.ok(csv.startsWith('\uFEFF'));
  assert.ok(csv.includes('"\'=SUM(1,2)"'));
  assert.ok(csv.includes('"\'  @malicious"'));
  assert.ok(csv.includes('"\'\tformula"'));
  assert.ok(csv.includes('"PT AST, ""Indonesia"""'));
  assert.ok(csv.includes('Gaji naik — 2027'));
});

test('activity export includes only the supplied filtered rows and labels sample data and WIB', () => {
  const rows = reports.recipientRows(campaigns.initialState().campaigns[0]);

  const csv = reports.activityExport([rows[0], rows[41]]);

  assert.equal(csv.split('\r\n').length, 3);
  assert.ok(csv.includes('karyawan001@perusahaan.example'));
  assert.ok(csv.includes('karyawan042@perusahaan.example'));
  assert.ok(!csv.includes('karyawan002@perusahaan.example'));
  assert.ok(csv.includes('09.03 WIB'));
  assert.ok(csv.includes('Data contoh frontend'));
  assert.ok(csv.includes('Belum diakses'));
});

test('summary export reconciles with report totals and does not mutate stored campaigns', () => {
  const items = reports.filterCampaigns(campaigns.initialState().campaigns, { campaign: 'november' });
  const before = plain(items);

  const csv = reports.summaryExport(items);

  assert.ok(csv.includes('"480","468","12","41","8.76"'));
  assert.equal(csv.split('\r\n').length, 2);
  assert.deepEqual(plain(items), before);
});
