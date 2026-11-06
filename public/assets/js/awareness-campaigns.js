'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('campaignList');
  if (!root) return;
  const core = window.AwarenessCampaigns, preview = window.AwarenessPreview, ui = window.AwarenessComposeUI;
  let state = core.load(ui.storage);
  const escape = preview.escapeHtml;
  function url(base, id, key = 'id') { const target = new URL(base); target.searchParams.set(key, id); return target.href; }
  const table = new DataTable(ui.find('campaignTable'), {
    data: state.campaigns,
    columns: [
      { data: 'name', render: (name, type, row) => type === 'display' ? `<a class="fw-medium" href="${escape(url(root.dataset.detailUrl, row.id))}">${escape(name)}</a><small class="d-block text-body-secondary mt-1 text-break">${escape(row.message.subject || 'Subjek belum diisi')}</small>` : name },
      { data: 'status', render: (status, type) => type === 'display' ? ui.badge(status) : status },
      { data: 'target', render: value => Number(value || 0).toLocaleString('id-ID') },
      { data: 'scheduledAt', render: (value, type) => type === 'sort' || type === 'type' ? core.parseSchedule(value) || 0 : escape(core.scheduleLabel(value)) },
      { data: 'endsAt', defaultContent: '', render: (value, type) => type === 'sort' || type === 'type' ? core.parseSchedule(value) || 0 : escape(core.scheduleLabel(value)) },
      { data: 'clicked', render: (value, type, row) => ['draft', 'scheduled'].includes(row.status) ? '—' : Number(value || 0).toLocaleString('id-ID') },
      { data: 'id', orderable: false, searchable: false, render: (id, type, row) => `<div class="d-flex gap-1"><a href="${escape(url(root.dataset.detailUrl, id))}" class="btn btn-icon btn-text-secondary rounded-pill" aria-label="Lihat detail simulasi"><i class="icon-base ti tabler-eye" aria-hidden="true"></i></a><button type="button" class="btn btn-icon btn-text-secondary rounded-pill" data-campaign-action="duplicate" data-id="${id}" aria-label="Duplikat simulasi"><i class="icon-base ti tabler-copy" aria-hidden="true"></i></button>${row.status === 'draft' ? `<a href="${escape(url(root.dataset.createUrl, id, 'edit'))}" class="btn btn-icon btn-text-secondary rounded-pill" aria-label="Edit draf"><i class="icon-base ti tabler-pencil" aria-hidden="true"></i></a><button type="button" class="btn btn-icon btn-text-danger rounded-pill" data-campaign-action="delete" data-id="${id}" aria-label="Hapus draf"><i class="icon-base ti tabler-trash" aria-hidden="true"></i></button>` : ''}</div>` }
    ],
    order: [], pageLength: 10, layout: ui.tableLayout, language: ui.tableLanguage
  });
  function counters() {
    root.querySelectorAll('[data-campaign-count]').forEach(element => { const status = element.dataset.campaignCount; element.textContent = status === 'total' ? state.campaigns.length : state.campaigns.filter(item => item.status === status).length; });
  }
  ui.find('campaignSearch').addEventListener('input', event => table.search(event.target.value).draw());
  ui.find('campaignStatusFilter').addEventListener('change', event => table.column(1).search(event.target.value).draw());
  ui.find('campaignPageLength').addEventListener('change', event => table.page.len(Number(event.target.value)).draw());
  let busy = false;
  root.addEventListener('click', async event => {
    const button = event.target.closest('[data-campaign-action]');
    if (!button || busy) return;
    const latest = core.load(ui.storage);
    const campaign = latest.campaigns.find(item => item.id === button.dataset.id);
    if (!campaign) { ui.notify('Simulasi tidak ditemukan. Muat ulang daftar untuk melihat data terbaru.'); return; }
    busy = true;
    try {
      if (button.dataset.campaignAction === 'duplicate') {
        const copy = core.duplicateCampaign(campaign, core.newId('campaign'), new Date().toISOString());
        latest.campaigns.unshift(copy);
        if (ui.persist(latest)) window.location.assign(url(root.dataset.createUrl, copy.id, 'edit'));
      } else if (campaign.status === 'draft' && await ui.confirmAction('Hapus draf simulasi?', `Hapus draf “${campaign.name}” dari pratinjau?`)) {
        latest.campaigns = latest.campaigns.filter(item => item.id !== campaign.id);
        if (ui.persist(latest)) { state = latest; table.clear().rows.add(state.campaigns).draw(); counters(); ui.notify('Draf simulasi dihapus dari pratinjau.'); }
      }
    } finally { busy = false; }
  });
  counters();
});
