@extends('layouts.app')
@include('pages.partials.compose-assets', ['table' => true, 'editor' => false, 'wizard' => false, 'pageScript' => 'awareness-campaigns.js'])

@section('content')
    <div id="campaignList" data-create-url="{{ route('campaigns.create') }}" data-detail-url="{{ route('campaigns.show') }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-4 mb-6">
            <div><h4 class="mb-1">Simulasi</h4><p class="mb-0 text-body-secondary">Siapkan simulasi email dan tinjau setiap simulasi.</p></div>
            <div class="d-flex flex-wrap gap-3"><a href="{{ route('email-templates.index') }}" class="btn btn-label-secondary">Template email</a><a href="{{ route('campaigns.create') }}" class="btn btn-primary"><i class="icon-base ti tabler-plus icon-sm me-2" aria-hidden="true"></i>Buat simulasi</a></div>
        </div>
        <div class="row g-6 mb-6">
            @foreach ([['total', 'Semua simulasi', 'tabler-send', 'primary'], ['draft', 'Draf', 'tabler-file-pencil', 'secondary'], ['scheduled', 'Terjadwal', 'tabler-calendar-time', 'info'], ['running', 'Berjalan', 'tabler-player-play', 'success']] as [$key, $label, $icon, $color])
                <div class="col-sm-6 col-xl-3"><div class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3"><div><p class="mb-1">{{ $label }}</p><h4 class="mb-0" data-campaign-count="{{ $key }}">—</h4></div><div class="avatar"><span class="avatar-initial rounded bg-label-{{ $color }}"><i class="icon-base ti {{ $icon }} icon-26px" aria-hidden="true"></i></span></div></div></div></div>
            @endforeach
        </div>
        <div class="card">
            <div class="card-header border-bottom"><h5 class="card-title mb-1">Daftar simulasi</h5><p class="card-subtitle">Draf, jadwal, dan hasil simulasi dalam satu tempat</p></div>
            <div class="card-body py-5"><div class="row g-3">
                <div class="col-md-6"><label for="campaignSearch" class="form-label">Cari simulasi</label><input type="search" class="form-control" id="campaignSearch" placeholder="Cari nama simulasi…"></div>
                <div class="col-sm-6 col-md-3"><label for="campaignStatusFilter" class="form-label">Status</label><select class="form-select" id="campaignStatusFilter"><option value="">Semua status</option><option value="draft">Draf</option><option value="scheduled">Terjadwal</option><option value="running">Berjalan</option><option value="completed">Selesai</option></select></div>
                <div class="col-sm-6 col-md-3"><label for="campaignPageLength" class="form-label">Tampilkan</label><select class="form-select" id="campaignPageLength"><option value="10">10 baris</option><option value="25">25 baris</option><option value="50">50 baris</option></select></div>
            </div></div>
            <div class="card-datatable table-responsive pt-0"><table class="table" id="campaignTable"><thead><tr><th>Simulasi</th><th>Status</th><th>Penerima</th><th>Mulai · WIB</th><th>Selesai · Batas evaluasi · WIB</th><th>Klik unik</th><th>Aksi</th></tr></thead><tbody></tbody></table></div>
        </div>
    </div>
    @include('pages.partials.compose-feedback')
@endsection
