@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endpush

@section('content')
    <div id="awarenessDashboard" data-reports-url="{{ route('reports.index') }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div><h4 class="mb-1">Dashboard</h4><p class="mb-0 text-body-secondary">Ringkasan simulasi dan respons penerima email.</p></div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="badge bg-label-secondary">Data contoh</span>
                <label class="visually-hidden" for="dashboardCampaign">Pilih simulasi</label>
                <select class="form-select w-auto mw-100" id="dashboardCampaign"><option value="november">Simulasi Kenaikan Gaji 2027 — PT AST INDONESIA</option></select>
            </div>
        </div>

        <div class="row g-6 mb-6">
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Target penerima</span><span class="badge p-2 bg-label-primary rounded"><i class="icon-base ti tabler-users icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-target>480</h3><p class="mb-0 text-body-secondary small">Alamat email unik dalam simulasi</p>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Pengiriman berhasil</span><span class="badge p-2 bg-label-success rounded"><i class="icon-base ti tabler-mail-check icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-sent>468</h3><p class="mb-0 text-body-secondary small"><span data-dashboard-failed>12</span> pengiriman gagal</p>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Penerima mengeklik</span><span class="badge p-2 bg-label-warning rounded"><i class="icon-base ti tabler-click icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-clicked>41</h3><p class="mb-0 text-body-secondary small">Dihitung per tautan penerima unik</p>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Tingkat klik <i class="icon-base ti tabler-info-circle icon-sm" data-bs-toggle="tooltip" title="Penerima unik yang tautannya diakses dibagi jumlah pengiriman berhasil." aria-label="Cara menghitung tingkat klik"></i></span><span class="badge p-2 bg-label-info rounded"><i class="icon-base ti tabler-chart-pie icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-rate>8,8%</h3><p class="mb-0 text-body-secondary small">Dari pengiriman yang berhasil</p>
            </div></div></div>
        </div>

        <div class="row g-6 mb-6">
            <div class="col-lg-8"><div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-3"><div><h5 class="card-title mb-1">Aktivitas klik pertama</h5><p class="card-subtitle">Penerima unik menurut jam akses · WIB</p></div><span class="badge bg-label-primary" data-dashboard-date>5 Nov 2026</span></div>
                <div class="card-body"><div id="clickActivityChart" role="img" aria-label="Grafik jumlah penerima unik menurut waktu klik pertama"></div></div>
            </div></div>
            <div class="col-lg-4"><div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-1">Respons penerima</h5><p class="card-subtitle">Dari pengiriman yang berhasil</p></div>
                <div class="card-body">
                    <div id="recipientResponseChart" role="img" aria-label="Perbandingan tautan yang sudah dan belum diakses"></div>
                    <div class="d-flex flex-wrap justify-content-between gap-3 mt-4">
                        <div><span class="badge badge-dot bg-primary me-2"></span><small>Diakses</small><h6 class="mt-1 mb-0 ms-4" data-dashboard-clicked>41</h6></div>
                        <div><span class="badge badge-dot bg-secondary me-2"></span><small>Belum diakses</small><h6 class="mt-1 mb-0 ms-4" data-dashboard-unclicked>427</h6></div>
                    </div>
                </div>
            </div></div>
        </div>

        <div class="row g-6">
            <div class="col-xl-8"><div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h5 class="card-title mb-1">Aktivitas penerima terbaru</h5><p class="card-subtitle">Akses tautan dan IP yang terlihat oleh server</p></div><a href="{{ route('reports.index') }}" class="btn btn-sm btn-label-primary" id="dashboardReportLink">Lihat laporan<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></a></div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Email penerima</th><th>IP tercatat</th><th>Klik pertama</th><th>Status</th></tr></thead><tbody id="recentClickRows"><tr><td colspan="4" class="text-center text-body-secondary py-8">Memuat aktivitas contoh…</td></tr></tbody></table></div>
            </div></div>
            <div class="col-xl-4"><div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-3"><div><h5 class="card-title mb-1">Simulasi terpilih</h5><p class="card-subtitle">Ringkasan pengiriman</p></div><span class="badge bg-label-success" id="campaignStatus">Berjalan</span></div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-6"><div class="avatar me-3"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-send icon-26px" aria-hidden="true"></i></span></div><div class="overflow-hidden"><h6 class="mb-1" id="selectedCampaignName">Simulasi Kenaikan Gaji 2027 — PT AST INDONESIA</h6><small class="text-body-secondary" data-dashboard-date>5 Nov 2026</small></div></div>
                    <div class="d-flex justify-content-between gap-2 mb-2"><span>Progres pengiriman</span><span class="fw-medium">100%</span></div>
                    <div class="progress mb-5" role="progressbar" aria-label="Progres pengiriman contoh" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar w-100"></div></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-body-secondary">Berhasil</span><span class="fw-medium" data-dashboard-sent>468</span></div>
                    <div class="d-flex justify-content-between mb-6"><span class="text-body-secondary">Gagal</span><span class="fw-medium text-danger" data-dashboard-failed>12</span></div>
                    <a href="{{ route('campaigns.index') }}" class="btn btn-label-primary w-100">Halaman simulasi</a>
                </div>
            </div></div>
        </div>
        <p class="small text-body-secondary mt-5 mb-0">Seluruh statistik dan aktivitas pada dashboard ini menggunakan data contoh.</p>
    </div>
@endsection

@push('vendor-scripts')
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/awareness-dashboard.js') }}"></script>
@endpush
