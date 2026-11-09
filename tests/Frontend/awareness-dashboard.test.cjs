const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/assets/js/awareness-dashboard.js'), 'utf8');
const plain = value => JSON.parse(JSON.stringify(value));
const flush = async () => { await new Promise(resolve => setImmediate(resolve)); };
function element() {
  return {
    textContent: '', value: '', disabled: false, children: [], style: {}, attributes: {}, listeners: {}, dataset: {},
    set innerHTML(_) { throw new Error('Dashboard must render untrusted content as text.'); },
    setAttribute(name, value) { this.attributes[name] = value; },
    replaceChildren(...children) { this.children = children; },
    append(child) { this.children.push(child); },
    addEventListener(name, callback) { this.listeners[name] = callback; },
    remove() {},
    querySelector() { return this.bar ||= element(); }
  };
}
function setup() {
  const ids = new Map(), fields = new Map(), requests = [], charts = [];
  const get = id => { if (!ids.has(id)) ids.set(id, element()); return ids.get(id); };
  const root = get('awarenessDashboard');
  root.dataset = { dashboardUrl: 'http://localhost/dashboard/data', reportsUrl: 'http://localhost/reports' };
  root.querySelectorAll = selector => { if (!fields.has(selector)) fields.set(selector, [element()]); return fields.get(selector); };
  root.querySelector = () => null;
  const document = { hidden: false, documentElement: { getAttribute: () => 'light' }, getElementById: get, createElement: element, addEventListener: (_, callback) => callback() };
  const state = { ids, fields, requests, charts, document, root, get, errors: [], interval: null, pagehide: null, themeChanged: null };
  const window = {
    AwarenessApi: { request: url => new Promise((resolve, reject) => requests.push({ url, resolve, reject })), showLoadError: (_, message) => state.errors.push(message) },
    setInterval: (callback, duration) => { assert.equal(duration, 15000); state.interval = callback; return 1; },
    clearInterval: () => { state.interval = null; }, addEventListener: (_, callback) => { state.pagehide = callback; }
  };
  const sandbox = {
    window, document, URL, Intl, console,
    Option: function (name, value) { this.textContent = name; this.value = value; },
    getComputedStyle: () => ({ getPropertyValue: name => name, fontFamily: 'Arial' }),
    MutationObserver: class { constructor(callback) { state.themeChanged = callback; } observe() {} },
    ApexCharts: class { constructor(node, options) { this.node = node; this.options = options; charts.push(this); } render() { return Promise.resolve(); } destroy() { this.destroyed = true; } }
  };
  vm.runInNewContext(script, sandbox);
  return state;
}
function data(overrides = {}) {
  return { data: { simulations: [{ id: 'a', name: 'Simulasi A' }, { id: 'b', name: 'Simulasi B' }], updatedAt: '2026-11-09T03:00:00Z', selected: {
    id: 'a', name: 'Simulasi A', status: 'running', scheduledAt: '2026-11-09T09:00', endsAt: '2026-11-09T12:00',
    target: 4, sent: 2, clicked: 1, pending: 1, uncertain: 1, skipped: 0, unclicked: 1, rate: 50, progress: 75,
    activity: { labels: ['09 Nov 10.00'], counts: [1] }, recent: [{ email: '<img src=x onerror=alert(1)>', ip: '10.1.1.2', at: '2026-11-09T03:01:00Z' }], ...overrides
  } } };
}

test('empty database shows zero metrics and empty charts without sample events', async () => {
  const state = setup();
  state.requests[0].resolve({ data: { simulations: [], selected: null, updatedAt: '2026-11-09T03:00:00Z' } }); await flush();
  assert.equal(state.get('dashboardCampaign').disabled, true);
  assert.equal(state.get('selectedCampaignName').textContent, 'Belum ada Simulasi');
  assert.equal(state.fields.get('[data-dashboard-target]')[0].textContent, '0');
  assert.equal(state.fields.get('[data-dashboard-rate]')[0].textContent, '0,0%');
  assert.match(state.get('recentClickRows').children[0].children[0].textContent, /Belum ada Simulasi/);
  assert.deepEqual(plain(state.charts.at(-1).options.series), []);
  assert.equal(new URL(state.get('dashboardReportLink').href).search, '');
});

test('real data renders IP and dates as text with matching report selection and progress', async () => {
  const state = setup(); state.requests[0].resolve(data()); await flush();
  assert.equal(state.get('recentClickRows').children[0].children[0].textContent, '<img src=x onerror=alert(1)>');
  assert.equal(state.get('recentClickRows').children[0].children[1].textContent, '10.1.1.2');
  assert.match(state.get('dashboardStartsAt').textContent, /09.00 WIB/);
  assert.match(state.get('dashboardEndsAt').textContent, /12.00 WIB/);
  assert.equal(state.get('dashboardProgress').attributes['aria-valuenow'], '75');
  assert.equal(state.get('dashboardProgress').bar.style.width, '75%');
  assert.equal(new URL(state.get('dashboardReportLink').href).searchParams.get('campaign'), 'a');
  assert.equal(state.get('campaignStatus').textContent, 'Berjalan');
  assert.deepEqual(plain(state.charts.at(-1).options.series), [1, 1]);
});

test('polling updates graphs when counts change for the same simulation and pauses on hidden tabs', async () => {
  const state = setup(); state.requests[0].resolve(data()); await flush();
  state.interval();
  assert.equal(new URL(state.requests[1].url).searchParams.get('simulation'), 'a');
  state.requests[1].resolve(data({ clicked: 2, unclicked: 0, rate: 100, activity: { labels: ['09 Nov 10.00'], counts: [2] } })); await flush();
  assert.equal(state.fields.get('[data-dashboard-clicked]')[0].textContent, '2');
  assert.deepEqual(plain(state.charts.at(-1).options.series), [2, 0]);
  assert.deepEqual(plain(state.charts.at(-2).options.series[0].data), [2]);
  assert.equal(state.charts[0].destroyed, true);
  state.document.hidden = true; state.interval(); assert.equal(state.requests.length, 2);
  state.pagehide(); assert.equal(state.interval, null); assert.equal(state.charts.at(-1).destroyed, true);
});

test('switching simulations ignores a late response from the previous selection', async () => {
  const state = setup(); state.requests[0].resolve(data()); await flush();
  state.get('dashboardCampaign').value = 'a'; state.get('dashboardCampaign').listeners.change();
  state.get('dashboardCampaign').value = 'b'; state.get('dashboardCampaign').listeners.change();
  state.requests[2].resolve(data({ id: 'b', name: 'Simulasi B' })); await flush();
  state.requests[1].resolve(data()); await flush();
  assert.equal(state.get('selectedCampaignName').textContent, 'Simulasi B');
  assert.equal(state.get('dashboardCampaign').value, 'b');
});

test('refresh errors retain last confirmed metrics and initial errors never show invented activity', async () => {
  const state = setup(); state.requests[0].resolve(data()); await flush();
  state.interval(); state.requests[1].reject(new Error('Tidak terhubung')); await flush();
  assert.equal(state.get('selectedCampaignName').textContent, 'Simulasi A');
  assert.deepEqual(state.errors, ['Tidak terhubung']);
  assert.match(state.get('dashboardUpdated').textContent, /belum berhasil/);
  const initial = setup(); initial.requests[0].reject(new Error('Gagal')); await flush();
  assert.equal(initial.get('selectedCampaignName').textContent, 'Belum ada Simulasi');
  assert.equal(initial.fields.get('[data-dashboard-sent]')[0].textContent, '0');
});
