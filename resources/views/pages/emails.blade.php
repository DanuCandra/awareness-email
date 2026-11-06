@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/dropzone/dropzone.css') }}">
@endpush

@section('content')
    <div id="emailWorkspace">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div><h4 class="mb-1">Daftar Email</h4><p class="mb-0 text-body-secondary">Kelola alamat email penerima simulasi perusahaan.</p></div>
            <div class="d-flex flex-wrap gap-3"><button type="button" class="btn btn-label-primary" data-bs-toggle="offcanvas" data-bs-target="#emailEditor"><i class="icon-base ti tabler-plus icon-sm me-2" aria-hidden="true"></i>Tambah email</button><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#emailImportModal"><i class="icon-base ti tabler-upload icon-sm me-2" aria-hidden="true"></i>Impor Excel</button></div>
        </div>
        <div class="alert alert-primary d-flex align-items-start gap-3" role="note"><i class="icon-base ti tabler-info-circle icon-md flex-shrink-0 mt-1" aria-hidden="true"></i><div><span class="fw-medium">Mode pratinjau.</span> Daftar awal menggunakan data contoh. Perubahan di halaman ini akan direset saat dimuat ulang.</div></div>
        <div class="row g-6 mb-6">
            <div class="col-sm-4"><div class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3"><div><p class="mb-1">Total email</p><h4 class="mb-0" data-email-total>480</h4></div><div class="avatar"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-mail icon-26px" aria-hidden="true"></i></span></div></div></div></div>
            <div class="col-sm-4"><div class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3"><div><p class="mb-1">Dari impor file</p><h4 class="mb-0" data-email-imported>480</h4></div><div class="avatar"><span class="avatar-initial rounded bg-label-success"><i class="icon-base ti tabler-file-spreadsheet icon-26px" aria-hidden="true"></i></span></div></div></div></div>
            <div class="col-sm-4"><div class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3"><div><p class="mb-1">Ditambahkan manual</p><h4 class="mb-0" data-email-manual>0</h4></div><div class="avatar"><span class="avatar-initial rounded bg-label-info"><i class="icon-base ti tabler-user-plus icon-26px" aria-hidden="true"></i></span></div></div></div></div>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom"><div><h5 class="card-title mb-1">Email penerima</h5><p class="card-subtitle">Satu alamat email untuk setiap penerima</p></div><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-label-secondary btn-sm" id="downloadEmailTemplate"><i class="icon-base ti tabler-download icon-sm me-2" aria-hidden="true"></i>Template Excel</button><button type="button" class="btn btn-label-secondary btn-sm" id="exportEmailList"><i class="icon-base ti tabler-file-export icon-sm me-2" aria-hidden="true"></i>Ekspor CSV</button></div></div>
            <div class="card-body py-5">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-lg-4"><label for="emailSearch" class="form-label">Cari email</label><div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-search" aria-hidden="true"></i></span><input type="search" class="form-control" id="emailSearch" placeholder="Cari alamat email…"></div></div>
                    <div class="col-sm-3 col-lg-2"><label for="emailSourceFilter" class="form-label">Sumber</label><select id="emailSourceFilter" class="form-select"><option value="">Semua sumber</option><option value="Excel">Excel</option><option value="CSV">CSV</option><option value="Manual">Manual</option></select></div>
                    <div class="col-sm-3 col-lg-2"><label for="emailPageLength" class="form-label">Tampilkan</label><select id="emailPageLength" class="form-select"><option value="10">10 baris</option><option value="25">25 baris</option><option value="50">50 baris</option></select></div>
                    <div class="col-lg-4 d-flex flex-wrap justify-content-lg-end gap-2"><button type="button" class="btn btn-label-danger" id="deleteSelectedEmails" disabled>Hapus terpilih <span class="badge bg-danger ms-2" id="selectedEmailCount">0</span></button></div>
                </div>
            </div>
            <div class="card-datatable table-responsive pt-0"><table id="recipientTable" class="table"><thead><tr><th><input type="checkbox" class="form-check-input" id="selectPageEmails" aria-label="Pilih semua email pada halaman ini"></th><th>Email penerima</th><th>Sumber</th><th>Ditambahkan</th><th>Aksi</th></tr></thead><tbody></tbody></table></div>
        </div>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="emailEditor" aria-labelledby="emailEditorTitle">
            <div class="offcanvas-header border-bottom"><h5 class="offcanvas-title" id="emailEditorTitle">Tambah email</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button></div>
            <div class="offcanvas-body">
                <p class="text-body-secondary mb-6">Cukup masukkan alamat email perusahaan penerima.</p>
                <form id="emailEditorForm" novalidate><div class="mb-6"><label class="form-label" for="recipientEmail">Alamat email</label><input type="email" class="form-control" id="recipientEmail" placeholder="karyawan@perusahaan.com" required autocomplete="off"><div class="invalid-feedback" id="recipientEmailError"></div></div><div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Simpan ke pratinjau</button><button type="button" class="btn btn-label-secondary" data-bs-dismiss="offcanvas">Batal</button></div></form>
            </div>
        </div>

        <div class="modal fade" id="deleteEmailModal" tabindex="-1" aria-labelledby="deleteEmailTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="deleteEmailTitle">Hapus email?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><p class="mb-0" id="deleteEmailMessage"></p></div><div class="modal-footer"><button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger" id="confirmDeleteEmails">Hapus dari pratinjau</button></div></div></div></div>

        <div class="modal fade" id="emailImportModal" tabindex="-1" aria-labelledby="emailImportTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
            <div class="modal-header border-bottom"><div><h5 class="modal-title mb-1" id="emailImportTitle">Impor email dari Excel</h5><p class="text-body-secondary small mb-0">Pilih file, periksa hasil, lalu tambahkan email valid ke pratinjau.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body p-6">
                <ol class="list-unstyled d-flex flex-wrap gap-4 border-bottom pb-5 mb-6" aria-label="Tahapan impor"><li><span class="badge bg-primary me-2" data-import-step="1">1</span>Pilih file</li><li><span class="badge bg-label-secondary me-2" data-import-step="2">2</span>Periksa email</li><li><span class="badge bg-label-secondary me-2" data-import-step="3">3</span>Selesai</li></ol>
                <div id="importError" class="alert alert-danger d-none" role="alert"></div>
                <div data-import-pane="upload">
                    <div class="row g-6">
                        <div class="col-lg-8">
                            <div class="dropzone needsclick" id="importDropArea"><div class="dz-message needsclick"><i class="icon-base ti tabler-cloud-upload icon-48px text-primary mb-4" aria-hidden="true"></i><h5 class="mb-2">Tarik file Excel ke sini</h5><p class="text-body-secondary small mb-5">.xlsx atau .csv · maksimal 5 MB · 10.000 baris email</p><button type="button" class="btn btn-primary" id="browseImportFile">Pilih file</button></div></div>
                            <input type="file" id="importFile" accept=".xlsx,.csv" hidden>
                            <div id="selectedImportFile" class="alert alert-primary mt-4 mb-0 d-none"></div>
                            <div id="importLoading" class="mt-4 d-none" role="status"><span class="spinner-border spinner-border-sm text-primary me-2" aria-hidden="true"></span>Memeriksa file…</div>
                        </div>
                        <div class="col-lg-4">
                            <h6 class="mb-4">Format yang digunakan</h6>
                            <div class="table-responsive mb-4"><table class="table table-bordered table-sm mb-0"><thead><tr><th>email</th></tr></thead><tbody><tr><td>karyawan@perusahaan.com</td></tr></tbody></table></div>
                            <ul class="small text-body-secondary ps-4 mb-5"><li class="mb-2">Hanya satu kolom dengan judul <strong>email</strong>.</li><li class="mb-2">Excel dibaca dari worksheet pertama.</li><li>Email duplikat dan format salah akan ditandai.</li></ul>
                            <div class="d-grid gap-3">
                                <button type="button" class="btn btn-label-secondary btn-sm w-100" data-download-template>
                                    <i class="icon-base ti tabler-download icon-sm me-2" aria-hidden="true"></i>Unduh template Excel
                                </button>
                                <button type="button" class="btn btn-label-primary btn-sm w-100" id="useImportExample">
                                    <i class="icon-base ti tabler-file-description icon-sm me-2" aria-hidden="true"></i>Coba data contoh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div data-import-pane="review" hidden>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h6 class="mb-1" id="importFileName"></h6><small class="text-body-secondary" id="importRowSummary"></small></div><span class="badge bg-label-success">Pemeriksaan selesai</span></div>
                    <div class="row g-4 mb-6"><div class="col-sm-4"><div class="border rounded p-4"><small>Email valid baru</small><h4 class="text-success mb-0 mt-1" id="importValidCount">0</h4></div></div><div class="col-sm-4"><div class="border rounded p-4"><small>Duplikat / sudah ada</small><h4 class="text-warning mb-0 mt-1" id="importDuplicateCount">0</h4></div></div><div class="col-sm-4"><div class="border rounded p-4"><small>Format tidak valid</small><h4 class="text-danger mb-0 mt-1" id="importInvalidCount">0</h4></div></div></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><h6 class="mb-0">Pratinjau hasil pemeriksaan</h6><label class="d-flex align-items-center gap-2 small">Tampilkan<select class="form-select form-select-sm w-auto" id="importStatusFilter"><option value="">Semua hasil</option><option value="valid">Valid</option><option value="duplicate">Duplikat</option><option value="invalid">Tidak valid</option></select></label></div>
                    <div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Baris</th><th>Email</th><th>Status</th><th>Keterangan</th></tr></thead><tbody id="importPreviewRows"></tbody></table></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4"><small class="text-body-secondary" id="importPreviewInfo"></small><div class="btn-group"><button type="button" class="btn btn-sm btn-label-secondary" id="previousImportPage" aria-label="Halaman sebelumnya"><i class="icon-base ti tabler-chevron-left" aria-hidden="true"></i></button><button type="button" class="btn btn-sm btn-label-secondary" id="nextImportPage" aria-label="Halaman berikutnya"><i class="icon-base ti tabler-chevron-right" aria-hidden="true"></i></button></div></div>
                    <p class="small text-body-secondary mt-5 mb-0">Hanya email valid baru yang ditambahkan. Baris duplikat, kosong, dan tidak valid dilewati.</p>
                </div>
                <div data-import-pane="complete" hidden class="text-center py-8"><div class="avatar avatar-xl mx-auto mb-5"><span class="avatar-initial rounded-circle bg-label-success"><i class="icon-base ti tabler-check icon-48px" aria-hidden="true"></i></span></div><h4 class="mb-2">Daftar pratinjau diperbarui</h4><p id="importCompleteMessage" class="mb-2"></p><p class="small text-body-secondary mb-0">Perubahan akan direset saat halaman dimuat ulang.</p></div>
            </div>
            <div class="modal-footer border-top flex-column flex-sm-row align-items-stretch justify-content-sm-between gap-3 px-6 py-5">
                <button type="button" class="btn btn-label-secondary m-0" id="importBackButton">Batal</button>
                <button type="button" class="btn btn-primary m-0" id="importNextButton" disabled>Periksa file<i class="icon-base ti tabler-arrow-right icon-sm ms-2" aria-hidden="true"></i></button>
            </div>
        </div></div></div>

        <div class="toast-container position-fixed bottom-0 end-0 p-4" aria-live="polite" aria-atomic="true"><div class="toast" id="emailToast" role="status"><div class="toast-header"><i class="icon-base ti tabler-circle-check text-success me-2" aria-hidden="true"></i><strong class="me-auto">Daftar email</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Tutup"></button></div><div class="toast-body" id="emailToastMessage"></div></div></div>
    </div>
@endsection

@push('vendor-scripts')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/awareness-emails.js') }}"></script>
@endpush
