'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('awarenessDashboard');
  if (!root) return;
  const api = window.AwarenessApi;
  const selector = document.getElementById('dashboardCampaign');
  const numberFormat = new Intl.NumberFormat('id-ID');
  const rateFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const statuses = { draft: ['Draf', 'secondary'], scheduled: ['Terjadwal', 'info'], running: ['Berjalan', 'success'], completed: ['Selesai', 'secondary'] };
  const empty = () => ({ id: '', name: 'Belum ada Simulasi', target: 0, sent: 0, clicked: 0, pending: 0, uncertain: 0, skipped: 0, unclicked: 0, rate: 0, progress: 0, activity: { labels: [], counts: [] }, recent: [] });
  let selected = empty(), requestVersion = 0, loading = false;
  let activityChart = null, responseChart = null, updating = false, updatePending = false, appliedSignature = '';
  const formatDate = value => value ? new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value.length === 16 ? value + ':00+07:00' : value)) + ' WIB' : '—';
  function palette() {
    const styles = getComputedStyle(document.documentElement);
    const value = name => styles.getPropertyValue('--bs-' + name).trim();
    return { primary: value('primary'), muted: value('secondary-color'), heading: value('heading-color'), border: value('border-color'), font: styles.fontFamily, mode: document.documentElement.getAttribute('data-bs-theme') };
  }
  function createActivityChart() {
    const colors = palette();
    return new ApexCharts(document.getElementById('clickActivityChart'), {
      chart: { type: 'bar', height: 265, toolbar: { show: false }, fontFamily: colors.font, foreColor: colors.muted, animations: { enabled: false } },
      series: [{ name: 'Penerima unik', data: selected.activity.counts }], colors: [colors.primary],
      noData: { text: 'Belum ada klik dalam masa evaluasi', style: { color: colors.muted } },
      plotOptions: { bar: { borderRadius: 5, columnWidth: '35%', borderRadiusApplication: 'end' } },
      dataLabels: { enabled: false }, grid: { borderColor: colors.border, strokeDashArray: 4 },
      xaxis: { categories: selected.activity.labels, axisBorder: { show: false }, axisTicks: { show: false }, title: { text: 'Waktu klik pertama · WIB' } },
      yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0 },
      tooltip: { theme: colors.mode, y: { formatter: value => value + ' penerima' } }
    });
  }
  function createResponseChart() {
    const colors = palette();
    const rate = selected.rate;
    return new ApexCharts(document.getElementById('recipientResponseChart'), {
      chart: { type: 'donut', height: 220, fontFamily: colors.font, animations: { enabled: false } },
      series: selected.sent ? [selected.clicked, selected.unclicked] : [], yaxis: [],
      noData: { text: 'Belum ada pengiriman berhasil', style: { color: colors.muted } },
      labels: ['Diakses dalam periode', 'Belum diakses dalam periode'], colors: [colors.primary, colors.border],
      stroke: { width: 0 }, legend: { show: false }, dataLabels: { enabled: false },
      tooltip: { theme: colors.mode, y: { formatter: value => value + ' penerima' } },
      plotOptions: { pie: { expandOnClick: false, donut: { size: '78%', labels: { show: true, name: { show: true, offsetY: 22, color: colors.muted, fontSize: '13px' }, value: { offsetY: -12, color: colors.heading, fontSize: '28px', fontWeight: 500, formatter: value => numberFormat.format(Number(value)) }, total: { show: true, showAlways: true, label: 'Tingkat klik', color: colors.muted, formatter: () => rateFormat.format(rate) + '%' } } } } }
    });
  }
  function updateCharts() {
    updatePending = true;
    if (!updating) void applyChartUpdates();
  }
  async function applyChartUpdates() {
    updating = true;
    try {
      while (updatePending) {
        updatePending = false;
        const signature = JSON.stringify([selected.id, selected.activity, selected.sent, selected.clicked, selected.rate, palette()]);
        if (signature === appliedSignature) continue;
        activityChart?.destroy(); responseChart?.destroy();
        activityChart = createActivityChart(); responseChart = createResponseChart();
        await Promise.all([activityChart.render(), responseChart.render()]);
        appliedSignature = signature;
      }
    } catch (error) {
      console.error('Pembaruan grafik awareness gagal:', error);
    } finally { updating = false; }
  }
  function render() {
    const values = { ...selected, rate: rateFormat.format(selected.rate) + '%', date: formatDate(selected.scheduledAt) };
    for (const key of ['target', 'sent', 'clicked', 'pending', 'uncertain', 'skipped', 'unclicked', 'rate', 'date']) {
      root.querySelectorAll('[data-dashboard-' + key + ']').forEach(element => { element.textContent = typeof values[key] === 'number' ? numberFormat.format(values[key]) : values[key]; });
    }
    document.getElementById('selectedCampaignName').textContent = selected.name;
    document.getElementById('dashboardStartsAt').textContent = formatDate(selected.scheduledAt);
    document.getElementById('dashboardEndsAt').textContent = formatDate(selected.endsAt);
    const [label, color] = statuses[selected.status] || ['—', 'secondary'];
    const status = document.getElementById('campaignStatus'); status.textContent = label; status.className = 'badge bg-label-' + color;
    document.getElementById('dashboardProgressLabel').textContent = numberFormat.format(selected.progress) + '%';
    const progress = document.getElementById('dashboardProgress');
    progress.setAttribute('aria-valuenow', String(selected.progress)); progress.querySelector('.progress-bar').style.width = selected.progress + '%';
    const reportLink = document.getElementById('dashboardReportLink');
    const url = new URL(root.dataset.reportsUrl); if (selected.id) { url.searchParams.set('campaign', selected.id); url.searchParams.set('tab', 'activity'); }
    reportLink.href = url.href;
    const rows = document.getElementById('recentClickRows'); rows.replaceChildren();
    for (const event of selected.recent) {
      const row = document.createElement('tr');
      for (const value of [event.email, event.ip || '—', formatDate(event.at), 'Diakses dalam periode']) {
        const cell = document.createElement('td'); cell.textContent = value; row.append(cell);
      }
      rows.append(row);
    }
    if (!selected.recent.length) {
      const row = document.createElement('tr'), cell = document.createElement('td'); cell.colSpan = 4; cell.className = 'text-center text-body-secondary py-8';
      cell.textContent = selected.id ? 'Belum ada klik dalam masa evaluasi.' : 'Belum ada Simulasi. Buat Simulasi untuk melihat ringkasan.'; row.append(cell); rows.append(row);
    }
    document.getElementById('recipientResponseChart').setAttribute('aria-label', `${selected.clicked} penerima mengeklik dalam periode dan ${selected.unclicked} belum mengeklik dalam periode`);
    updateCharts();
  }
  async function load(id = '', background = false) {
    if (background && loading) return;
    const version = ++requestVersion; loading = true;
    const url = new URL(root.dataset.dashboardUrl); if (id) url.searchParams.set('simulation', id);
    try {
      const { data } = await api.request(url.href);
      if (version !== requestVersion) return;
      selected = data.selected || empty();
      selector.replaceChildren(...(data.simulations.length ? data.simulations.map(item => new Option(item.name, item.id)) : [new Option('Belum ada Simulasi', '')]));
      selector.value = selected.id; selector.disabled = !data.simulations.length;
      root.querySelector('[data-load-error]')?.remove();
      document.getElementById('dashboardUpdated').textContent = 'Diperbarui ' + formatDate(data.updatedAt);
      render();
    } catch (error) {
      if (version !== requestVersion) return;
      api.showLoadError(root, error.message);
      document.getElementById('dashboardUpdated').textContent = 'Pembaruan data belum berhasil';
      if (!background) { selected = empty(); render(); }
    } finally { if (version === requestVersion) loading = false; }
  }
  selector.addEventListener('change', () => { selected = empty(); render(); void load(selector.value); });
  new MutationObserver(updateCharts).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'style', 'data-skin'] });
  const interval = window.setInterval(() => { if (!document.hidden) void load(selector.value, true); }, 15000);
  window.addEventListener('pagehide', () => { requestVersion++; window.clearInterval(interval); activityChart?.destroy(); responseChart?.destroy(); }, { once: true });
  void load();
});
