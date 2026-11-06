'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('awarenessDashboard');
  if (!root) return;
  const preview = window.AwarenessPreview;
  const selector = document.getElementById('dashboardCampaign');
  const numberFormat = new Intl.NumberFormat('id-ID');
  const rateFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  let selected = preview.campaigns[0];
  let chartsReady = false;

  selector.replaceChildren(...preview.campaigns.map(campaign => new Option(campaign.name, campaign.id)));

  function palette() {
    const styles = getComputedStyle(document.documentElement);
    const value = name => styles.getPropertyValue('--bs-' + name).trim();
    return { primary: value('primary'), secondary: value('secondary'), muted: value('secondary-color'), heading: value('heading-color'), border: value('border-color'), paper: value('paper-bg'), font: styles.fontFamily, mode: document.documentElement.getAttribute('data-bs-theme') };
  }

  const colors = palette();
  const activityChart = new ApexCharts(document.getElementById('clickActivityChart'), {
    chart: { type: 'bar', height: 265, toolbar: { show: false }, fontFamily: colors.font, foreColor: colors.muted, animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches } },
    series: [{ name: 'Penerima unik', data: selected.hourly }],
    colors: [colors.primary],
    plotOptions: { bar: { borderRadius: 5, columnWidth: '35%', borderRadiusApplication: 'end' } },
    dataLabels: { enabled: false },
    grid: { borderColor: colors.border, strokeDashArray: 4, padding: { left: 8, right: 10 } },
    xaxis: { categories: ['09.00', '10.00', '11.00', '12.00', '13.00', '14.00'], axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0, title: { text: 'Penerima unik', style: { color: colors.muted, fontWeight: 400 } } },
    tooltip: { theme: colors.mode, y: { formatter: value => value + ' penerima' } },
    responsive: [{ breakpoint: 576, options: { chart: { height: 240 }, plotOptions: { bar: { columnWidth: '50%' } } } }]
  });

  const responseChart = new ApexCharts(document.getElementById('recipientResponseChart'), {
    chart: { type: 'donut', height: 220, fontFamily: colors.font, animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches } },
    series: [selected.clicked, preview.metrics(selected).unclicked],
    yaxis: [],
    labels: ['Diakses', 'Belum diakses'],
    colors: [colors.primary, colors.border],
    stroke: { width: 0 },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { theme: colors.mode, y: { formatter: value => value + ' penerima' } },
    plotOptions: { pie: { expandOnClick: false, donut: { size: '78%', labels: { show: true, name: { show: true, offsetY: 22, color: colors.muted, fontSize: '13px' }, value: { offsetY: -12, color: colors.heading, fontSize: '28px', fontWeight: 500, formatter: value => numberFormat.format(Number(value)) }, total: { show: true, showAlways: true, label: 'Tingkat klik', color: colors.muted, formatter: () => rateFormat.format(preview.metrics(selected).rate) + '%' } } } } }
  });

  let updatePending = false, updating = false, appliedSignature = '';
  function updateCharts() {
    if (!chartsReady) return;
    updatePending = true;
    if (!updating) void applyChartUpdates();
  }

  async function applyChartUpdates() {
    updating = true;
    try {
      while (updatePending) {
        updatePending = false;
        const theme = palette();
        const signature = JSON.stringify([selected.id, theme.primary, theme.border, theme.muted, theme.heading, theme.mode]);
        if (signature === appliedSignature) continue;
        await Promise.all([
          activityChart.updateOptions({ colors: [theme.primary], series: [{ name: 'Penerima unik', data: selected.hourly }], chart: { foreColor: theme.muted }, grid: { borderColor: theme.border }, yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0, title: { text: 'Penerima unik', style: { color: theme.muted, fontWeight: 400 } } }, tooltip: { theme: theme.mode } }, false, false),
          responseChart.updateOptions({ colors: [theme.primary, theme.border], series: [selected.clicked, preview.metrics(selected).unclicked], tooltip: { theme: theme.mode }, plotOptions: { pie: { donut: { labels: { name: { color: theme.muted }, value: { color: theme.heading }, total: { color: theme.muted } } } } } }, false, false)
        ]);
        appliedSignature = signature;
      }
    } catch (error) {
      console.error('Pembaruan grafik awareness gagal:', error);
    } finally {
      updating = false;
    }
  }

  function renderCampaign() {
    const metrics = preview.metrics(selected);
    const values = { target: selected.target, sent: selected.sent, clicked: selected.clicked, failed: metrics.failed, unclicked: metrics.unclicked, rate: rateFormat.format(metrics.rate) + '%', date: preview.formatDate(selected.date) };
    Object.entries(values).forEach(([key, value]) => {
      root.querySelectorAll('[data-dashboard-' + key + ']').forEach(element => { element.textContent = typeof value === 'number' ? numberFormat.format(value) : value; });
    });
    document.getElementById('selectedCampaignName').textContent = selected.name;
    const reportUrl = new URL(root.dataset.reportsUrl); reportUrl.searchParams.set('campaign', selected.id); reportUrl.searchParams.set('tab', 'activity'); document.getElementById('dashboardReportLink').href = reportUrl.href;
    const status = document.getElementById('campaignStatus');
    status.textContent = selected.status;
    status.className = 'badge ' + (selected.status === 'Berjalan' ? 'bg-label-success' : 'bg-label-secondary');
    const events = [];
    selected.hourly.forEach((count, hourIndex) => {
      for (let index = 0; index < count; index++) {
        const id = events.length + 1;
        const minute = 3 + Math.floor(index * 54 / count);
        events.push({ email: `karyawan${String(id).padStart(3, '0')}@perusahaan.example`, ip: `10.10.20.${30 + id}`, time: `${selected.date}T${String(hourIndex + 9).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+07:00` });
      }
    });
    document.getElementById('recentClickRows').innerHTML = events.slice(-6).reverse().map(event => `<tr><td><span class="fw-medium">${preview.escapeHtml(event.email)}</span></td><td class="text-nowrap">${event.ip}</td><td class="text-nowrap">${preview.escapeHtml(preview.formatDate(event.time, true))}</td><td><span class="badge bg-label-warning">Diakses</span></td></tr>`).join('');
    document.getElementById('recipientResponseChart').setAttribute('aria-label', `${selected.clicked} tautan penerima diakses dan ${metrics.unclicked} belum diakses`);
    updateCharts();
  }

  selector.addEventListener('change', () => {
    selected = preview.campaigns.find(campaign => campaign.id === selector.value) || preview.campaigns[0];
    renderCampaign();
  });
  renderCampaign();
  Promise.all([activityChart.render(), responseChart.render()]).then(() => { chartsReady = true; updateCharts(); });
  new MutationObserver(updateCharts).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'style', 'data-skin'] });
});
