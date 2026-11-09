const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../../public/assets/js/awareness-delivery-logs.js'), 'utf8');
const flush = async () => { await new Promise(resolve => setImmediate(resolve)); };
function element() {
  const classes = new Set();
  return { textContent: '', value: '', children: [], dataset: {}, listeners: {}, disabled: false,
    classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
    set innerHTML(_) { throw new Error('Use textContent for log data.'); },
    append(child) { this.children.push(child); }, replaceChildren(...children) { this.children = children; },
    addEventListener(name, callback) { this.listeners[name] = callback; }
  };
}
function setup() {
  const ids = new Map(), counts = ['target', 'sent', 'waiting', 'processing', 'uncertain', 'skipped'].map(key => ({ ...element(), dataset: { logCount: key } }));
  const get = id => { if (!ids.has(id)) ids.set(id, element()); return ids.get(id); };
  const root = get('deliveryLogs'); root.dataset = { indexUrl: 'http://localhost/log-pengiriman' }; root.querySelectorAll = () => counts;
  const state = { get, counts, requests: [], interval: null, pagehide: null, document: { hidden: false } };
  Object.assign(state.document, { getElementById: get, createElement: element, addEventListener: (_, callback) => callback() });
  const window = {
    location: { href: 'http://localhost/log-pengiriman?simulation=a' }, AwarenessApi: { request: url => new Promise((resolve, reject) => state.requests.push({ url, resolve, reject })) },
    setInterval: (callback, duration) => { assert.equal(duration, 2000); state.interval = callback; return 1; },
    clearInterval: () => { state.interval = null; }, addEventListener: (_, callback) => { state.pagehide = callback; }
  };
  vm.runInNewContext(script, { document: state.document, window, URL, Intl, Option: function (name, value) { this.textContent = name; this.value = value; } });
  return state;
}
const entry = (id, event = 'done') => ({ id, event, email: 'person@example.com', message: 'Email diterima SMTP.', at: '2026-11-09T02:00:00Z' });
const payload = (events = [], hasMore = false, overrides = {}) => ({ data: {
  simulation: { id: 'a', name: 'Simulasi A', status: 'running', deliveryStatus: 'running', target: 200, sent: 1, waiting: 198, processing: 1, uncertain: 0, skipped: 0, ...overrides },
  events, hasMore, updatedAt: '2026-11-09T02:00:00Z'
} });
async function ready(events = [], more = false) {
  const state = setup(); state.requests[0].resolve({ data: [{ id: 'a', name: 'Simulasi A', status: 'running' }, { id: 'b', name: 'Simulasi B', status: 'completed' }] }); await flush();
  state.requests[1].resolve(payload(events, more)); await flush(); return state;
}

test('empty list shows a clean empty state without generated history', async () => {
  const state = setup(); state.requests[0].resolve({ data: [] }); await flush();
  assert.equal(state.get('deliveryLogSimulation').disabled, true);
  assert.match(state.get('deliveryLogRows').children[0].children[0].textContent, /Belum ada Simulasi/);
  assert.equal(state.requests.length, 1);
  assert.equal(state.get('olderDeliveryLogs').disabled, true);
});

test('new logs and counters appear on polling and untrusted content renders as plain text', async () => {
  const state = await ready([entry(1, 'sending')]);
  state.interval(); assert.equal(new URL(state.requests[2].url).searchParams.get('after'), '1');
  state.requests[2].resolve(payload([{ ...entry(2, 'uncertain'), email: '<img onerror=alert(1)>', message: '<script>unsafe</script>' }], false, { uncertain: 1 })); await flush();
  const cells = state.get('deliveryLogRows').children[0].children;
  assert.equal(cells[1].children[0].textContent, 'PERLU DIPERIKSA');
  assert.equal(cells[2].textContent, '<img onerror=alert(1)>');
  assert.equal(cells[3].textContent, '<script>unsafe</script>');
  assert.equal(state.counts.find(cell => cell.dataset.logCount === 'uncertain').textContent, '1');
  assert.match(cells[0].textContent, /09.00.00/);
});

test('catch-up reads all incremental pages and keeps the latest hundred without duplicates', async () => {
  const state = await ready(Array.from({ length: 100 }, (_, index) => entry(index + 1)), true);
  state.interval(); state.requests[2].resolve(payload(Array.from({ length: 100 }, (_, index) => entry(index + 101)), true)); await flush();
  assert.equal(new URL(state.requests[3].url).searchParams.get('after'), '200');
  state.requests[3].resolve(payload([entry(200), entry(201), entry(202)])); await flush();
  assert.equal(state.get('deliveryLogRows').children.length, 100);
  assert.equal(state.get('olderDeliveryLogs').disabled, false);
  state.interval(); assert.equal(new URL(state.requests[4].url).searchParams.get('after'), '202');
});

test('older pages pause live refresh and latest resumes it without skipping history', async () => {
  const state = await ready([entry(101), entry(102)], true);
  state.get('olderDeliveryLogs').listeners.click();
  assert.equal(new URL(state.requests[2].url).searchParams.get('before'), '101');
  state.requests[2].resolve(payload([entry(99), entry(100)], false)); await flush();
  state.interval(); assert.equal(state.requests.length, 3);
  assert.match(state.get('deliveryLogMode').textContent, /Histori/);
  assert.equal(state.get('olderDeliveryLogs').disabled, true);
  state.get('latestDeliveryLogs').listeners.click(); state.requests[3].resolve(payload([entry(200)], true)); await flush();
  state.interval(); assert.equal(new URL(state.requests[4].url).searchParams.get('after'), '200');
});

test('changing simulation discards late log responses from previous selection', async () => {
  const state = await ready([entry(1)]); state.interval();
  state.get('deliveryLogSimulation').value = 'b'; state.get('deliveryLogSimulation').listeners.change();
  state.requests[3].resolve(payload([entry(20)], false, { id: 'b', name: 'Simulasi B', deliveryStatus: 'completed' })); await flush();
  state.requests[2].resolve(payload([entry(2)])); await flush();
  assert.equal(state.get('deliveryLogTitle').textContent, 'Simulasi B');
  assert.equal(state.get('deliveryLogPhase').textContent, 'Pengiriman selesai');
  assert.equal(state.get('deliveryLogRows').children.length, 1);
});

test('network errors preserve existing logs, retry polling, and hidden pages do not fetch', async () => {
  const state = await ready([entry(1)]); state.interval(); state.requests[2].reject(new Error('Terputus')); await flush();
  assert.equal(state.get('deliveryLogError').textContent, 'Terputus');
  assert.equal(state.get('deliveryLogRows').children.length, 1);
  state.document.hidden = true; state.interval(); assert.equal(state.requests.length, 3);
  state.document.hidden = false; state.interval(); state.requests[3].resolve(payload([])); await flush();
  assert.equal(state.get('deliveryLogError').classList.contains('d-none'), true);
  state.pagehide(); assert.equal(state.interval, null);
});
