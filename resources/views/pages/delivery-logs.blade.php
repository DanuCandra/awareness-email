@extends('layouts.app')

@section('content')
    <div id="deliveryLogs" data-index-url="{{ route('delivery-logs.index') }}" data-simulations-url="{{ route('campaigns.index') }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div><h4 class="mb-1">Log Pengiriman</h4><p class="mb-0 text-body-secondary">Pantau proses pengiriman dan buka kembali historinya.</p></div>
            <button type="button" class="btn btn-label-secondary" id="reloadDeliveryLogs"><i class="icon-base ti tabler-refresh icon-sm me-2" aria-hidden="true"></i>Muat ulang</button>
        </div>
        <div class="card mb-6"><div class="card-body">
            <div class="row g-4 align-items-end">
                <div class="col-lg-8"><label class="form-label" for="deliveryLogSimulation">Simulasi</label><select class="form-select" id="deliveryLogSimulation" disabled><option value="">Memuat Simulasi…</option></select></div>
                <div class="col-lg-4"><div class="d-flex flex-wrap align-items-center gap-3"><span class="badge bg-label-secondary" id="deliveryLogPhase">—</span><span class="small text-body-secondary" id="deliveryLogUpdated" aria-live="polite">Memuat data…</span></div></div>
            </div>
        </div></div>
        <div class="alert alert-danger d-none" id="deliveryLogError" role="alert"></div>
        <div class="row g-4 mb-6">
            @foreach ([['target', 'Total penerima'], ['sent', 'Diterima SMTP'], ['waiting', 'Menunggu'], ['processing', 'Diproses'], ['uncertain', 'Perlu diperiksa'], ['skipped', 'Tidak dikirim']] as [$key, $label])
                <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body"><p class="mb-2 text-body-secondary">{{ $label }}</p><h4 class="mb-0" data-log-count="{{ $key }}">0</h4></div></div></div>
            @endforeach
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-4">
                <div><h5 class="card-title mb-1" id="deliveryLogTitle">Proses pengiriman</h5><p class="card-subtitle" id="deliveryLogMode">100 log terbaru · pembaruan setiap 2 detik</p></div>
                <div class="d-flex flex-wrap gap-3"><button type="button" class="btn btn-sm btn-label-primary" id="latestDeliveryLogs" disabled>Terbaru (live)</button><button type="button" class="btn btn-sm btn-label-secondary" id="olderDeliveryLogs" disabled>Lebih lama<i class="icon-base ti tabler-chevron-right icon-sm ms-1" aria-hidden="true"></i></button></div>
            </div>
            <div class="table-responsive"><table class="table mb-0"><thead><tr><th class="text-nowrap">Waktu · WIB</th><th>Status</th><th>Email penerima</th><th>Keterangan</th></tr></thead><tbody id="deliveryLogRows"><tr><td colspan="4" class="text-center text-body-secondary py-8">Memuat log…</td></tr></tbody></table></div>
        </div>
        <p class="small text-body-secondary mt-5 mb-0">DONE pada penerima berarti email diterima server SMTP. Hasil yang belum pasti ditandai Perlu diperiksa dan tidak dikirim ulang otomatis. Selesainya pengiriman terpisah dari batas evaluasi Simulasi. Histori rinci dicatat sejak fitur ini aktif.</p>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/awareness-delivery-logs.js') }}"></script>
@endpush
