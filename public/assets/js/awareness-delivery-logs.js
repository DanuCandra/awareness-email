'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('deliveryLogs');
  if (!root) return;
  const api = window.AwarenessApi;
  const selector = document.getElementById('deliveryLogSimulation');
  const olderButton = document.getElementById('olderDeliveryLogs');
  const latestButton = document.getElementById('latestDeliveryLogs');
  const errorBox = document.getElementById('deliveryLogError');
  const statuses = { draft: 'Draf', scheduled: 'Terjadwal', running: 'Berjalan', completed: 'Selesai' };
  const phases = { draft: ['Draf', 'secondary'], scheduled: ['Menunggu jadwal', 'info'], running: ['RUNNING', 'primary'], completed: ['Pengiriman selesai', 'success'], completed_with_issues: ['Selesai dengan catatan', 'warning'], closing: ['Evaluasi selesai · menutup proses', 'warning'] };
  const events = { queued: ['QUEUED', 'info'], running: ['RUNNING', 'primary'], sending: ['SENDING', 'primary'], done: ['DONE', 'success'], fail: ['FAIL', 'danger'], uncertain: ['PERLU DIPERIKSA', 'warning'], waiting: ['WAITING', 'secondary'], skipped: ['SKIPPED', 'secondary'], delivery_completed: ['DONE', 'success'], evaluation_completed: ['EVALUASI SELESAI', 'secondary'], cancelled: ['DIBATALKAN', 'secondary'] };
  const formatDate = value => new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(value));
  let simulationId = '', rows = [], cursor = 0, hasOlder = false, history = false, loading = false, version = 0, listVersion = 0;

  function buttons() {
    olderButton.disabled = loading || !simulationId || !hasOlder;
    latestButton.disabled = loading || !simulationId;
  }
  function renderRows(message) {
    const body = document.getElementById('deliveryLogRows'); body.replaceChildren();
    for (const event of rows) {
      const row = document.createElement('tr');
      const time = document.createElement('td'); time.className = 'text-nowrap small'; time.textContent = formatDate(event.at); row.append(time);
      const state = document.createElement('td'), badge = document.createElement('span');
      const [label, color] = events[event.event] || ['INFO', 'secondary']; badge.className = 'badge bg-label-' + color; badge.textContent = label; state.append(badge); row.append(state);
      const email = document.createElement('td'); email.className = 'text-break'; email.textContent = event.email || '—'; row.append(email);
      const detail = document.createElement('td'); detail.className = 'text-break'; detail.textContent = event.message; row.append(detail); body.append(row);
    }
    if (!rows.length) {
      const row = document.createElement('tr'), cell = document.createElement('td'); cell.colSpan = 4; cell.className = 'text-center text-body-secondary py-8';
      cell.textContent = message || (simulationId ? 'Belum ada log pengiriman tercatat untuk Simulasi ini.' : 'Belum ada Simulasi. Buat Simulasi untuk melihat proses pengiriman.'); row.append(cell); body.append(row);
    }
    document.getElementById('deliveryLogMode').textContent = history ? 'Histori · pembaruan dijeda. Pilih Terbaru (live) untuk memantau kembali.' : '100 log terbaru · pembaruan setiap 2 detik';
    buttons();
  }
  function renderSummary(simulation) {
    root.querySelectorAll('[data-log-count]').forEach(element => { element.textContent = new Intl.NumberFormat('id-ID').format(simulation?.[element.dataset.logCount] || 0); });
    const phase = document.getElementById('deliveryLogPhase');
    const [label, color] = phases[simulation?.deliveryStatus] || ['—', 'secondary']; phase.textContent = label; phase.className = 'badge bg-label-' + color;
    document.getElementById('deliveryLogTitle').textContent = simulation?.name || 'Proses pengiriman';
  }
  async function load(mode = 'latest') {
    if (!simulationId || loading) return;
    loading = true; buttons();
    const requestVersion = version;
    const id = simulationId;
    let after = mode === 'incremental' ? cursor : null;
    try {
      for (let page = 0; page < 5; page++) {
        const url = new URL(root.dataset.indexUrl + '/' + encodeURIComponent(id));
        if (mode === 'older' && rows.length) url.searchParams.set('before', Math.min(...rows.map(row => row.id)));
        if (after !== null) url.searchParams.set('after', after);
        const { data } = await api.request(url.href);
        if (requestVersion !== version) return;
        renderSummary(data.simulation);
        if (mode === 'incremental') {
          const unique = new Map([...rows, ...data.events].map(event => [event.id, event]));
          if (unique.size > 100) hasOlder = true;
          rows = [...unique.values()].sort((a, b) => b.id - a.id).slice(0, 100);
        } else {
          rows = [...data.events].sort((a, b) => b.id - a.id); hasOlder = data.hasMore;
        }
        if (mode !== 'older' && data.events.length) cursor = Math.max(cursor, ...data.events.map(event => event.id));
        errorBox.classList.add('d-none'); errorBox.textContent = '';
        document.getElementById('deliveryLogUpdated').textContent = 'Diperbarui ' + formatDate(data.updatedAt) + ' WIB';
        renderRows();
        if (mode !== 'incremental' || !data.hasMore || !data.events.length) break;
        after = cursor;
      }
    } catch (error) {
      if (requestVersion !== version) return;
      errorBox.textContent = error.message; errorBox.classList.remove('d-none');
      document.getElementById('deliveryLogUpdated').textContent = 'Pembaruan terputus · coba muat ulang';
      if (!rows.length) renderRows('Log belum dapat dimuat.');
    } finally {
      if (requestVersion === version) { loading = false; buttons(); }
    }
  }
  function select(id) {
    version++; simulationId = id; rows = []; cursor = 0; hasOlder = false; history = false; loading = false;
    errorBox.classList.add('d-none'); renderSummary(null); renderRows(id ? 'Memuat log…' : undefined);
    document.getElementById('deliveryLogUpdated').textContent = id ? 'Memuat data…' : 'Belum ada data';
    if (id) void load();
  }
  async function loadList() {
    const requestVersion = ++listVersion;
    try {
      const { data } = await api.request(root.dataset.indexUrl);
      if (requestVersion !== listVersion) return;
      selector.replaceChildren(...(data.length ? data.map(item => new Option(item.name + ' · ' + (statuses[item.status] || item.status), item.id)) : [new Option('Belum ada Simulasi', '')]));
      selector.disabled = !data.length;
      const requested = simulationId || new URL(window.location.href).searchParams.get('simulation');
      selector.value = data.find(item => item.id === requested)?.id || data[0]?.id || '';
      select(selector.value);
    } catch (error) {
      if (requestVersion !== listVersion) return;
      errorBox.textContent = error.message; errorBox.classList.remove('d-none');
      if (!simulationId) { selector.replaceChildren(new Option('Gagal memuat Simulasi', '')); renderRows('Daftar Simulasi belum dapat dimuat. Pilih Muat ulang untuk mencoba kembali.'); }
    }
  }
  selector.addEventListener('change', () => select(selector.value));
  olderButton.addEventListener('click', () => { history = true; void load('older'); });
  latestButton.addEventListener('click', () => { history = false; cursor = 0; void load(); });
  document.getElementById('reloadDeliveryLogs').addEventListener('click', () => { void loadList(); });
  const interval = window.setInterval(() => { if (!document.hidden && !history && !loading && simulationId) void load('incremental'); }, 2000);
  window.addEventListener('pagehide', () => { version++; listVersion++; window.clearInterval(interval); }, { once: true });
  void loadList();
});
