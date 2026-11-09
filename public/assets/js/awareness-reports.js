'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const root = document.getElementById('reportsWorkspace');
  if (!root) return;
  const core = window.AwarenessCampaigns, reports = window.AwarenessReports, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI;
  const api = window.AwarenessApi, escape = preview.escapeHtml;
  let state = { campaigns: [] };
  try { state.campaigns = (await api.request(api.urls.simulations + '?recipients=1')).data; }
  catch (error) { api.showLoadError(root, error.message); return; }
  const number = value => Number(value).toLocaleString('id-ID');
  const percent = value => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value) + '%';
  const date = value => value ? preview.formatDate(value, true) : '—';
  const dateOnly = value => value ? preview.formatDate(value + 'T00:00:00+07:00') : '';
  const allCampaigns = reports.filterCampaigns(state.campaigns);
  const params = new URLSearchParams(window.location.search);
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  const preferences = window.AwarenessSettings.reportDefaults(window.AwarenessSettings.load(ui.storage), today);
  const explicitScope = ['campaign', 'from', 'to'].some(key => params.has(key));
  let filters = { campaign: params.get('campaign') || '', from: explicitScope ? params.get('from') || '' : preferences.from, to: explicitScope ? params.get('to') || '' : preferences.to };
  ui.find('reportPageLength').value = String(preferences.pageLength);
  if (reports.validateFilters(filters)) { filters.from = ''; filters.to = ''; }
  let selected = [], activity = [], summary, chart, chartReady = false, chartPending = false, chartUpdating = false, appliedSignature = '';
  const modal = bootstrap.Modal.getOrCreateInstance(ui.find('reportRecipientModal'));
  const campaignSelector = ui.find('reportCampaign');
  campaignSelector.replaceChildren(new Option('Semua simulasi', ''), ...allCampaigns.map(item => new Option(item.name, item.id)));
  if (filters.campaign && !allCampaigns.some(item => item.id === filters.campaign)) campaignSelector.add(new Option('Simulasi tidak tersedia', filters.campaign));
  campaignSelector.value = filters.campaign; ui.find('reportFrom').value = filters.from; ui.find('reportTo').value = filters.to;

  function campaignUrl(id) { const url = new URL(root.dataset.campaignUrl); url.searchParams.set('id', id); return url.href; }
  function statusBadge(status) { const item = reports.statuses[status]; return `<span class="badge bg-label-${item.color}">${item.label}</span>`; }
  const summaries = new Map();
  function campaignSummary(item) {
    if (!summaries.has(item.id)) summaries.set(item.id, reports.summarize([item], reports.recipientRows(item)));
    return summaries.get(item.id);
  }

  const campaignTable = new DataTable(ui.find('reportCampaignTable'), {
    data: [], columns: [
      { data: 'name', render: (value, type, item) => type === 'display' ? `<div class="report-campaign-name"><a class="fw-medium" href="${escape(campaignUrl(item.id))}">${escape(value)}</a><div class="d-flex flex-wrap gap-2 mt-2"><small class="text-body-secondary">${escape(dateOnly(reports.campaignDate(item)))}</small>${ui.badge(item.status)}</div></div>` : value },
      ...['scheduledAt', 'endsAt'].map(key => ({ data: key, defaultContent: '', render: (value, type) => ['sort', 'type'].includes(type) ? core.parseSchedule(value) || 0 : escape(core.scheduleLabel(value)) })),
      ...['target', 'sent', 'failed', 'clicked'].map(key => ({ data: null, render: (value, type, item) => type === 'display' ? number(campaignSummary(item)[key]) : campaignSummary(item)[key] })),
      { data: null, render: (value, type, item) => type === 'display' ? number(campaignSummary(item).afterClicks) : campaignSummary(item).afterClicks },
      { data: null, render: (value, type, item) => type === 'display' ? `<span class="badge bg-label-warning">${campaignSummary(item).evaluationSent ? percent(campaignSummary(item).rate) : '—'}</span>` : campaignSummary(item).rate },
      { data: 'id', orderable: false, searchable: false, render: id => `<button type="button" class="btn btn-sm btn-label-primary text-nowrap" data-report-campaign="${escape(id)}">Lihat aktivitas<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></button>` }
    ], order: [], pageLength: 10, layout: ui.tableLayout, language: ui.tableLanguage
  });
  const activityTable = new DataTable(ui.find('reportActivityTable'), {
    data: [], columns: [
      { data: 'email', render: (value, type, item) => type === 'display' ? `<div class="report-recipient-name"><span class="fw-medium">${escape(value)}</span><small class="d-block text-body-secondary mt-1">${escape(item.campaignName)}</small></div>` : value + ' ' + item.campaignName },
      { data: 'status', render: (value, type) => type === 'display' ? statusBadge(value) : value },
      { data: 'ip', render: (value, type) => type === 'display' ? `<span class="font-monospace text-nowrap">${escape(value || '—')}</span>` : value },
      { data: 'firstClick', render: (value, type) => type === 'sort' || type === 'type' ? Date.parse(value) || 0 : type === 'display' ? `<span class="text-nowrap small">${escape(date(value))}</span>` : date(value) },
      { data: 'clicks', render: (value, type) => type === 'display' ? number(value) : value },
      { data: 'browser', render: (value, type, item) => type === 'display' ? value ? `<span class="text-nowrap">${escape(value)}</span><small class="d-block text-body-secondary text-nowrap">${escape(item.os)} · ${escape(item.device)}</small>` : '—' : [value, item.os, item.device].join(' ') },
      { data: 'period', render: (value, type, item) => {
        const counts = { within: item.withinClicks, after: item.afterClicks, before: item.beforeClicks, unknown: item.unclassifiedClicks };
        const keys = Object.keys(counts).filter(key => counts[key] > 0);
        if (type !== 'display') return keys.join(' ') || 'none';
        return keys.map(key => `<span class="badge bg-label-${key === 'within' ? 'success' : 'secondary'} text-wrap d-block mb-1">${reports.periods[key]}: ${number(counts[key])}</span>`).join('') || 'Tidak ada akses';
      } },
      { data: 'id', orderable: false, searchable: false, render: id => `<button type="button" class="btn btn-icon btn-text-primary rounded-pill" data-report-recipient="${escape(id)}" aria-label="Lihat detail aktivitas penerima"><i class="icon-base ti tabler-eye icon-22px" aria-hidden="true"></i></button>` }
    ], order: [[3, 'desc']], pageLength: preferences.pageLength, layout: ui.tableLayout, language: ui.tableLanguage
  });

  function updateActivityCount() {
    const visible = activityTable.rows({ search: 'applied' }).count();
    ui.find('reportActivityCount').textContent = number(visible) + ' dari ' + number(activity.length) + ' penerima';
    ui.find('exportActivityReport').disabled = visible === 0;
  }
  activityTable.on('draw', updateActivityCount);
  function filterActivity() {
    const status = ui.find('reportActivityStatus').value;
    activityTable.column(1).search(status ? '^' + status + '$' : '', true, false);
    const period = ui.find('reportActivityPeriod').value;
    activityTable.column(6).search(period ? '\\b' + period + '\\b' : '', true, false);
    activityTable.search(ui.find('reportActivitySearch').value).draw();
  }
  ui.find('reportActivitySearch').addEventListener('input', filterActivity);
  ui.find('reportActivityStatus').addEventListener('change', filterActivity);
  ui.find('reportActivityPeriod').addEventListener('change', filterActivity);
  ui.find('reportPageLength').addEventListener('change', event => activityTable.page.len(Number(event.target.value)).draw());
  ui.find('reportActivityTab').addEventListener('shown.bs.tab', () => activityTable.columns.adjust().draw(false));
  ui.find('reportCampaignsTab').addEventListener('shown.bs.tab', () => campaignTable.columns.adjust().draw(false));

  function palette() {
    const styles = getComputedStyle(document.documentElement), value = name => styles.getPropertyValue('--bs-' + name).trim();
    return { primary: value('primary'), muted: value('secondary-color'), border: value('border-color'), font: styles.fontFamily, mode: document.documentElement.getAttribute('data-bs-theme') };
  }
  function chartOptions() {
    const colors = palette(), items = selected.slice(0, 6);
    return {
      chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: colors.font, foreColor: colors.muted, animations: { enabled: false } },
      series: [{ name: 'Tingkat klik', data: items.map(item => campaignSummary(item).evaluationSent ? Number(campaignSummary(item).rate.toFixed(1)) : null) }], colors: [colors.primary],
      plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '38%', borderRadiusApplication: 'end' } },
      grid: { borderColor: colors.border, strokeDashArray: 4, padding: { left: 0, right: 25 } },
      dataLabels: { enabled: true, formatter: percent, style: { fontSize: '12px', fontWeight: 500 } },
      xaxis: { categories: items.map(item => escape(item.name.length > 34 ? item.name.slice(0, 31) + '…' : item.name)), min: 0, max: 100, tickAmount: 4, labels: { formatter: value => Math.round(value) + '%' }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { maxWidth: 230 } }, tooltip: { enabled: false },
      noData: { text: 'Belum ada hasil simulasi', style: { color: colors.muted } },
      responsive: [{ breakpoint: 576, options: { yaxis: { labels: { maxWidth: 125, style: { fontSize: '10px' } } }, chart: { height: 260 } } }]
    };
  }
  async function updateChart() {
    chartPending = true;
    if (!chartReady || chartUpdating) return;
    chartUpdating = true;
    try {
      while (chartPending) {
        chartPending = false;
        const options = chartOptions(), signature = JSON.stringify(options);
        if (signature === appliedSignature || !selected.length) continue;
        await chart.updateOptions(options, false, false);
        appliedSignature = signature;
      }
    } catch (error) { console.error('Pembaruan grafik laporan gagal:', error); }
    finally { chartUpdating = false; }
  }

  function periodLabel() { return filters.from || filters.to ? (dateOnly(filters.from) || 'Awal periode') + ' – ' + (dateOnly(filters.to) || 'Akhir periode') : 'Seluruh periode'; }
  function renderPrint() {
    ui.find('reportPrint').innerHTML = `<h1>Laporan Security Awareness</h1><p><strong>${escape(window.AwarenessSettings.load(ui.storage).identity.companyName)} · Aktivitas tersimpan</strong><br>Periode pengiriman: ${escape(periodLabel())}<br>Dibuat: ${escape(preview.formatDate(new Date().toISOString(), true))}</p><p>${number(summary.campaigns)} simulasi · ${number(summary.sent)} berhasil dikirim · ${number(summary.clicked)} penerima mengeklik · tingkat klik ${summary.evaluationSent ? percent(summary.rate) : '—'}</p><table><thead><tr><th>Simulasi</th><th>Mulai · WIB</th><th>Batas evaluasi · WIB</th><th>Target</th><th>Berhasil</th><th>Gagal</th><th>Mengeklik dalam periode</th><th>Akses setelah batas</th><th>Tingkat klik</th></tr></thead><tbody>${selected.map(item => { const value = campaignSummary(item); return `<tr><td>${escape(item.name)}</td><td>${escape(core.scheduleLabel(item.scheduledAt))}</td><td>${escape(core.scheduleLabel(item.endsAt))}</td><td>${number(value.target)}</td><td>${number(value.sent)}</td><td>${number(value.failed)}</td><td>${number(value.clicked)}</td><td>${number(value.afterClicks)}</td><td>${value.evaluationSent ? percent(value.rate) : '—'}</td></tr>`; }).join('')}</tbody></table><p>Penerima mengeklik dihitung unik pada setiap simulasi. ${number(summary.uniqueEmails)} alamat email unik lintas simulasi; ${number(summary.clicks)} akses dalam periode termasuk klik ulang. Tingkat klik = penerima mengeklik dalam periode dibagi pengiriman berhasil pada simulasi dengan periode lengkap. Akses setelah batas: ${number(summary.afterClicks)}; sebelum mulai: ${number(summary.beforeClicks)}; belum diklasifikasi: ${number(summary.unclassifiedClicks)}. Simulasi dengan periode belum lengkap: ${number(summary.missingPeriods)}. Data pada laporan ini berasal dari aktivitas Simulasi yang tersimpan.</p>`;
  }
  function renderReport() {
    selected = reports.filterCampaigns(state.campaigns, filters);
    activity = selected.flatMap(reports.recipientRows);
    summary = reports.summarize(selected, activity);
    root.querySelectorAll('[data-report-metric]').forEach(element => { const key = element.dataset.reportMetric; element.textContent = key === 'rate' ? summary.evaluationSent ? percent(summary.rate) : '—' : number(summary[key]); });
    ui.find('reportScope').textContent = periodLabel() + ' · ' + selected.length + ' simulasi';
    ui.find('reportPeriodSummary').textContent = 'Hasil utama menghitung akses dari waktu mulai sampai batas evaluasi (termasuk tepat pada batas). Dasar tingkat klik: ' + number(summary.evaluationSent) + ' pengiriman berhasil dengan periode lengkap. Akses setelah batas: ' + number(summary.afterClicks) + ' · Sebelum mulai: ' + number(summary.beforeClicks) + ' · Belum diklasifikasi: ' + number(summary.unclassifiedClicks) + '. ' + (summary.missingPeriods ? summary.missingPeriods + ' simulasi belum memiliki periode lengkap dan tidak masuk perhitungan tingkat klik.' : '');
    ui.find('reportEmpty').classList.toggle('d-none', selected.length > 0);
    ui.find('reportResults').classList.toggle('d-none', !selected.length);
    ui.find('printReport').disabled = ui.find('exportReport').disabled = !selected.length;
    const delivered = summary.target ? summary.sent / summary.target * 100 : 0;
    ui.find('reportDeliveryBar').style.width = delivered + '%'; ui.find('reportDeliveryProgress').setAttribute('aria-valuenow', delivered.toFixed(1));
    ui.find('reportClickBar').style.width = summary.rate + '%'; ui.find('reportClickProgress').setAttribute('aria-valuenow', summary.rate.toFixed(1));
    ui.find('reportChartCount').textContent = Math.min(selected.length, 6) + ' simulasi';
    ui.find('reportChartCaption').textContent = selected.length > 6 ? 'Grafik menampilkan 6 simulasi terbaru. Seluruh hasil tersedia pada tabel dan ekspor.' : 'Tingkat klik dihitung dari pengiriman berhasil dengan periode evaluasi lengkap.';
    ui.find('reportComparisonChart').setAttribute('aria-label', selected.slice(0, 6).map(item => item.name + ': ' + percent(campaignSummary(item).rate)).join('; '));
    campaignTable.clear().rows.add(selected).draw();
    activityTable.clear().rows.add(activity); filterActivity();
    renderPrint();
    if (!chart && selected.length) {
      chart = new ApexCharts(ui.find('reportComparisonChart'), chartOptions());
      chart.render().then(() => { chartReady = true; void updateChart(); }).catch(error => console.error('Grafik laporan gagal dimuat:', error));
    } else void updateChart();
  }

  function applyFilters() {
    const next = { campaign: campaignSelector.value, from: ui.find('reportFrom').value, to: ui.find('reportTo').value };
    const error = reports.validateFilters(next);
    ui.find('reportFilterError').textContent = error; ui.find('reportFilterError').classList.toggle('d-none', !error);
    for (const id of ['reportFrom', 'reportTo']) { ui.find(id).classList.toggle('is-invalid', !!error); ui.find(id).setAttribute('aria-invalid', String(!!error)); }
    if (error) { ui.find('reportFrom').focus(); return; }
    filters = next;
    const url = new URL(window.location.href);
    for (const key of ['campaign', 'from', 'to']) { if (filters[key]) url.searchParams.set(key, filters[key]); else url.searchParams.delete(key); }
    window.history.replaceState(null, '', url); renderReport();
  }
  ui.find('reportFilterForm').addEventListener('submit', event => { event.preventDefault(); applyFilters(); });
  function resetFilters() {
    campaignSelector.value = ''; ui.find('reportFrom').value = ''; ui.find('reportTo').value = '';
    ui.find('reportActivitySearch').value = ''; ui.find('reportActivityStatus').value = ''; ui.find('reportActivityPeriod').value = '';
    applyFilters();
  }
  ui.find('resetReportFilters').addEventListener('click', resetFilters);
  ui.find('resetEmptyReport').addEventListener('click', resetFilters);
  ui.find('reportCampaignTable').addEventListener('click', event => {
    const button = event.target.closest('[data-report-campaign]');
    if (!button) return;
    campaignSelector.value = button.dataset.reportCampaign; ui.find('reportFrom').value = filters.from; ui.find('reportTo').value = filters.to; ui.find('reportActivitySearch').value = ''; ui.find('reportActivityStatus').value = ''; ui.find('reportActivityPeriod').value = ''; applyFilters();
    bootstrap.Tab.getOrCreateInstance(ui.find('reportActivityTab')).show();
    ui.find('reportActivityTab').scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  });
  ui.find('reportActivityTable').addEventListener('click', event => {
    const button = event.target.closest('[data-report-recipient]');
    const item = button && activity.find(row => row.id === button.dataset.reportRecipient);
    if (!item) return;
    for (const [key, value] of Object.entries({ Email: item.email, Campaign: item.campaignName, First: date(item.firstClick), Last: date(item.lastClick), Clicks: number(item.clicks), Ip: item.ip || '—', Browser: item.browser || '—', Os: item.os || '—', Device: item.device || '—' })) ui.find('reportRecipient' + key).textContent = value;
    ui.find('reportRecipientStatus').innerHTML = statusBadge(item.status);
    ui.find('reportRecipientStart').textContent = core.scheduleLabel(item.scheduledAt);
    ui.find('reportRecipientEnd').textContent = core.scheduleLabel(item.endsAt);
    ui.find('reportRecipientEvaluation').textContent = 'Dalam periode: ' + item.withinClicks + ' · Setelah batas: ' + item.afterClicks + ' · Sebelum mulai: ' + item.beforeClicks + ' · Belum diklasifikasi: ' + item.unclassifiedClicks;
    ui.find('reportRecipientCampaignLink').href = campaignUrl(item.campaignId);
    ui.find('reportRecipientNoClicks').classList.toggle('d-none', item.events.length > 0);
    ui.find('reportRecipientTimeline').innerHTML = item.events.map((visit, index) => `<li class="timeline-item timeline-item-transparent"><span class="timeline-point timeline-point-${index ? 'secondary' : 'warning'}"></span><div class="timeline-event"><div class="timeline-header mb-2"><h6 class="mb-0">${index ? 'Klik ulang' : 'Klik pertama'}</h6><small class="text-body-secondary">${escape(date(visit.at))}</small></div><p class="small mb-1">${escape(visit.ip)} · ${escape(visit.browser)} · ${escape(visit.os)}</p><small class="text-body-secondary">${escape(visit.device)} · Akses ke-${index + 1} · ${escape(reports.periods[visit.period])}</small></div></li>`).join('');
    modal.show();
  });
  let exporting = false;
  async function download(sheet, name, message) {
    if (exporting) return;
    exporting = true;
    const buttons = ['exportCampaignReport', 'exportActivityReport'].map(ui.find);
    buttons.forEach(button => { button.disabled = true; });
    try {
      const blob = await reports.createWorkbook(window.JSZip, sheet);
      preview.download(blob, name + '.xlsx');
      ui.notify(message);
    } catch (error) { ui.notify('Ekspor Excel gagal. ' + error.message); }
    finally { exporting = false; ui.find('exportCampaignReport').disabled = !selected.length; updateActivityCount(); }
  }
  ui.find('exportCampaignReport').addEventListener('click', () => {
    if (selected.length) void download(reports.summarySheet(selected), 'ringkasan-simulasi', 'Ringkasan simulasi sesuai filter berhasil diekspor sebagai Excel.');
  });
  ui.find('exportActivityReport').addEventListener('click', () => {
    const rows = activityTable.rows({ search: 'applied', order: 'applied' }).data().toArray();
    if (rows.length) void download(reports.activitySheet(rows), 'aktivitas-penerima', number(rows.length) + ' baris aktivitas sesuai filter diekspor sebagai Excel, termasuk seluruh halaman tabel.');
  });
  ui.find('printReport').addEventListener('click', () => { if (selected.length) { renderPrint(); window.print(); } });
  window.addEventListener('beforeprint', renderPrint);
  new MutationObserver(() => { void updateChart(); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'style', 'data-skin'] });
  renderReport();
  if (params.get('tab') === 'activity') bootstrap.Tab.getOrCreateInstance(ui.find('reportActivityTab')).show();
});
