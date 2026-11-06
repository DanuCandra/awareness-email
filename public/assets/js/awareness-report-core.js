'use strict';

(function (root, factory) {
  const api = factory(root.AwarenessPreview, root.AwarenessCampaigns);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.AwarenessReports = api;
})(typeof window === 'undefined' ? globalThis : window, function (preview, campaigns) {
  const statuses = {
    clicked: { label: 'Diakses', color: 'warning' },
    not_clicked: { label: 'Belum diakses', color: 'secondary' },
    uncertain: { label: 'Perlu diperiksa', color: 'warning' },
    skipped: { label: 'Tidak dikirim', color: 'secondary' },
    sending: { label: 'Sedang diproses', color: 'info' },
    failed: { label: 'Pengiriman gagal', color: 'danger' },
    pending: { label: 'Belum dikirim', color: 'info' }
  };
  const clients = [
    { browser: 'Chrome', os: 'Windows 11', device: 'Desktop' },
    { browser: 'Edge', os: 'Windows 11', device: 'Desktop' },
    { browser: 'Firefox', os: 'Ubuntu', device: 'Desktop' },
    { browser: 'Safari', os: 'macOS', device: 'Laptop' },
    { browser: 'Chrome', os: 'Android', device: 'Ponsel' }
  ];
  const count = value => Math.max(0, Math.floor(Number(value) || 0));
  const hasResults = campaign => ['running', 'completed'].includes(campaign.status);
  const campaignDate = campaign => String(campaign.scheduledAt || campaign.date || '').slice(0, 10);
  const periods = { within: 'Dalam periode', after: 'Setelah batas evaluasi', before: 'Sebelum mulai', unknown: 'Periode belum lengkap', none: 'Tidak ada akses' };
  function eventPeriod(campaign, at) {
    const start = campaigns.parseSchedule(campaign.scheduledAt), end = campaigns.parseSchedule(campaign.endsAt), time = Date.parse(at);
    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start || !Number.isFinite(time)) return 'unknown';
    return time < start ? 'before' : time > end ? 'after' : 'within';
  }

  function firstClickTimes(campaign, clicked) {
    let base = campaigns.parseSchedule(campaign.scheduledAt);
    if (!Number.isFinite(base)) base = campaigns.parseSchedule(campaignDate(campaign) + 'T09:00');
    if (!Number.isFinite(base)) return [];
    const hours = Array.isArray(campaign.hourly) ? campaign.hourly.map(count) : [];
    if (hours.reduce((sum, value) => sum + value, 0) !== clicked) {
      hours.length = 0;
      for (let hour = 0; hour < 6; hour++) hours.push(Math.floor(clicked / 6) + (hour < clicked % 6 ? 1 : 0));
    }
    return hours.flatMap((total, hour) => Array.from({ length: total }, (_, index) => new Date(base + (hour * 60 + 3 + Math.floor(index * 54 / total)) * 60000).toISOString()));
  }

  function recipientRows(campaign) {
    if (Array.isArray(campaign.recipientRows)) return JSON.parse(JSON.stringify(campaign.recipientRows));
    const ids = campaigns.recipientsFor(campaign);
    const registry = new Map((campaign.recipientRegistry || []).map(item => [item.id, item.email]));
    const sent = hasResults(campaign) ? Math.min(ids.length, count(campaign.sent)) : 0;
    const clicked = Math.min(sent, count(campaign.clicked));
    const firstTimes = firstClickTimes(campaign, clicked);
    return ids.map((id, index) => {
      const status = !hasResults(campaign) ? 'pending' : index >= sent ? 'failed' : index < clicked ? 'clicked' : 'not_clicked';
      const client = clients[(id - 1) % clients.length];
      const ip = `10.10.${20 + Math.floor(id / 200)}.${30 + id % 200}`;
      const events = status === 'clicked' && firstTimes[index] ? Array.from({ length: 1 + index % 3 }, (_, visit) => ({
        at: new Date(Date.parse(firstTimes[index]) + visit * 17 * 60000).toISOString(), ip, ...client
      })).map(event => ({ ...event, period: eventPeriod(campaign, event.at) })) : [];
      const withinClicks = events.filter(event => event.period === 'within').length;
      const afterClicks = events.filter(event => event.period === 'after').length;
      const beforeClicks = events.filter(event => event.period === 'before').length;
      const unclassifiedClicks = events.filter(event => event.period === 'unknown').length;
      return {
        id: campaign.id + '-recipient-' + id, recipientId: id,
        campaignId: campaign.id, campaignName: campaign.name, campaignDate: campaignDate(campaign),
        scheduledAt: campaign.scheduledAt || '', endsAt: campaign.endsAt || '', hasPeriod: !Object.keys(campaigns.validatePeriod(campaign, true)).length,
        withinClicks, afterClicks, beforeClicks, unclassifiedClicks,
        period: withinClicks ? 'within' : afterClicks ? 'after' : beforeClicks ? 'before' : unclassifiedClicks ? 'unknown' : 'none',
        email: registry.get(id) || `karyawan${String(id).padStart(3, '0')}@perusahaan.example`, status,
        delivery: status === 'pending' ? 'Belum dikirim' : status === 'failed' ? 'Gagal' : 'Berhasil',
        clicked: status === 'clicked', clicks: events.length,
        firstClick: events[0]?.at || '', lastClick: events.at(-1)?.at || '',
        ip: events.length ? ip : '', browser: events.length ? client.browser : '', os: events.length ? client.os : '', device: events.length ? client.device : '', events
      };
    });
  }

  function validateFilters(filters) {
    const validDate = value => !value || /^\d{4}-\d{2}-\d{2}$/.test(value) && Number.isFinite(campaigns.parseSchedule(value + 'T00:00'));
    if (!validDate(filters.from) || !validDate(filters.to)) return 'Gunakan tanggal yang valid.';
    if (filters.from && filters.to && filters.from > filters.to) return 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.';
    return '';
  }

  function filterCampaigns(items, filters = {}) {
    return items.filter(item => hasResults(item) && (!filters.campaign || item.id === filters.campaign) && (!filters.from || campaignDate(item) >= filters.from) && (!filters.to || campaignDate(item) <= filters.to))
      .sort((left, right) => campaignDate(right).localeCompare(campaignDate(left)));
  }

  function summarize(items, rows) {
    const target = rows.length;
    const sent = rows.filter(row => ['clicked', 'not_clicked'].includes(row.status)).length;
    const evaluationSent = rows.filter(row => row.hasPeriod && ['clicked', 'not_clicked'].includes(row.status)).length;
    const evaluated = rows.filter(row => row.hasPeriod && ['clicked', 'not_clicked'].includes(row.status));
    const clicked = evaluated.filter(row => row.withinClicks > 0).length;
    const sum = key => rows.reduce((total, row) => total + row[key], 0);
    return { campaigns: items.length, target, sent, clicked, failed: rows.filter(row => row.status === 'failed').length, unclicked: evaluationSent - clicked, rate: evaluationSent ? clicked / evaluationSent * 100 : 0, clicks: evaluated.reduce((total, row) => total + row.withinClicks, 0), uniqueEmails: new Set(evaluated.filter(row => row.withinClicks > 0).map(row => row.email)).size, evaluationSent, afterClicks: sum('afterClicks'), beforeClicks: sum('beforeClicks'), unclassifiedClicks: sum('unclassifiedClicks'), missingPeriods: items.filter(item => Object.keys(campaigns.validatePeriod(item, true)).length > 0).length };
  }

  function csvCell(value) {
    let result = String(value ?? '');
    if (/^[\s\uFEFF]*[=+\-@]/.test(result) || /^[\t\r\n]/.test(result)) result = "'" + result;
    return '"' + result.replace(/"/g, '""') + '"';
  }

  function toCsv(headers, rows) { return '\uFEFF' + [headers, ...rows].map(row => row.map(csvCell).join(',')).join('\r\n'); }

  function summaryExport(items) {
    return toCsv(['Simulasi', 'Mulai (WIB)', 'Batas evaluasi (WIB)', 'Status', 'Target', 'Berhasil', 'Gagal', 'Penerima mengeklik dalam periode', 'Tingkat klik dalam periode (%)', 'Akses setelah batas', 'Akses sebelum mulai', 'Akses belum diklasifikasi', 'Pengiriman berhasil dengan periode lengkap', 'Keterangan'], items.map(item => {
      const summary = summarize([item], recipientRows(item));
      return [item.name, campaigns.scheduleLabel(item.scheduledAt), campaigns.scheduleLabel(item.endsAt), campaigns.statuses[item.status]?.label || '', summary.target, summary.sent, summary.failed, summary.clicked, summary.evaluationSent ? summary.rate.toFixed(2) : '', summary.afterClicks, summary.beforeClicks, summary.unclassifiedClicks, summary.evaluationSent, item.dataSource === 'database' ? 'Aktivitas tersimpan' : 'Data contoh frontend'];
    }));
  }

  function activityExport(rows) {
    const date = value => value ? preview.formatDate(value, true) : '';
    return toCsv(['Email', 'Simulasi', 'Status', 'IP tercatat', 'Klik pertama (WIB)', 'Klik terakhir (WIB)', 'Total klik', 'Browser', 'Sistem operasi', 'Perangkat', 'Mulai (WIB)', 'Batas evaluasi (WIB)', 'Klasifikasi periode', 'Akses dalam periode', 'Akses setelah batas', 'Akses sebelum mulai', 'Akses belum diklasifikasi', 'Keterangan'], rows.map(row => [row.email, row.campaignName, statuses[row.status].label, row.ip, date(row.firstClick), date(row.lastClick), row.clicks, row.browser, row.os, row.device, campaigns.scheduleLabel(row.scheduledAt), campaigns.scheduleLabel(row.endsAt), periods[row.period], row.withinClicks, row.afterClicks, row.beforeClicks, row.unclassifiedClicks, row.dataSource === 'database' ? 'Aktivitas tersimpan' : 'Data contoh frontend']));
  }

  return { statuses, periods, eventPeriod, hasResults, campaignDate, recipientRows, validateFilters, filterCampaigns, summarize, toCsv, summaryExport, activityExport };
});
