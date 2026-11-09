@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endpush

@section('content')
    <div id="awarenessDashboard" data-dashboard-url="{{ route('dashboard.data') }}" data-reports-url="{{ route('reports.index') }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div><h4 class="mb-1">Dashboard</h4><p class="mb-0 text-body-secondary">Ringkasan simulasi dan respons penerima email.</p></div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="small text-body-secondary" id="dashboardUpdated" aria-live="polite">Memuat data…</span>
                <label class="visually-hidden" for="dashboardCampaign">Pilih simulasi</label>
                <select class="form-select w-auto mw-100" id="dashboardCampaign" disabled><option value="">Memuat Simulasi…</option></select>
            </div>
        </div>

        <div class="row g-6 mb-6">
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Target penerima</span><span class="badge p-2 bg-label-primary rounded"><i class="icon-base ti tabler-users icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-target>0</h3><p class="mb-0 text-body-secondary small">Alamat email dalam Simulasi terpilih</p>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Pengiriman berhasil</span><span class="badge p-2 bg-label-success rounded"><i class="icon-base ti tabler-mail-check icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-sent>0</h3><p class="mb-0 text-body-secondary small"><span data-dashboard-pending>0</span> menunggu / diproses</p>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Penerima mengeklik</span><span class="badge p-2 bg-label-warning rounded"><i class="icon-base ti tabler-click icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-clicked>0</h3><p class="mb-0 text-body-secondary small">Penerima unik dalam masa evaluasi</p>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4"><span class="text-heading">Tingkat klik <i class="icon-base ti tabler-info-circle icon-sm" data-bs-toggle="tooltip" title="Penerima unik yang mengeklik dalam masa evaluasi dibagi jumlah pengiriman berhasil." aria-label="Cara menghitung tingkat klik"></i></span><span class="badge p-2 bg-label-info rounded"><i class="icon-base ti tabler-chart-pie icon-26px" aria-hidden="true"></i></span></div>
                <h3 class="mb-1" data-dashboard-rate>0,0%</h3><p class="mb-0 text-body-secondary small">Dari pengiriman yang berhasil</p>
            </div></div></div>
        </div>

        <div class="row g-6 mb-6">
            <div class="col-lg-8"><div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-3"><div><h5 class="card-title mb-1">Aktivitas klik pertama</h5><p class="card-subtitle">Penerima unik menurut jam akses · WIB</p></div><span class="badge bg-label-primary" data-dashboard-date>—</span></div>
                <div class="card-body"><div id="clickActivityChart" role="img" aria-label="Grafik jumlah penerima unik menurut waktu klik pertama"></div></div>
            </div></div>
            <div class="col-lg-4"><div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-1">Respons penerima</h5><p class="card-subtitle">Dalam masa evaluasi</p></div>
                <div class="card-body">
                    <div id="recipientResponseChart" role="img" aria-label="Perbandingan tautan yang sudah dan belum diakses"></div>
                    <div class="d-flex flex-wrap justify-content-between gap-3 mt-4">
                        <div><span class="badge badge-dot bg-primary me-2"></span><small>Diakses</small><h6 class="mt-1 mb-0 ms-4" data-dashboard-clicked>0</h6></div>
                        <div><span class="badge badge-dot bg-secondary me-2"></span><small>Belum diakses</small><h6 class="mt-1 mb-0 ms-4" data-dashboard-unclicked>0</h6></div>
                    </div>
                </div>
            </div></div>
        </div>

        <div class="row g-6">
            <div class="col-xl-8"><div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h5 class="card-title mb-1">Aktivitas penerima terbaru</h5><p class="card-subtitle">Akses tautan dan IP yang terlihat oleh server</p></div><a href="{{ route('reports.index') }}" class="btn btn-sm btn-label-primary" id="dashboardReportLink">Lihat laporan<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></a></div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Email penerima</th><th>IP tercatat</th><th>Klik pertama</th><th>Status</th></tr></thead><tbody id="recentClickRows"><tr><td colspan="4" class="text-center text-body-secondary py-8">Memuat aktivitas…</td></tr></tbody></table></div>
            </div></div>
            <div class="col-xl-4"><div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-3"><div><h5 class="card-title mb-1">Simulasi terpilih</h5><p class="card-subtitle">Ringkasan pengiriman</p></div><span class="badge bg-label-success" id="campaignStatus">—</span></div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-6"><div class="avatar me-3"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-send icon-26px" aria-hidden="true"></i></span></div><div class="overflow-hidden"><h6 class="mb-1" id="selectedCampaignName">Belum ada Simulasi</h6><small class="text-body-secondary" data-dashboard-date>—</small></div></div>
                    <div class="d-flex justify-content-between gap-2 mb-2"><span>Progres antrean</span><span class="fw-medium" id="dashboardProgressLabel">0%</span></div>
                    <div class="progress mb-5" role="progressbar" id="dashboardProgress" aria-label="Progres penyelesaian antrean" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: 0%"></div></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-body-secondary">Berhasil</span><span class="fw-medium" data-dashboard-sent>0</span></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-body-secondary">Menunggu / diproses</span><span class="fw-medium" data-dashboard-pending>0</span></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-body-secondary">Perlu diperiksa</span><span class="fw-medium text-warning" data-dashboard-uncertain>0</span></div>
                    <div class="d-flex justify-content-between mb-5"><span class="text-body-secondary">Tidak dikirim</span><span class="fw-medium" data-dashboard-skipped>0</span></div>
                    <p class="small text-body-secondary mb-3">Mulai: <span id="dashboardStartsAt">—</span><br>Selesai: <span id="dashboardEndsAt">—</span></p>
                    <a href="{{ route('campaigns.index') }}" class="btn btn-label-primary w-100">Halaman simulasi</a>
                </div>
            </div></div>
        </div>
        <p class="small text-body-secondary mt-5 mb-0">Tingkat klik hanya menghitung penerima dengan pengiriman berhasil yang mengeklik dalam masa evaluasi. Progres antrean mencakup pengiriman berhasil, perlu diperiksa, dan tidak dikirim. Data diperbarui setiap 15 detik.</p>
    </div>
@endsection

@push('vendor-scripts')
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/awareness-dashboard.js') }}"></script>
@endpush
