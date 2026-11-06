'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const root = document.getElementById('campaignDetail');
  if (!root) return;
  const core = window.AwarenessCampaigns, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI;
  const params = new URLSearchParams(window.location.search);
  const api = window.AwarenessApi;
  let campaign = null;
  try { if (params.get('id')) campaign = (await api.request(api.urls.simulations + '/' + encodeURIComponent(params.get('id')))).data; }
  catch (error) { api.showLoadError(root, error.message); }
  if (!campaign) { ui.find('detailNotFound').classList.remove('d-none'); ui.find('campaignDetailContent').hidden = true; ui.find('detailCampaignActions').classList.add('d-none'); return; }
  const escape = preview.escapeHtml;
  function editUrl(id) { const target = new URL(root.dataset.createUrl); target.searchParams.set('edit', id); return target.href; }
  function recipientRows() {
    return (campaign.recipientRows || []).map(item => ({ ...item, ip: item.ip || '—' }));
  }
  const recipientTable = new DataTable(ui.find('detailRecipientTable'), {
    data: recipientRows(),
    columns: [
      { data: 'email', render: (email, type) => type === 'display' ? escape(email) : email },
      { data: 'delivery', render: (value, type) => type === 'display' ? `<span class="badge bg-label-${value === 'Berhasil' ? 'success' : value === 'Gagal' ? 'danger' : 'secondary'}">${value}</span>` : value },
      { data: 'clicked', render: (value, type, row) => row.delivery === 'Belum dikirim' || row.delivery === 'Gagal' ? '—' : `<span class="badge bg-label-${value ? 'warning' : 'secondary'}">${value ? 'Diakses' : 'Belum diakses'}</span>` },
      { data: 'ip' }
    ], order: [[0, 'asc']], pageLength: 10, layout: ui.tableLayout, language: ui.tableLanguage
  });
  ui.find('detailRecipientSearch').addEventListener('input', event => recipientTable.search(event.target.value).draw());
  ui.find('detailDeliveryFilter').addEventListener('change', event => recipientTable.column(1).search(event.target.value).draw());
  ui.find('campaignRecipientsTab').addEventListener('shown.bs.tab', () => recipientTable.columns.adjust().draw(false));

  function render() {
    ui.find('detailCampaignName').textContent = campaign.name;
    ui.find('detailCampaignStatus').innerHTML = ui.badge(campaign.status);
    const metric = preview.metrics(campaign);
    ui.find('detailDeliverySummary').textContent = campaign.sent + ' diterima SMTP · ' + campaign.pending + ' menunggu/diproses · ' + campaign.uncertain + ' perlu diperiksa · ' + campaign.skipped + ' tidak dikirim';
    ui.find('detailDeliveryWarning').textContent = campaign.lastError || '';
    ui.find('detailDeliveryWarning').classList.toggle('d-none', !campaign.lastError);
    root.querySelectorAll('[data-detail-metric]').forEach(element => { const key = element.dataset.detailMetric; element.textContent = key === 'rate' ? campaign.sent > 0 ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(metric.rate) + '%' : '—' : Number(campaign[key] || 0).toLocaleString('id-ID'); });
    ui.find('detailDescription').textContent = campaign.description || '—';
    ui.find('detailSenderName').textContent = campaign.senderName || '—';
    ui.find('detailSenderEmail').textContent = campaign.senderEmail || '—';
    ui.find('detailSchedule').textContent = core.scheduleLabel(campaign.scheduledAt);
    ui.find('detailEndsAt').textContent = core.scheduleLabel(campaign.endsAt);
    ui.find('detailRecipientMode').textContent = campaign.target + ' email · ' + (campaign.recipientMode === 'all' ? 'Seluruh daftar email' : 'Pilihan khusus');
    ui.find('detailSubject').textContent = campaign.message.subject || 'Belum diisi';
    ui.find('detailUpdated').textContent = Number.isFinite(Date.parse(campaign.updatedAt)) ? preview.formatDate(campaign.updatedAt, true) : '—';
    ui.find('editDetailCampaign').classList.toggle('d-none', campaign.status !== 'draft'); ui.find('editDetailCampaign').href = editUrl(campaign.id);
    ui.find('cancelDetailSchedule').classList.toggle('d-none', campaign.status !== 'scheduled');
    ui.find('viewDetailReport').classList.toggle('d-none', !window.AwarenessReports.hasResults(campaign));
    const reportUrl = new URL(root.dataset.reportsUrl); reportUrl.searchParams.set('campaign', campaign.id); reportUrl.searchParams.set('tab', 'activity'); ui.find('viewDetailReport').href = reportUrl.href;
    const events = [{ title: 'Simulasi disiapkan', description: campaign.name, date: ui.find('detailUpdated').textContent, color: 'primary' }];
    if (campaign.status !== 'draft') events.push({ title: 'Jadwal pengiriman', description: core.scheduleLabel(campaign.scheduledAt), color: 'info' });
    if (['running', 'completed'].includes(campaign.status)) {
      events.push({ title: 'Pengiriman diproses', description: `${campaign.sent} diterima SMTP · ${campaign.pending} menunggu/diproses · ${campaign.uncertain} perlu diperiksa · ${campaign.skipped} tidak dikirim`, color: 'success' });
      events.push({ title: 'Aktivitas tautan tercatat', description: `${campaign.clicked} tautan penerima unik diakses`, color: 'warning' });
    }
    ui.find('campaignTimeline').innerHTML = events.map(event => `<li class="timeline-item timeline-item-transparent"><span class="timeline-point timeline-point-${event.color}"></span><div class="timeline-event"><div class="timeline-header mb-2"><h6 class="mb-0">${event.title}</h6></div><p class="mb-1 text-break">${escape(event.description)}</p>${event.date ? `<small class="text-body-secondary">${escape(event.date)}</small>` : ''}</div></li>`).join('');
    ui.paintPreview(ui.find('detailMailPreview'), campaign.message, { campaignName: campaign.name, senderName: campaign.senderName, senderEmail: campaign.senderEmail });
  }
  let busy = false;
  ui.find('duplicateDetailCampaign').addEventListener('click', async () => {
    if (busy) return; busy = true;
    try { const result = await api.request(api.urls.simulations + '/' + campaign.id + '/duplicate', { method: 'POST' }); window.location.assign(editUrl(result.data.id)); }
    catch (error) { ui.notify(error.message); busy = false; }
  });
  ui.find('cancelDetailSchedule').addEventListener('click', async () => {
    if (busy) return; busy = true;
    try {
      if (!await ui.confirmAction('Batalkan jadwal simulasi?', 'Simulasi akan dikembalikan menjadi draf apabila pengiriman belum dimulai.', 'Batalkan jadwal', 'danger')) return;
      const result = await api.request(api.urls.simulations + '/' + campaign.id + '/cancel', { method: 'POST' });
      campaign = result.data; render(); recipientTable.clear().rows.add(recipientRows()).draw(false); ui.notify('Jadwal dibatalkan. Simulasi kembali menjadi draf.');
    } catch (error) { ui.notify(error.message); }
    finally { busy = false; }
  });
  render();
  setInterval(async () => {
    if (busy || document.visibilityState !== 'visible') return;
    try { campaign = (await api.request(api.urls.simulations + '/' + campaign.id)).data; render(); recipientTable.clear().rows.add(recipientRows()).draw(false); } catch {}
  }, 15000);
  if (params.get('saved') === 'draft') ui.notify('Draf simulasi tersimpan.');
  if (params.get('saved') === 'scheduled') ui.notify('Simulasi terjadwal. Email akan diproses sesuai waktu mulai dan jeda SMTP.');
});
